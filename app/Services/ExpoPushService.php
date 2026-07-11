<?php

namespace App\Services;

use App\Models\DevicePushToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    private const PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * @param  array{title: string, body: string, url?: string}  $payload
     */
    public function send(DevicePushToken $deviceToken, array $payload): bool
    {
        if (! DevicePushToken::isValidExpoToken($deviceToken->token)) {
            Log::warning('Invalid Expo push token removed', [
                'device_push_token_id' => $deviceToken->id,
            ]);
            $deviceToken->delete();

            return false;
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(15)
                ->post(self::PUSH_URL, [
                    [
                        'to' => $deviceToken->token,
                        'sound' => 'default',
                        'title' => $payload['title'],
                        'body' => $payload['body'],
                        'data' => [
                            'url' => $payload['url'] ?? '/',
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Expo push HTTP failure', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $ticket = $response->json('data.0') ?? $response->json('data');

            if (is_array($ticket) && ($ticket['status'] ?? null) === 'ok') {
                return true;
            }

            $error = is_array($ticket) ? ($ticket['details']['error'] ?? $ticket['message'] ?? 'unknown') : 'unknown';

            if (in_array($error, ['DeviceNotRegistered', 'InvalidCredentials'], true)) {
                $deviceToken->delete();
            }

            Log::warning('Expo push ticket error', [
                'token_id' => $deviceToken->id,
                'error' => $error,
                'ticket' => $ticket,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('Expo push exception', [
                'token_id' => $deviceToken->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
