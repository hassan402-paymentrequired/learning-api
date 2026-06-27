<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PushSubscriptionController extends Controller
{
    public function vapidPublicKey(WebPushService $webPush)
    {
        if (! $webPush->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Push notifications are not configured.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'public_key' => $webPush->publicKey(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|string',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'timezone' => 'nullable|string|timezone:all',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();
        $timezone = $request->input('timezone', $user->timezone);

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $request->endpoint)],
            [
                'user_id' => $user->id,
                'endpoint' => $request->endpoint,
                'p256dh' => $request->input('keys.p256dh'),
                'auth' => $request->input('keys.auth'),
                'user_agent' => $request->userAgent(),
                'timezone' => $timezone,
            ]
        );

        $user->update([
            'push_notifications_enabled' => true,
            'timezone' => $timezone,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Push subscription saved.',
        ], 201);
    }

    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        PushSubscription::where('user_id', auth()->id())
            ->where('endpoint_hash', hash('sha256', $request->endpoint))
            ->delete();

        if (! PushSubscription::where('user_id', auth()->id())->exists()) {
            auth()->user()->update(['push_notifications_enabled' => false]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Push subscription removed.',
        ]);
    }
}
