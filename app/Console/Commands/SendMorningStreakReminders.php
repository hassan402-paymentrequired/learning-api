<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserStreak;
use App\Services\PushNotificationBudget;
use App\Services\WebPushService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendMorningStreakReminders extends Command
{
    protected $signature = 'streak:send-morning-reminders';

    protected $description = 'Send morning push reminders to users who have not practiced today';

    public function handle(WebPushService $webPush, PushNotificationBudget $pushBudget): int
    {
        if (! $webPush->isConfigured()) {
            $this->warn('VAPID keys not configured. Skipping morning reminders.');

            return self::SUCCESS;
        }

        $sent = 0;

        User::query()
            ->where('push_notifications_enabled', true)
            ->whereHas('pushSubscriptions')
            ->with('pushSubscriptions')
            ->chunkById(100, function ($users) use ($webPush, $pushBudget, &$sent) {
                foreach ($users as $user) {
                    if ($this->sendReminderIfDue($user, $webPush, $pushBudget)) {
                        $sent++;
                    }
                }
            });

        $this->info("Morning streak reminders sent to {$sent} user(s).");

        return self::SUCCESS;
    }

    private function sendReminderIfDue(User $user, WebPushService $webPush, PushNotificationBudget $pushBudget): bool
    {
        $timezone = $user->timezone ?: 'Africa/Lagos';
        $now = Carbon::now($timezone);
        $today = $now->toDateString();

        [$reminderHour] = array_pad(explode(':', $user->morning_reminder_time ?? '07:00'), 2, '00');

        if ((int) $now->format('G') !== (int) $reminderHour) {
            return false;
        }

        if ($pushBudget->hasSentToday($user, $timezone)) {
            return false;
        }

        $practicedToday = UserStreak::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->exists();

        if ($practicedToday) {
            return false;
        }

        $currentStreak = UserStreak::where('user_id', $user->id)
            ->orderByDesc('date')
            ->limit(30)
            ->pluck('date')
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->toArray();

        $streakCount = $this->estimateCurrentStreak($currentStreak, $today, $timezone);

        $greeting = (int) $now->format('G') < 12 ? 'Good morning' : 'Hello';
        $body = $streakCount > 0
            ? "You're on a {$streakCount}-day streak. Practice today to keep it going!"
            : 'Start your day with a quick practice session.';

        $payload = [
            'title' => "{$greeting} — keep your streak!",
            'body' => $body,
            'url' => '/dashboard',
        ];

        $delivered = false;

        foreach ($user->pushSubscriptions as $subscription) {
            if ($webPush->send($subscription, $payload)) {
                $delivered = true;
            }
        }

        if ($delivered) {
            $pushBudget->recordSent($user, $timezone);
        } else {
            Log::warning('Morning reminder not delivered', ['user_id' => $user->id]);
        }

        return $delivered;
    }

  /**
   * @param  array<int, string>  $streakDates
   */
    private function estimateCurrentStreak(array $streakDates, string $today, string $timezone): int
    {
        if (empty($streakDates)) {
            return 0;
        }

        $yesterday = Carbon::parse($today, $timezone)->subDay()->format('Y-m-d');

        if (! in_array($today, $streakDates, true) && ! in_array($yesterday, $streakDates, true)) {
            return 0;
        }

        $start = in_array($today, $streakDates, true) ? $today : $yesterday;
        $count = 0;
        $cursor = Carbon::parse($start, $timezone);

        while (in_array($cursor->format('Y-m-d'), $streakDates, true)) {
            $count++;
            $cursor->subDay();
        }

        return $count;
    }
}
