@extends('emails.layouts.base')

@section('title', 'Reset your password — ' . config('app.name'))
@section('preheader', 'Use code ' . $otp . ' to reset your password.')

@section('content')
@php
    $expiresMinutes = (int) config('auth.password_reset_expires_in', 15);
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom: 8px;">
            <p style="margin: 0 0 6px; font-size: 13px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #71717a;">
                Password reset
            </p>
            <h1 style="margin: 0; font-size: 28px; line-height: 36px; font-weight: 700; letter-spacing: -0.03em; color: #18181b;">
                Reset your password
            </h1>
        </td>
    </tr>
    <tr>
        <td style="padding: 20px 0 8px;">
            <p style="margin: 0 0 14px; font-size: 16px; line-height: 26px; color: #52525b;">
                Hi {{ $user->name ?? 'there' }},
            </p>
            <p style="margin: 0; font-size: 16px; line-height: 26px; color: #52525b; text-align: center;">
                We received a request to reset your password. Use the code below on the reset page.
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding: 8px 0 24px;">
            @include('emails.partials.otp', [
                'code' => $otp,
                'expires' => $expiresMinutes . ' ' . \Illuminate\Support\Str::plural('minute', $expiresMinutes),
            ])
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 28px;">
            @include('emails.partials.button', [
                'url' => rtrim(config('app.frontend_url', config('app.url')), '/') . '/authenticate/reset-password',
                'label' => 'Continue to reset',
            ])
        </td>
    </tr>
    <tr>
        <td>
            @component('emails.partials.callout', ['type' => 'warning'])
                If you didn't request a password reset, ignore this email — your password won't change unless you use this code.
            @endcomponent
        </td>
    </tr>
</table>
@endsection
