<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPin;
use App\Models\SubscriptionSetting;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SubscriptionPinController extends Controller
{
    /**
     * Redeem a 6-digit PIN to activate the user's subscription.
     */
    public function redeem(Request $request)
    {
        $request->validate([
            'pin' => 'required|string|size:6|regex:/^\d{6}$/',
        ]);

        $user = auth()->user();

        // Find the PIN — it must belong to this user and be unused
        $subscriptionPin = SubscriptionPin::where('pin', $request->pin)
            ->where('user_id', $user->id)
            ->where('status', 'unused')
            ->first();

        if (!$subscriptionPin) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or already used PIN. Please check the PIN and try again.',
            ], 422);
        }

        // Check PIN has not expired
        if ($subscriptionPin->expires_at && $subscriptionPin->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'This PIN has expired. Please request a new one from admin.',
            ], 422);
        }

        // Calculate expiry date
        $days          = (int) SubscriptionSetting::get('default_subscription_days', 365);
        $globalExpiry  = SubscriptionSetting::get('global_expiry_date');

        if ($globalExpiry) {
            $expiresAt = Carbon::parse($globalExpiry)->endOfDay();
        } else {
            $expiresAt = now()->addDays($days);
        }

        // Activate the user's subscription
        $user->update([
            'subscription_status'    => 'active',
            'subscription_type'      => 'pin',
            'subscription_expires_at'=> $expiresAt,
            'subscription_device_id' => null,
        ]);

        // Mark PIN as consumed
        $subscriptionPin->update([
            'status'  => 'used',
            'used_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription activated successfully!',
            'data'    => [
                'subscription_status'    => 'active',
                'subscription_type'      => 'pin',
                'subscription_expires_at'=> $expiresAt->toIso8601String(),
            ],
        ]);
    }
}
