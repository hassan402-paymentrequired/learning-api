@component('mail::message')
# Password Reset

Hello,

You requested to reset your password. Please use the following code:

## Reset Code: {{ $otp }}

This code will expire in 15 minutes.

If you did not request a password reset, please ignore this email.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
