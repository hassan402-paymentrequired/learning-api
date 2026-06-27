<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function isConfigured(): bool
    {
        return ! empty(config('services.webpush.public_key'))
            && ! empty(config('services.webpush.private_key'));
    }

    public function publicKey(): ?string
    {
        return config('services.webpush.public_key');
    }

    /**
     * @param  array{title: string, body: string, url?: string}  $payload
     */
    public function send(PushSubscription $subscription, array $payload): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('Web push skipped: VAPID keys not configured.');

            return false;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ]);

        $pushSubscription = Subscription::create([
            'endpoint' => $subscription->endpoint,
            'keys' => [
                'p256dh' => $subscription->p256dh,
                'auth' => $subscription->auth,
            ],
        ]);

        $report = $webPush->sendOneNotification(
            $pushSubscription,
            json_encode($payload)
        );

        if ($report->isSuccess()) {
            return true;
        }

        $reason = $report->getReason();

        if ($report->isSubscriptionExpired()) {
            $subscription->delete();
        }

        Log::warning('Web push failed', [
            'endpoint' => $subscription->endpoint,
            'reason' => $reason,
        ]);

        return false;
    }
}
