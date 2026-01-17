@extends('emails.app')

@section('title', 'Password Reset')

@section('header')
  <div style="display: flex; align-items: center; gap: 0.75rem;">
    <div style="width: 2.5rem; height: 1px; background-color: #fff;"></div>
    <svg
      stroke="currentColor"
      fill="currentColor"
      stroke-width="0"
      viewBox="0 0 24 24"
      height="20"
      width="20"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path fill="none" d="M0 0h24v24H0V0z"></path>
      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path>
    </svg>
    <div style="width: 2.5rem; height: 1px; background-color: #fff;"></div>
  </div>
  <div style="display: flex; flex-direction: column; gap: 1.25rem;">
    <div style="text-align: center; font-size: 14px; font-weight: normal;">
      PASSWORD RESET REQUEST
    </div>
    <div
      class=""
      style="font-size: 24px; font-weight: bold; text-transform: capitalize; text-align: center"
    >
      Reset Your Password
    </div>
  </div>
@endsection

@section('content')
  <h4 style="color: #374151;">Hello {{ $user->name }},</h4>
  <p style="line-height: 1.5; color: #4b5563;">
    You requested to reset your password. Please use the following One Time Password(OTP):
  </p>
  <div style="display: flex; align-items: center; margin-top: 1rem; gap: 20px;">
    @php
      $otpDigits = str_split((string)$otp);
    @endphp
    @foreach($otpDigits as $digit)
      <p class="border otpbox" style="">
        {{ $digit }}
      </p>
    @endforeach
  </div>
  <p style="margin-top: 1rem; line-height: 1.75; color: #4b5563;">
    This passcode will only be valid for the next
    <span style="font-weight: bold;">{{ config('auth.password_reset_expires_in', 15) }} minutes</span>. If you did not request a password reset, please ignore this email.
  </p>
  <p style="margin-top: 2rem; color: #4b5563;">
    Thank you, <br />
    {{ config('app.name') }}
  </p>
@endsection
