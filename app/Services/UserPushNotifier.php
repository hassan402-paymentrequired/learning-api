<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserPushNotifier
{
    public function __construct(
        private WebPushService $webPush,
        private ExpoPushService $expoPush,
    ) {
    }

    /**
     * @param  array{title: string, body: string, url?: string}  $payload
     */
    public function send(User $user, array $payload): bool
    {
        if (! $user->push_notifications_enabled) {
            return false;
        }

        $user->loadMissing(['pushSubscriptions', 'devicePushTokens']);

        $delivered = false;

        if ($this->webPush->isConfigured()) {
            foreach ($user->pushSubscriptions as $subscription) {
                if ($this->webPush->send($subscription, $payload)) {
                    $delivered = true;
                }
            }
        }

        foreach ($user->devicePushTokens as $deviceToken) {
            if ($this->expoPush->send($deviceToken, $payload)) {
                $delivered = true;
            }
        }

        if (! $delivered) {
            Log::warning('Push notification not delivered', ['user_id' => $user->id]);
        }

        return $delivered;
    }

    public function hasAnyEndpoint(User $user): bool
    {
        return $user->pushSubscriptions()->exists()
            || $user->devicePushTokens()->exists();
    }
}
