@extends('emails.layouts.base')

@section('title', 'Verify your email — ' . config('app.name'))
@section('preheader', 'Your verification code is ' . $otp . '. It expires in 10 minutes.')

@section('content')
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom: 8px;">
            <p style="margin: 0 0 6px; font-size: 13px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #71717a;">
                Email verification
            </p>
            <h1 style="margin: 0; font-size: 28px; line-height: 36px; font-weight: 700; letter-spacing: -0.03em; color: #18181b;">
                Confirm it's really you
            </h1>
        </td>
    </tr>
    <tr>
        <td style="padding: 20px 0 8px;">
            <p style="margin: 0; font-size: 16px; line-height: 26px; color: #52525b; text-align: center;">
                Enter this code to verify your email address and finish setting up your account.
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding: 8px 0 24px;">
            @include('emails.partials.otp', [
                'code' => $otp,
                'expires' => '10 minutes',
            ])
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 28px;">
            @component('emails.partials.callout', ['type' => 'warning'])
                If you didn't create a {{ config('app.name') }} account, you can safely ignore this email. Someone may have entered your address by mistake.
            @endcomponent
        </td>
    </tr>
    <tr>
        <td align="center" style="padding-top: 4px;">
            <p style="margin: 0 0 16px; font-size: 14px; line-height: 22px; color: #71717a;">
                Didn't request this? Secure your account:
            </p>
            @include('emails.partials.button', [
                'url' => rtrim(config('app.frontend_url', config('app.url')), '/') . '/authenticate/reset-password',
                'label' => 'Reset password',
            ])
        </td>
    </tr>
</table>
@endsection
