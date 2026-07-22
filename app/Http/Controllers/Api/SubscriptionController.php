<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\PublicId;
use App\Services\ReferralService;
use App\Services\SubscriptionEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    /**
     * Resolve the device ID to bind when activating a subscription.
     * Prefers the device stored at payment initialization over the request header.
     */
    private function resolveDeviceIdForSubscription(Subscription $subscription, ?string $headerDeviceId): ?string
    {
        if (!empty($subscription->device_id)) {
            return $subscription->device_id;
        }

        return !empty($headerDeviceId) ? $headerDeviceId : null;
    }

    /**
     * Build subscription update payload for activation, binding the purchasing device.
     */
    private function buildActivationPayload(Subscription $subscription, ?string $headerDeviceId): array
    {
        $payload = [
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
            'type' => $subscription->type ?? 'paystack',
        ];

        $deviceId = $this->resolveDeviceIdForSubscription($subscription, $headerDeviceId);
        if ($deviceId) {
            $payload['device_id'] = $deviceId;
        }

        return $payload;
    }

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
            'data' => PublicId::subscriptionPlan($plan),
        ]);
    }

    /**
     * Initialize payment with Paystack.
     */
    public function initializePayment(Request $request)
    {
        $request->validate([
            'plan_uuid' => 'required_without:plan_id|uuid|exists:subscription_plans,uuid',
            'plan_id' => 'required_without:plan_uuid|exists:subscription_plans,id',
            'referral_code' => 'nullable|string|exists:users,referral_code',
        ]);

        $user = auth()->user();
        $plan = $request->filled('plan_uuid')
            ? SubscriptionPlan::where('uuid', $request->plan_uuid)->firstOrFail()
            : SubscriptionPlan::findOrFail($request->plan_id);
        $deviceId = $request->header('X-Device-Id');

        if (empty($deviceId)) {
            return response()->json([
                'success' => false,
                'message' => 'Device ID is required to subscribe.',
            ], 400);
        }

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

        // Apply discount if user was referred
        if ($request->has('referral_code') && $request->referral_code) {
            $referrer = User::where('referral_code', $request->referral_code)->first();
            if ($referrer && $referrer->id !== $user->id) {
                if (!$user->referred_by) {
                    $discountAmount = $originalAmount * (config('referral.referred_discount_percent', 5) / 100);
                    $finalAmount = $originalAmount - $discountAmount;
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

        // Create pending subscription record, bound to the device initiating payment
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'paystack_reference' => $paymentData['reference'],
            'amount_paid' => $finalAmount,
            'original_amount' => $originalAmount,
            'discount_amount' => $discountAmount,
            'status' => 'pending',
            'device_id' => $deviceId,
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
                'subscription_uuid' => $subscription->uuid,
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
    public function callback(Request $request, SubscriptionEmailService $subscriptionEmail, ReferralService $referralService)
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
            DB::transaction(function () use ($subscription, $transactionData, $referralService) {
                $user = $subscription->user;

                $subscription->update($this->buildActivationPayload($subscription, null));

                $referralService->rewardOnSubscription($user, $subscription->fresh());
                $referralService->ensureReferralCode($user);
            });

            $subscriptionEmail->sendReceipt($subscription->fresh());
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $redirectUrl = $frontendUrl.'/subscription?reference='.urlencode($reference);
        $safeReference = json_encode($reference);
        $safeFrontendOrigin = json_encode($frontendUrl);
        $safeRedirectUrl = json_encode($redirectUrl);

        // Notify popup opener (web) and redirect same-window users back to the app.
        // Mobile WebView detects this /subscriptions/callback URL before the redirect runs.
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Payment Successful</title>
</head>
<body>
  <h1>Payment Successful</h1>
  <p>Your subscription has been activated. You can close this window.</p>
  <script>
    (function () {
      var reference = {$safeReference};
      var frontendOrigin = {$safeFrontendOrigin};
      var redirectUrl = {$safeRedirectUrl};

      if (window.opener && !window.opener.closed) {
        try {
          window.opener.postMessage(
            { type: 'payment_success', reference: reference },
            frontendOrigin
          );
        } catch (e) {}
        setTimeout(function () { window.close(); }, 400);
        return;
      }

      window.location.replace(redirectUrl);
    })();
  </script>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html');
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
    public function verifyPayment(Request $request, SubscriptionEmailService $subscriptionEmail, ReferralService $referralService)
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
        $deviceId = $request->header('X-Device-Id');

        if ($subscription->status !== 'active') {
            DB::transaction(function () use ($subscription, $transactionData, $deviceId, $referralService) {
                $user = $subscription->user;

                $subscription->update($this->buildActivationPayload($subscription, $deviceId));

                $referralService->rewardOnSubscription($user, $subscription->fresh());
                $referralService->ensureReferralCode($user);
            });

            $subscriptionEmail->sendReceipt($subscription->fresh());
        } elseif (!empty($deviceId)) {
            // Bind or rebind to the browser completing verification.
            // Covers: legacy unbound rows, and web clients that lost localStorage
            // between Paystack redirect and finalize (callback already activated).
            if (empty($subscription->device_id) || $subscription->device_id !== $deviceId) {
                $subscription->update(['device_id' => $deviceId]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription activated successfully.',
            'data' => [
                'subscription' => PublicId::subscription($subscription->fresh()->load('plan')),
            ],
        ]);
    }

    /**
     * Bind the current device ID to the user's subscription.
     * Call this after successful payment so subscription is only valid from this device.
     */
    public function registerDevice(Request $request)
    {
        $user = auth()->user();
        $deviceId = $request->header('X-Device-Id');

        if (empty($deviceId)) {
            return response()->json([
                'success' => false,
                'message' => 'Device ID is required.',
            ], 400);
        }

        // Find an active subscription for this user that is NOT yet bound to any device
        $subscription = $user->subscriptions()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->whereNull('device_id')
            ->orderBy('expires_at', 'desc')
            ->first();

        if (!$subscription) {
            // Check if there's already one bound to this device
            $existing = $user->subscriptions()
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->where('device_id', $deviceId)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'This device is already linked to an active subscription.',
                ]);
            }

            // Recent purchase reclaim: web clients often lose localStorage during/after
            // Paystack and generate a new device id. Allow rebinding within 48 hours of activation.
            $recentOtherDevice = $user->subscriptions()
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->whereNotNull('device_id')
                ->where('device_id', '!=', $deviceId)
                ->where('starts_at', '>=', now()->subHours(48))
                ->orderByDesc('starts_at')
                ->first();

            if ($recentOtherDevice) {
                $recentOtherDevice->update(['device_id' => $deviceId]);

                return response()->json([
                    'success' => true,
                    'message' => 'Subscription linked to this browser.',
                    'code' => 'DEVICE_RECLAIMED',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'You do not have any unbound active subscriptions. Please purchase a new one for this device.',
                'code' => 'NO_UNBOUND_SUBSCRIPTION',
            ], 403);
        }

        $subscription->update(['device_id' => $deviceId]);

        return response()->json([
            'success' => true,
            'message' => 'This device is now linked to your subscription.',
            'data' => [
                'subscription_uuid' => $subscription->uuid,
                'expires_at' => $subscription->expires_at->toIso8601String(),
            ]
        ]);
    }

    /**
     * Get current user's subscription status.
     * Subscription is valid only on the device that was used when subscribing (if device binding is set).
     */
    public function status(Request $request)
    {
        $user = auth()->user();
        $deviceId = $request->header('X-Device-Id');

        $activeSubscription = !empty($deviceId)
            ? $user->activeSubscription($deviceId)
            : null;

        $hasActiveForDevice = $activeSubscription !== null;

        // Check if there are active subscriptions on OTHER devices
        $otherActiveQuery = $user->subscriptions()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->whereNotNull('device_id');

        if (!empty($deviceId)) {
            $otherActiveQuery->where('device_id', '!=', $deviceId);
        }

        $otherActiveCount = $otherActiveQuery->count();

        // Unbound active subscriptions exist but cannot be used until bound on the purchasing device
        $unboundActiveCount = $user->subscriptions()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->whereNull('device_id')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'has_active_subscription' => $hasActiveForDevice,
                'other_devices_active' => $otherActiveCount > 0,
                'needs_device_binding' => $unboundActiveCount > 0,
                'subscription_device_bound' => $activeSubscription && !empty($activeSubscription->device_id),
                'subscription' => $activeSubscription
                    ? PublicId::subscription($activeSubscription->load('plan'))
                    : null,
            ],
        ]);
    }
}
