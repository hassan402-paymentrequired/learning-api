<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MarketingEmailService;
use Illuminate\Http\Request;

class MarketingUnsubscribeController extends Controller
{
    public function __invoke(Request $request, MarketingEmailService $marketingEmail)
    {
        if (! $request->hasValidSignature()) {
            return response('This unsubscribe link is invalid or has expired.', 403)
                ->header('Content-Type', 'text/plain');
        }

        $user = User::where('uuid', $request->query('user'))->first();

        if (! $user) {
            return response('User not found.', 404)
                ->header('Content-Type', 'text/plain');
        }

        $marketingEmail->unsubscribe($user);

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        return response(
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Unsubscribed</title></head><body style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;padding:40px 20px;text-align:center;color:#18181b;"><h1 style="font-size:24px;margin-bottom:12px;">You\'re unsubscribed</h1><p style="color:#52525b;line-height:1.6;max-width:420px;margin:0 auto 24px;">You will no longer receive product updates or study tips from ' . e(config('app.name')) . '. Account and security emails will still be sent when needed.</p><p><a href="' . e($frontendUrl . '/profile') . '" style="color:#18181b;">Manage email preferences</a></p></body></html>',
            200
        )->header('Content-Type', 'text/html');
    }
}
