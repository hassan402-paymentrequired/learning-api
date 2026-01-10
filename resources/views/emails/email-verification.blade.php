@component('mail::message')
# Email Verification

Hello {{ $user->name }},

Please use the following code to verify your email address:

## Verification Code: {{ $otp }}

This code will expire in 10 minutes.

If you did not create an account, please ignore this email.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
