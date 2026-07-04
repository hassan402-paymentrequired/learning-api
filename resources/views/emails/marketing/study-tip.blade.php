@extends('emails.layouts.base')

@section('title', 'Study tip — ' . config('app.name'))
@section('preheader', $tip['title'] ?? 'A quick tip to help you study smarter.')

@section('content')
@php
    $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
    $ctaUrl = $frontendUrl . ($tip['cta_path'] ?? '/dashboard');
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom: 8px;">
            <p style="margin: 0 0 6px; font-size: 13px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #71717a;">
                Study tip
            </p>
            <h1 style="margin: 0; font-size: 28px; line-height: 36px; font-weight: 700; letter-spacing: -0.03em; color: #18181b;">
                {{ $tip['title'] }}
            </h1>
        </td>
    </tr>
    <tr>
        <td style="padding: 20px 0 28px;">
            <p style="margin: 0 0 14px; font-size: 16px; line-height: 26px; color: #52525b;">
                Hi {{ $user->name ?? 'there' }},
            </p>
            <p style="margin: 0; font-size: 16px; line-height: 26px; color: #52525b;">
                {{ $tip['body'] }}
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 28px;">
            @include('emails.partials.button', [
                'url' => $ctaUrl,
                'label' => $tip['cta_label'] ?? 'Open ' . config('app.name'),
            ])
        </td>
    </tr>
    <tr>
        <td>
            @component('emails.partials.callout', ['type' => 'info'])
                <strong style="color: #18181b;">Keep it up.</strong> Small, consistent sessions are how top scorers prepare.
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
