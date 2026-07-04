<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\MarketingReengagementNotification;
use App\Notifications\MarketingStudyTipNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

final class MarketingEmailService
{
    public function wantsMarketingEmails(User $user): bool
    {
        return (bool) $user->marketing_emails_enabled
            && $user->email_verified_at !== null;
    }

    public function canSendMarketingEmail(User $user): bool
    {
        if (! $this->wantsMarketingEmails($user)) {
            return false;
        }

        $minDays = config('marketing.min_days_between_emails', 7);

        if (! $user->last_marketing_email_sent_at) {
            return true;
        }

        return $user->last_marketing_email_sent_at->lte(now()->subDays($minDays));
    }

    public function practicedRecently(User $user, ?int $days = null): bool
    {
        $days = $days ?? config('marketing.inactive_days', 14);
        $since = now()->subDays($days);

        $hasStreak = $user->streaks()
            ->where('date', '>=', $since->toDateString())
            ->exists();

        if ($hasStreak) {
            return true;
        }

        return $user->examAttempts()
            ->where('started_at', '>=', $since)
            ->exists();
    }

    public function isEligibleForReengagement(User $user): bool
    {
        if (! $this->canSendMarketingEmail($user)) {
            return false;
        }

        $minAccountAge = config('marketing.min_account_age_days', 14);

        if ($user->created_at->gt(now()->subDays($minAccountAge))) {
            return false;
        }

        if ($this->practicedRecently($user)) {
            return false;
        }

        $reengagementMinDays = config('marketing.reengagement_min_days_between', 30);

        if (
            $user->last_reengagement_email_sent_at
            && $user->last_reengagement_email_sent_at->gt(now()->subDays($reengagementMinDays))
        ) {
            return false;
        }

        return true;
    }

    public function isEligibleForStudyTip(User $user): bool
    {
        if (! $this->canSendMarketingEmail($user)) {
            return false;
        }

        return $this->practicedRecently($user);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentStudyTip(): ?array
    {
        $tips = config('marketing.study_tips', []);

        if ($tips === []) {
            return null;
        }

        $index = ((int) now()->format('W')) % count($tips);

        return $tips[$index];
    }

    public function unsubscribeUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'marketing.unsubscribe',
            now()->addMonths(6),
            ['user' => $user->uuid]
        );
    }

    public function profilePreferencesUrl(): string
    {
        return rtrim(config('app.frontend_url', config('app.url')), '/') . '/profile';
    }

    public function sendStudyTip(User $user): bool
    {
        $tip = $this->currentStudyTip();

        if (! $tip || ! $this->isEligibleForStudyTip($user)) {
            return false;
        }

        try {
            $user->notify(new MarketingStudyTipNotification(
                $tip,
                $this->unsubscribeUrl($user),
                $this->profilePreferencesUrl()
            ));

            $this->recordMarketingSent($user);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to send marketing study tip email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendReengagement(User $user): bool
    {
        if (! $this->isEligibleForReengagement($user)) {
            return false;
        }

        try {
            $user->notify(new MarketingReengagementNotification(
                $this->unsubscribeUrl($user),
                $this->profilePreferencesUrl()
            ));

            $this->recordMarketingSent($user);
            $user->update(['last_reengagement_email_sent_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to send marketing reengagement email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function unsubscribe(User $user): void
    {
        $user->update(['marketing_emails_enabled' => false]);
    }

    private function recordMarketingSent(User $user): void
    {
        $user->update(['last_marketing_email_sent_at' => now()]);
    }
}
