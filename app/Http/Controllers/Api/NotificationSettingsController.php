<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationSettingsController extends Controller
{
    public function show()
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => [
                'push_notifications_enabled' => (bool) $user->push_notifications_enabled,
                'morning_reminder_time' => $user->morning_reminder_time ?? '07:00',
                'timezone' => $user->timezone ?? 'Africa/Lagos',
                'has_push_subscription' => $user->pushSubscriptions()->exists(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'push_notifications_enabled' => 'sometimes|boolean',
            'morning_reminder_time' => 'sometimes|date_format:H:i',
            'timezone' => 'sometimes|string|timezone:all',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();

        if ($request->has('push_notifications_enabled')) {
            $user->push_notifications_enabled = $request->boolean('push_notifications_enabled');

            if (! $user->push_notifications_enabled) {
                $user->pushSubscriptions()->delete();
            }
        }

        if ($request->has('morning_reminder_time')) {
            $user->morning_reminder_time = $request->morning_reminder_time;
        }

        if ($request->has('timezone')) {
            $user->timezone = $request->timezone;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Notification settings updated.',
            'data' => [
                'push_notifications_enabled' => (bool) $user->push_notifications_enabled,
                'morning_reminder_time' => $user->morning_reminder_time,
                'timezone' => $user->timezone,
                'has_push_subscription' => $user->pushSubscriptions()->exists(),
            ],
        ]);
    }
}
