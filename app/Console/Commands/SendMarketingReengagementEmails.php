<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MarketingEmailService;
use Illuminate\Console\Command;

class SendMarketingReengagementEmails extends Command
{
    protected $signature = 'marketing:send-reengagement';

    protected $description = 'Send re-engagement emails to opted-in inactive users';

    public function handle(MarketingEmailService $marketingEmail): int
    {
        $sent = 0;

        User::query()
            ->where('marketing_emails_enabled', true)
            ->whereNotNull('email_verified_at')
            ->where('created_at', '<=', now()->subDays(config('marketing.min_account_age_days', 14)))
            ->chunkById(100, function ($users) use ($marketingEmail, &$sent) {
                foreach ($users as $user) {
                    if ($marketingEmail->sendReengagement($user)) {
                        $sent++;
                    }
                }
            });

        $this->info("Re-engagement emails sent to {$sent} user(s).");

        return self::SUCCESS;
    }
}
