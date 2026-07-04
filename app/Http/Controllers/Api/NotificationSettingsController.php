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
            'data' => $this->settingsPayload($user),
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'push_notifications_enabled' => 'sometimes|boolean',
            'morning_reminder_time' => 'sometimes|date_format:H:i',
            'timezone' => 'sometimes|string|timezone:all',
            'subscription_reminder_emails_enabled' => 'sometimes|boolean',
            'marketing_emails_enabled' => 'sometimes|boolean',
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

        if ($request->has('subscription_reminder_emails_enabled')) {
            $user->subscription_reminder_emails_enabled = $request->boolean('subscription_reminder_emails_enabled');
        }

        if ($request->has('marketing_emails_enabled')) {
            $user->marketing_emails_enabled = $request->boolean('marketing_emails_enabled');
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Notification settings updated.',
            'data' => $this->settingsPayload($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload($user): array
    {
        return [
            'push_notifications_enabled' => (bool) $user->push_notifications_enabled,
            'morning_reminder_time' => $user->morning_reminder_time ?? '07:00',
            'timezone' => $user->timezone ?? 'Africa/Lagos',
            'has_push_subscription' => $user->pushSubscriptions()->exists(),
            'subscription_reminder_emails_enabled' => (bool) ($user->subscription_reminder_emails_enabled ?? true),
            'marketing_emails_enabled' => (bool) ($user->marketing_emails_enabled ?? false),
        ];
    }
}
