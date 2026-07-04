<?php

namespace App\Services;

use App\Models\Otp;
use Carbon\Carbon;

final class OtpRateLimiter
{
    public const MAX_ATTEMPTS_PER_HOUR = 3;

    public const WINDOW_MINUTES = 60;

    public function tooManyAttempts(string $email, string $type): bool
    {
        return $this->attemptCount($email, $type) >= self::MAX_ATTEMPTS_PER_HOUR;
    }

    public function attemptCount(string $email, string $type): int
    {
        return Otp::query()
            ->where('email', $email)
            ->where('type', $type)
            ->where('created_at', '>=', Carbon::now()->subMinutes(self::WINDOW_MINUTES))
            ->count();
    }

    public function retryAfterSeconds(string $email, string $type): int
    {
        $oldestInWindow = Otp::query()
            ->where('email', $email)
            ->where('type', $type)
            ->where('created_at', '>=', Carbon::now()->subMinutes(self::WINDOW_MINUTES))
            ->orderBy('created_at')
            ->value('created_at');

        if (! $oldestInWindow) {
            return 0;
        }

        $retryAt = Carbon::parse($oldestInWindow)->addMinutes(self::WINDOW_MINUTES);

        return max(0, (int) now()->diffInSeconds($retryAt, false));
    }
}
