<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevicePushToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DevicePushTokenController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:255',
            'platform' => 'nullable|string|in:ios,android',
            'timezone' => 'nullable|string|timezone:all',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! DevicePushToken::isValidExpoToken($request->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Expo push token.',
            ], 422);
        }

        $user = auth()->user();
        $timezone = $request->input('timezone', $user->timezone);

        DevicePushToken::updateOrCreate(
            ['token_hash' => hash('sha256', $request->token)],
            [
                'user_id' => $user->id,
                'token' => $request->token,
                'platform' => $request->input('platform'),
                'timezone' => $timezone,
            ]
        );

        $user->update([
            'push_notifications_enabled' => true,
            'timezone' => $timezone ?: $user->timezone,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Device push token saved.',
        ], 201);
    }

    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DevicePushToken::where('user_id', auth()->id())
            ->where('token_hash', hash('sha256', $request->token))
            ->delete();

        $user = auth()->user();
        $hasEndpoints = $user->pushSubscriptions()->exists()
            || $user->devicePushTokens()->exists();

        if (! $hasEndpoints) {
            $user->update(['push_notifications_enabled' => false]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Device push token removed.',
        ]);
    }
}
