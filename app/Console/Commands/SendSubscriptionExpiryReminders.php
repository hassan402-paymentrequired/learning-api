<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\SubscriptionEmailService;
use Illuminate\Console\Command;

class SendSubscriptionExpiryReminders extends Command
{
    protected $signature = 'subscriptions:send-expiry-reminders';

    protected $description = 'Send subscription expiry reminder emails (7 days and 1 day before)';

    public function handle(SubscriptionEmailService $emailService): int
    {
        $sent7 = $this->sendRemindersForDays(7, $emailService);
        $sent1 = $this->sendRemindersForDays(1, $emailService);

        $this->info("Sent {$sent7} seven-day reminder(s) and {$sent1} one-day reminder(s).");

        return self::SUCCESS;
    }

    private function sendRemindersForDays(int $days, SubscriptionEmailService $emailService): int
    {
        $targetDate = now()->addDays($days)->toDateString();
        $sentColumn = $days === 7 ? 'expiry_reminder_7d_sent_at' : 'expiry_reminder_1d_sent_at';
        $sent = 0;

        Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', $targetDate)
            ->whereNull($sentColumn)
            ->with(['plan', 'user'])
            ->chunkById(100, function ($subscriptions) use ($emailService, $days, &$sent) {
                foreach ($subscriptions as $subscription) {
                    if ($emailService->sendExpiryReminder($subscription, $days)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }
}
