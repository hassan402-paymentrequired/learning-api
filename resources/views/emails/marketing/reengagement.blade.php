@extends('emails.layouts.base')

@section('title', 'Come back to ' . config('app.name'))
@section('preheader', 'Your practice streak is waiting. Jump back in with a quick session.')

@section('content')
@php
    $dashboardUrl = rtrim(config('app.frontend_url', config('app.url')), '/') . '/dashboard';
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom: 8px;">
            <p style="margin: 0 0 6px; font-size: 13px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #71717a;">
                We miss you
            </p>
            <h1 style="margin: 0; font-size: 28px; line-height: 36px; font-weight: 700; letter-spacing: -0.03em; color: #18181b;">
                Ready for a quick session?
            </h1>
        </td>
    </tr>
    <tr>
        <td style="padding: 20px 0 28px;">
            <p style="margin: 0 0 14px; font-size: 16px; line-height: 26px; color: #52525b;">
                Hi {{ $user->name ?? 'there' }}, it has been a while since your last practice on {{ config('app.name') }}.
            </p>
            <p style="margin: 0; font-size: 16px; line-height: 26px; color: #52525b;">
                Even one short session today gets you back in rhythm. Your past progress is still there — pick up where you left off.
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 28px;">
            @include('emails.partials.button', [
                'url' => $dashboardUrl,
                'label' => 'Continue practicing',
            ])
        </td>
    </tr>
    <tr>
        <td>
            @component('emails.partials.callout', ['type' => 'info'])
                <strong style="color: #18181b;">No pressure.</strong> We'll only send occasional reminders like this while you're opted in.
            @endcomponent
        </td>
    </tr>
    <tr>
        <td>
            @include('emails.partials.marketing-unsubscribe', [
                'unsubscribeUrl' => $unsubscribeUrl,
                'preferencesUrl' => $preferencesUrl,
            ])
        </td>
    </tr>
</table>
@endsection
