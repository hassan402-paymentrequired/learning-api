<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPin;
use App\Models\SubscriptionSetting;
use App\Services\ReferralService;
use App\Services\SubscriptionEmailService;
use App\Support\PublicId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SubscriptionPinController extends Controller
{
    /**
     * Redeem a 6-digit PIN to activate the user's subscription.
     */
    public function redeem(Request $request, SubscriptionEmailService $subscriptionEmail, ReferralService $referralService)
    {
        $request->validate([
            'pin' => 'required|string|size:6|regex:/^\d{6}$/',
        ]);

        $user = auth()->user();
        $deviceId = $request->header('X-Device-Id');

        if (empty($deviceId)) {
            return response()->json([
                'success' => false,
                'message' => 'Device ID is required to activate subscription.',
            ], 400);
        }

        try {
            $subscription = DB::transaction(function () use ($request, $user, $deviceId) {
                // Lock the PIN row for the duration of the transaction so two
                // concurrent requests can't both pass the "unused" check.
                $subscriptionPin = SubscriptionPin::where('pin', $request->pin)
                    ->where('user_id', $user->id)
                    ->where('status', 'unused')
                    ->lockForUpdate()
                    ->first();

                if (!$subscriptionPin) {
                    throw new \RuntimeException('invalid_pin');
                }

                if ($subscriptionPin->expires_at && $subscriptionPin->expires_at->isPast()) {
                    throw new \RuntimeException('expired_pin');
                }

                // Calculate expiry date
                $days          = (int) SubscriptionSetting::get('default_subscription_days', 365);
                $globalExpiry  = SubscriptionSetting::get('global_expiry_date');

                if ($globalExpiry) {
                    $expiresAt = Carbon::parse($globalExpiry)->endOfDay();
                } else {
                    $expiresAt = now()->addDays($days);
                }

                // Activate the user's subscription by creating a new subscription record
                $subscription = \App\Models\Subscription::create([
                    'user_id'              => $user->id,
                    'subscription_plan_id' => 1, // Default or find appropriate plan ID
                    'status'               => 'active',
                    'type'                 => 'pin',
                    'starts_at'            => now(),
                    'expires_at'           => $expiresAt,
                    'device_id'            => $deviceId,
                    'amount_paid'          => 0, // PIN-based is usually prepaid/free at this point
                    'original_amount'      => 0,
                    'discount_amount'      => 0,
                    'notes'                => "Activated via PIN: {$subscriptionPin->pin}",
                ]);

                // Mark PIN as consumed
                $subscriptionPin->update([
                    'status'  => 'used',
                    'used_at' => now(),
                ]);

                return $subscription;
            });
        } catch (\RuntimeException $e) {
            $message = $e->getMessage() === 'expired_pin'
                ? 'This PIN has expired. Please request a new one from admin.'
                : 'Invalid or already used PIN. Please check the PIN and try again.';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        $referralService->rewardOnSubscription($user, $subscription->fresh());
        $referralService->ensureReferralCode($user);

        $subscriptionEmail->sendReceipt($subscription->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Subscription activated successfully!',
            'data'    => [
                'subscription' => PublicId::subscription($subscription->fresh()->load('plan')),
            ],
        ]);
    }
}
