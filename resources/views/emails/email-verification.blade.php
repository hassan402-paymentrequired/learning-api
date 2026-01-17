@extends('emails.app')

@section('title', 'Email Verification')

@section('header')
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <div style="width: 2.5rem; height: 1px; background-color: #fff;"></div>
        <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 24 24" height="20" width="20"
            xmlns="http://www.w3.org/2000/svg">
            <path fill="none" d="M0 0h24v24H0V0z"></path>
            <path
                d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z">
            </path>
        </svg>
        <div style="width: 2.5rem; height: 1px; background-color: #fff;"></div>
    </div>
    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
        <div style="text-align: center; font-size: 14px; font-weight: normal;">
            THANKS FOR SIGNING UP!
        </div>
        <div class="" style="font-size: 24px; font-weight: bold; text-transform: capitalize; text-align: center">
            Verify your E-mail Address
        </div>
    </div>
@endsection

@section('content')
    <h4 style="color: #374151;">Hello {{ $user->name }},</h4>
    <p style="line-height: 1.5; color: #4b5563;">
        Please use the following One Time Password(OTP)
    </p>
    <div style="display: flex; align-items: center; margin-top: 1rem; gap: 20px;">
        @php
            $otpDigits = str_split((string) $otp);
        @endphp
        @foreach ($otpDigits as $digit)
            <p class="border otpbox" style="">
                {{ $digit }}
            </p>
        @endforeach
    </div>
    <p style="margin-top: 1rem; line-height: 1.75; color: #4b5563;">
        This passcode will only be valid for the next
        <span style="font-weight: bold;">{{ config('auth.otp_expires_in', 10) }} minutes</span>. If the passcode
        does not work, you can use this login verification link:
    </p>
    <button
        style="padding-left: 1.25rem; padding-right: 1.25rem; padding-top: 0.5rem; padding-bottom: 0.5rem; margin-top: 1.5rem; font-size: 14px; font-weight: bold; text-transform: capitalize; background-color: #f97316; color: #fff; transition-property: background-color; transition-duration: 300ms; transform: none; border-radius: 0.375rem; border-width: 1px; border: none; outline: none; cursor: pointer;">
        Verify email
    </button>
    <p style="margin-top: 2rem; color: #4b5563;">
        Thank you, <br />
        {{ config('app.name') }}
    </p>
@endsection
