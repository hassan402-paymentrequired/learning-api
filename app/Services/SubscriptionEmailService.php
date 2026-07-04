<?php

namespace App\Services;

use App\Models\Subscription;
use App\Notifications\SubscriptionExpiryReminderNotification;
use App\Notifications\SubscriptionReceiptNotification;
use Illuminate\Support\Facades\Log;

final class SubscriptionEmailService
{
    public function sendReceipt(Subscription $subscription): void
    {
        $subscription->refresh()->load(['plan', 'user']);

        if ($subscription->receipt_email_sent_at) {
            return;
        }

        $user = $subscription->user;

        if (! $user) {
            return;
        }

        try {
            $user->notify(new SubscriptionReceiptNotification($subscription));
            $subscription->update(['receipt_email_sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Failed to send subscription receipt email', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendExpiryReminder(Subscription $subscription, int $daysRemaining): bool
    {
        if (! in_array($daysRemaining, [7, 1], true)) {
            return false;
        }

        $subscription->refresh()->load(['plan', 'user']);
        $user = $subscription->user;

        if (! $user || ! $user->subscription_reminder_emails_enabled) {
            return false;
        }

        $sentColumn = $daysRemaining === 7 ? 'expiry_reminder_7d_sent_at' : 'expiry_reminder_1d_sent_at';

        if ($subscription->{$sentColumn}) {
            return false;
        }

        try {
            $user->notify(new SubscriptionExpiryReminderNotification($subscription, $daysRemaining));
            $subscription->update([$sentColumn => now()]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to send subscription expiry reminder', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'days_remaining' => $daysRemaining,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
