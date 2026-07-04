@extends('emails.layouts.base')

@section('title', 'Subscription expiry reminder — ' . config('app.name'))
@section('preheader', $daysRemaining === 1
    ? 'Your subscription expires tomorrow. Renew to keep practicing.'
    : 'Your subscription expires in 7 days. Renew anytime to avoid interruption.')

@section('content')
@php
    $planName = $subscription->plan?->name ?? 'Annual plan';
    $expiresAt = $subscription->expires_at?->timezone($user->timezone ?? 'Africa/Lagos');
    $renewUrl = rtrim(config('app.frontend_url', config('app.url')), '/') . '/subscription';
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom: 8px;">
            <p style="margin: 0 0 6px; font-size: 13px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #71717a;">
                Subscription reminder
            </p>
            <h1 style="margin: 0; font-size: 28px; line-height: 36px; font-weight: 700; letter-spacing: -0.03em; color: #18181b;">
                @if($daysRemaining === 1)
                    Expires tomorrow
                @else
                    Expires in {{ $daysRemaining }} days
                @endif
            </h1>
        </td>
    </tr>
    <tr>
        <td style="padding: 20px 0 28px;">
            <p style="margin: 0 0 14px; font-size: 16px; line-height: 26px; color: #52525b;">
                Hi {{ $user->name ?? 'there' }}, your {{ config('app.name') }} <strong style="color: #18181b;">{{ $planName }}</strong> subscription
                @if($expiresAt)
                    ends on <strong style="color: #18181b;">{{ $expiresAt->format('l, j F Y') }}</strong>.
                @else
                    is coming to an end soon.
                @endif
            </p>
            <p style="margin: 0; font-size: 16px; line-height: 26px; color: #52525b;">
                Renew now to keep access to practice questions, streak tracking, and your progress history without interruption.
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 28px;">
            @include('emails.partials.button', [
                'url' => $renewUrl,
                'label' => 'Renew subscription',
            ])
        </td>
    </tr>
    <tr>
        <td>
            @component('emails.partials.callout', ['type' => $daysRemaining === 1 ? 'warning' : 'info'])
                @if($daysRemaining === 1)
                    <strong style="color: #18181b;">Last day:</strong> After expiry you will lose access to premium practice until you renew.
                @else
                    <strong style="color: #18181b;">No action needed yet</strong> — this is a friendly heads-up. You can renew anytime before expiry.
                @endif
            @endcomponent
        </td>
    </tr>
</table>
@endsection
