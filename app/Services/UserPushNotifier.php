<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserPushNotifier
{
    public function __construct(private WebPushService $webPush)
    {
    }

    /**
     * @param  array{title: string, body: string, url?: string}  $payload
     */
    public function send(User $user, array $payload): bool
    {
        if (!$user->push_notifications_enabled || !$this->webPush->isConfigured()) {
            return false;
        }

        $user->loadMissing('pushSubscriptions');

        if ($user->pushSubscriptions->isEmpty()) {
            return false;
        }

        $delivered = false;

        foreach ($user->pushSubscriptions as $subscription) {
            if ($this->webPush->send($subscription, $payload)) {
                $delivered = true;
            }
        }

        if (!$delivered) {
            Log::warning('Push notification not delivered', ['user_id' => $user->id]);
        }

        return $delivered;
    }
}
