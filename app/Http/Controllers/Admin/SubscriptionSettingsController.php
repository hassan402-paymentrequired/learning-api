<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionSettingsController extends Controller
{
    /**
     * Show the subscription settings page.
     */
    public function index()
    {
        $settings = [
            'default_subscription_days' => SubscriptionSetting::get('default_subscription_days', '365'),
            'global_expiry_date'        => SubscriptionSetting::get('global_expiry_date'),
        ];

        // Summary counts
        $summary = [
            'total_users'        => User::count(),
            'active_users'       => User::where('subscription_status', 'active')
                                        ->where('subscription_expires_at', '>', now())
                                        ->count(),
            'expired_users'      => User::where('subscription_status', 'active')
                                        ->where('subscription_expires_at', '<=', now())
                                        ->count(),
            'inactive_users'     => User::whereIn('subscription_status', ['inactive', 'cancelled', null])
                                        ->orWhereNull('subscription_status')
                                        ->count(),
        ];

        return Inertia::render('admin/subscription-settings/index', [
            'settings' => $settings,
            'summary'  => $summary,
        ]);
    }

    /**
     * Update subscription settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'default_subscription_days' => 'required|integer|min:1|max:3650',
            'global_expiry_date'        => 'nullable|date|after:today',
        ]);

        SubscriptionSetting::set('default_subscription_days', $validated['default_subscription_days']);
        SubscriptionSetting::set('global_expiry_date', $validated['global_expiry_date'] ?? null);

        return back()->with('success', 'Subscription settings saved.');
    }

    /**
     * Apply the global expiry date to ALL currently active users.
     */
    public function applyGlobalExpiry(Request $request)
    {
        $globalExpiry = SubscriptionSetting::get('global_expiry_date');

        if (!$globalExpiry) {
            return back()->withErrors(['global_expiry_date' => 'No global expiry date is set.']);
        }

        $expiresAt = \Carbon\Carbon::parse($globalExpiry)->endOfDay();

        User::where('subscription_status', 'active')->update([
            'subscription_expires_at' => $expiresAt,
        ]);

        return back()->with('success', "Global expiry applied: all active subscriptions now expire on {$expiresAt->toDateString()}.");
    }
}
