<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends Controller
{
    /**
     * Send OTP for email verification.
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Email is already verified.',
            ], 400);
        }

        // Create OTP
        $otp = Otp::createForEmailVerification($user);

        // Send OTP via notification (queued)
        try {
            $user->notify(new EmailVerificationNotification($otp->otp));
        } catch (\Exception $e) {
            Log::error('Failed to queue OTP email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            // For development, return OTP in response
            if (config('app.debug')) {
                return response()->json([
                    'success' => true,
                    'message' => 'OTP sent (check logs in development)',
                    'data' => [
                        'otp' => $otp->otp, // Only in development
                        'expires_at' => $otp->expires_at,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to your email.',
            'data' => [
                'expires_at' => $otp->expires_at,
            ],
        ]);
    }

    /**
     * Verify OTP and activate account.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Email is already verified.',
            ], 400);
        }

        // Find valid OTP
        $otp = Otp::where('user_id', $user->id)
            ->where('email', $user->email)
            ->where('type', 'email_verification')
            ->where('otp', $request->otp)
            ->where('is_used', false)
            ->first();

        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code.',
            ], 400);
        }

        if ($otp->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Verification code has expired. Please request a new one.',
            ], 400);
        }

        // Mark OTP as used
        $otp->update(['is_used' => true]);

        // Verify user's email
        $user->update(['email_verified_at' => now()]);

        // Send welcome email after verification
        try {
            $user->notify(new WelcomeNotification());
        } catch (\Exception $e) {
            Log::warning('Failed to send welcome email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
            'data' => [
                'user' => $user->fresh(),
            ],
        ]);
    }

    /**
     * Resend OTP.
     */
    public function resendOtp(Request $request)
    {
        return $this->sendOtp($request);
    }
}
