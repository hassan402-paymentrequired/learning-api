<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    /**
     * Get available subscription plans.
     */
    public function plans(Request $request)
    {
        $plan = SubscriptionPlan::where('is_active', true)
            ->where('interval', 'year')
            ->first();

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'No subscription plan available.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => (float) $plan->price,
                'currency' => $plan->currency,
                'interval' => $plan->interval,
                'interval_count' => $plan->interval_count,
            ],
        ]);
    }

    /**
     * Initialize payment with Paystack.
     */
    public function initializePayment(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'referral_code' => 'nullable|string|exists:users,referral_code',
        ]);

        $user = auth()->user();
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        // Check if plan is active
        if (!$plan->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Selected plan is not available.',
            ], 400);
        }

        // Calculate amount with referral discount
        $originalAmount = (float) $plan->price;
        $discountAmount = 0;
        $finalAmount = $originalAmount;
        $referral = null;

        // Apply 5% discount if user was referred
        $referral = null;
        if ($request->has('referral_code') && $request->referral_code) {
            $referrer = User::where('referral_code', $request->referral_code)->first();
            if ($referrer && $referrer->id !== $user->id) {
                // Check if user hasn't been referred before
                if (!$user->referred_by) {
                    $discountAmount = $originalAmount * 0.05; // 5% discount
                    $finalAmount = $originalAmount;
                    // Store referral relationship (will be finalized after payment)
                    $user->referred_by = $referrer->id;
                    $user->save();

                    // Create or get existing referral record
                    $referral = \App\Models\Referral::firstOrCreate(
                        [
                            'referred_id' => $user->id,
                        ],
                        [
                            'referrer_id' => $referrer->id,
                            'referred_discount_amount' => $discountAmount,
                            'status' => 'pending',
                        ]
                    );
                } else {
                    // User already has a referrer, use existing referral
                    $referral = \App\Models\Referral::where('referred_id', $user->id)->first();
                    if ($referral) {
                        $discountAmount = $originalAmount * 0.05; // 5% discount
                        $finalAmount = $originalAmount - $discountAmount;
                    }
                }
            }
        } else {
            // Check if user was already referred (from signup)
            if ($user->referred_by) {
                $referral = \App\Models\Referral::where('referred_id', $user->id)->first();
                if ($referral && $referral->status === 'pending') {
                    $discountAmount = $originalAmount * 0.05; // 5% discount
                    $finalAmount = $originalAmount - $discountAmount;
                }
            }
        }

        // Initialize Paystack payment
        $paystackSecretKey = config('services.paystack.secret_key');

        if (!$paystackSecretKey) {
            return response()->json([
                'success' => false,
                'message' => 'Payment service not configured. Please contact support.',
            ], 500);
        }

        // Create or get Paystack customer
        $customerCode = $user->paystack_customer_code;
        if (!$customerCode) {
            // Create customer on Paystack
            $customerResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $paystackSecretKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.paystack.co/customer', [
                'email' => $user->email,
                'first_name' => explode(' ', $user->name)[0],
                'last_name' => count(explode(' ', $user->name)) > 1 ? implode(' ', array_slice(explode(' ', $user->name), 1)) : '',
            ]);

            if ($customerResponse->successful()) {
                $customerData = $customerResponse->json('data');
                $customerCode = $customerData['customer_code'];
                $user->paystack_customer_code = $customerCode;
                $user->save();
            }
        }

        // Initialize transaction
        $amountInKobo = (int) ($finalAmount * 100); // Convert to kobo (Paystack uses kobo)
        $reference = 'SUB_' . time() . '_' . $user->id . '_' . uniqid();

        // Use backend callback URL - Paystack will redirect here after payment
        $callbackUrlForPaystack = url('/api/subscriptions/callback');

        $paymentResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $paystackSecretKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.paystack.co/transaction/initialize', [
            'email' => $user->email,
            'amount' => $amountInKobo,
            'currency' => 'NGN',
            'reference' => $reference,
            'callback_url' => $callbackUrlForPaystack,
            'metadata' => [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'original_amount' => $originalAmount,
                'discount_amount' => $discountAmount,
                'referral_id' => $referral?->id,
            ],
        ]);

        if (!$paymentResponse->successful()) {
            Log::error('Paystack initialization failed', [
                'response' => $paymentResponse->json(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize payment. Please try again.',
            ], 500);
        }

        $paymentData = $paymentResponse->json('data');

        // Create pending subscription record
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'paystack_reference' => $paymentData['reference'],
            'amount_paid' => $finalAmount,
            'original_amount' => $originalAmount,
            'discount_amount' => $discountAmount,
            'status' => 'pending',
        ]);

        // Generate callback and cancel URLs for frontend
        // Paystack will redirect to callback_url and append ?reference=xxx
        // So we return the base URL without query params for frontend to detect navigation
        $callbackUrl = url('/api/subscriptions/callback');
        $cancelUrl = url('/api/subscriptions/cancel');

        return response()->json([
            'success' => true,
            'data' => [
                'authorization_url' => $paymentData['authorization_url'],
                'access_code' => $paymentData['access_code'],
                'reference' => $paymentData['reference'],
                'subscription_id' => $subscription->id,
                'callback_url' => $callbackUrl,
                'cancel_url' => $cancelUrl,
            ],
        ]);
    }

    /**
     * Handle Paystack callback (per Paystack WebView documentation).
     * This route is called by Paystack after payment.
     * Returns a simple HTML page that the WebView can detect.
     */
    public function callback(Request $request)
    {
        $reference = $request->query('reference');
        $trxref = $request->query('trxref', $reference);

        if (!$reference && !$trxref) {
            // Return error page
            return response('<!DOCTYPE html><html><head><title>Payment Error</title></head><body><h1>Payment Error</h1><p>Payment reference not found</p></body></html>', 400)
                ->header('Content-Type', 'text/html');
        }

        $reference = $reference ?: $trxref;

        // Verify payment with Paystack
        $paystackSecretKey = config('services.paystack.secret_key');

        $verifyResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $paystackSecretKey,
            'Content-Type' => 'application/json',
        ])->get('https://api.paystack.co/transaction/verify/' . $reference);

        if (!$verifyResponse->successful()) {
            return response('<!DOCTYPE html><html><head><title>Payment Error</title></head><body><h1>Payment Error</h1><p>Payment verification failed</p></body></html>', 400)
                ->header('Content-Type', 'text/html');
        }

        $transactionData = $verifyResponse->json('data');

        if ($transactionData['status'] !== 'success') {
            return response('<!DOCTYPE html><html><head><title>Payment Error</title></head><body><h1>Payment Error</h1><p>Payment was not successful</p></body></html>', 400)
                ->header('Content-Type', 'text/html');
        }

        // Find subscription by reference
        $subscription = Subscription::where('paystack_reference', $reference)->first();

        if (!$subscription) {
            return response('<!DOCTYPE html><html><head><title>Payment Error</title></head><body><h1>Payment Error</h1><p>Subscription not found</p></body></html>', 404)
                ->header('Content-Type', 'text/html');
        }

        // Activate subscription if not already active
        if ($subscription->status !== 'active') {
            DB::transaction(function () use ($subscription, $transactionData) {
                $user = $subscription->user;
                $plan = $subscription->plan;

                // Calculate expiration date (1 year from now)
                $expiresAt = now()->addYear();

                // Update subscription
                $subscription->update([
                    'status' => 'active',
                    'starts_at' => now(),
                    'expires_at' => $expiresAt,
                ]);

                // Update user subscription status
                $user->update([
                    'subscription_status' => 'active',
                    'subscription_expires_at' => $expiresAt,
                ]);

                // Process referral rewards: referrer gets 500 credit when referred user subscribes
                $referral = \App\Models\Referral::where('referred_id', $user->id)
                    ->where('status', 'pending')
                    ->first();

                if ($referral) {
                    $referral->update([
                        'subscription_id' => $subscription->id,
                        'referrer_reward_amount' => 500,
                        'status' => 'rewarded',
                        'rewarded_at' => now(),
                    ]);
                    Log::info('Referral rewarded: referrer_id=' . $referral->referrer_id . ', referred_id=' . $user->id . ', subscription_id=' . $subscription->id);
                }

                // Generate referral code for user if they don't have one
                if (!$user->referral_code) {
                    $user->generateReferralCode();
                }
            });
        }

        // Return success page (WebView will detect navigation to this URL)
        return response('<!DOCTYPE html><html><head><title>Payment Successful</title></head><body><h1>Payment Successful</h1><p>Your subscription has been activated. You can close this window.</p></body></html>', 200)
            ->header('Content-Type', 'text/html');
    }

    /**
     * Handle payment cancellation (per Paystack WebView documentation).
     * Returns a simple HTML page that the WebView can detect.
     */
    public function cancel()
    {
        return response('<!DOCTYPE html><html><head><title>Payment Cancelled</title></head><body><h1>Payment Cancelled</h1><p>You have cancelled the payment process.</p></body></html>', 200)
            ->header('Content-Type', 'text/html');
    }

    /**
     * Verify payment with Paystack.
     */
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'reference' => 'required|string',
        ]);

        $subscription = Subscription::where('paystack_reference', $request->reference)->firstOrFail();

        // Verify with Paystack
        $paystackSecretKey = config('services.paystack.secret_key');

        $verifyResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $paystackSecretKey,
            'Content-Type' => 'application/json',
        ])->get('https://api.paystack.co/transaction/verify/' . $request->reference);

        if (!$verifyResponse->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed.',
            ], 400);
        }

        $transactionData = $verifyResponse->json('data');

        if ($transactionData['status'] !== 'success') {
            $subscription->update(['status' => 'failed']);

            return response()->json([
                'success' => false,
                'message' => 'Payment was not successful.',
            ], 400);
        }

        // Payment successful - activate subscription
        DB::transaction(function () use ($subscription, $transactionData) {
            $user = $subscription->user;
            $plan = $subscription->plan;

            // Calculate expiration date (1 year from now)
            $expiresAt = now()->addYear();

            // Update subscription
            $subscription->update([
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            // Update user subscription status
            $user->update([
                'subscription_status' => 'active',
                'subscription_expires_at' => $expiresAt,
            ]);

            // Process referral rewards: referrer gets 500 credit when referred user subscribes
            $referral = \App\Models\Referral::where('referred_id', $user->id)
                ->where('status', 'pending')
                ->first();

            if ($referral) {
                $referral->update([
                    'subscription_id' => $subscription->id,
                    'referrer_reward_amount' => 500,
                    'status' => 'rewarded',
                    'rewarded_at' => now(),
                ]);
                Log::info('Referral rewarded (verifyPayment): referrer_id=' . $referral->referrer_id . ', referred_id=' . $user->id . ', subscription_id=' . $subscription->id);
            }

            // Generate referral code for user if they don't have one
            if (!$user->referral_code) {
                $user->generateReferralCode();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Subscription activated successfully.',
            'data' => [
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'expires_at' => $subscription->expires_at,
                ],
                'user' => [
                    'subscription_status' => $subscription->user->subscription_status,
                    'subscription_expires_at' => $subscription->user->subscription_expires_at,
                ],
            ],
        ]);
    }

    /**
     * Get current user's subscription status.
     */
    public function status(Request $request)
    {
        $user = auth()->user();

        $activeSubscription = $user->activeSubscription;

        return response()->json([
            'success' => true,
            'data' => [
                'has_active_subscription' => $user->hasActiveSubscription(),
                'subscription_status' => $user->subscription_status,
                'subscription_expires_at' => $user->subscription_expires_at,
                'subscription' => $activeSubscription ? [
                    'id' => $activeSubscription->id,
                    'plan' => [
                        'name' => $activeSubscription->plan->name,
                        'price' => (float) $activeSubscription->plan->price,
                    ],
                    'expires_at' => $activeSubscription->expires_at,
                ] : null,
            ],
        ]);
    }
}
