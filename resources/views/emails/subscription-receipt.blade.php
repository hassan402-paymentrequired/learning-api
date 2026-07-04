@extends('emails.layouts.base')

@section('title', 'Subscription receipt — ' . config('app.name'))
@section('preheader', 'Payment confirmed. Your subscription is now active.')

@section('content')
@php
    $planName = $subscription->plan?->name ?? 'Annual plan';
    $expiresAt = $subscription->expires_at?->timezone($user->timezone ?? 'Africa/Lagos');
    $dashboardUrl = rtrim(config('app.frontend_url', config('app.url')), '/') . '/dashboard';
    $amountPaid = number_format((float) $subscription->amount_paid, 2);
    $currency = $subscription->plan?->currency ?? 'NGN';
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom: 8px;">
            <p style="margin: 0 0 6px; font-size: 13px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #71717a;">
                Payment confirmed
            </p>
            <h1 style="margin: 0; font-size: 28px; line-height: 36px; font-weight: 700; letter-spacing: -0.03em; color: #18181b;">
                You're subscribed
            </h1>
        </td>
    </tr>
    <tr>
        <td style="padding: 20px 0 28px;">
            <p style="margin: 0 0 14px; font-size: 16px; line-height: 26px; color: #52525b;">
                Hi {{ $user->name ?? 'there' }}, thanks for subscribing to {{ config('app.name') }}. Your account now has full access to premium practice.
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 28px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden;">
                <tr>
                    <td style="padding: 16px 20px; background-color: #fafafa; border-bottom: 1px solid #e4e4e7; font-size: 13px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.06em;">
                        Receipt summary
                    </td>
                </tr>
                <tr>
                    <td style="padding: 16px 20px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td style="padding: 6px 0; font-size: 14px; color: #71717a;">Plan</td>
                                <td align="right" style="padding: 6px 0; font-size: 14px; font-weight: 600; color: #18181b;">{{ $planName }}</td>
                            </tr>
                            @if((float) $subscription->amount_paid > 0)
                            <tr>
                                <td style="padding: 6px 0; font-size: 14px; color: #71717a;">Amount paid</td>
                                <td align="right" style="padding: 6px 0; font-size: 14px; font-weight: 600; color: #18181b;">{{ $currency }} {{ $amountPaid }}</td>
                            </tr>
                            @endif
                            @if($expiresAt)
                            <tr>
                                <td style="padding: 6px 0; font-size: 14px; color: #71717a;">Valid until</td>
                                <td align="right" style="padding: 6px 0; font-size: 14px; font-weight: 600; color: #18181b;">{{ $expiresAt->format('j F Y') }}</td>
                            </tr>
                            @endif
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 28px;">
            @include('emails.partials.button', [
                'url' => $dashboardUrl,
                'label' => 'Start practicing',
            ])
        </td>
    </tr>
    <tr>
        <td>
            @component('emails.partials.callout', ['type' => 'success'])
                <strong style="color: #18181b;">You're all set.</strong> This email is your confirmation — no further action needed.
            @endcomponent
        </td>
    </tr>
</table>
@endsection
