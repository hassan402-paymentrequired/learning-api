<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MarketingEmailService;
use Illuminate\Console\Command;

class SendMarketingStudyTips extends Command
{
    protected $signature = 'marketing:send-study-tips';

    protected $description = 'Send weekly study tip emails to opted-in active users';

    public function handle(MarketingEmailService $marketingEmail): int
    {
        if ($marketingEmail->currentStudyTip() === null) {
            $this->warn('No study tips configured. Skipping.');

            return self::SUCCESS;
        }

        $sent = 0;

        User::query()
            ->where('marketing_emails_enabled', true)
            ->whereNotNull('email_verified_at')
            ->chunkById(100, function ($users) use ($marketingEmail, &$sent) {
                foreach ($users as $user) {
                    if ($marketingEmail->sendStudyTip($user)) {
                        $sent++;
                    }
                }
            });

        $this->info("Study tip emails sent to {$sent} user(s).");

        return self::SUCCESS;
    }
}
