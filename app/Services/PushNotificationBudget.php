<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

final class PushNotificationBudget
{
    /**
     * All push notification types share a single daily budget per user.
     */
    public function hasSentToday(User $user, ?string $timezone = null): bool
    {
        $timezone = $timezone ?? $user->timezone ?? 'Africa/Lagos';
        $today = Carbon::now($timezone)->toDateString();

        $lastDate = $user->last_push_notification_date ?? $user->last_morning_push_date;

        return $lastDate?->format('Y-m-d') === $today;
    }

    public function recordSent(User $user, ?string $timezone = null): void
    {
        $timezone = $timezone ?? $user->timezone ?? 'Africa/Lagos';
        $today = Carbon::now($timezone)->toDateString();

        $user->update([
            'last_push_notification_date' => $today,
            'last_morning_push_date' => $today,
        ]);
    }
}
