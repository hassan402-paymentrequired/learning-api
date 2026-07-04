<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use App\Services\OtpRateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PasswordResetController extends Controller
{
    /**
     * Send OTP for password reset.
     */
    public function sendOtp(Request $request, OtpRateLimiter $rateLimiter)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        if ($rateLimiter->tooManyAttempts($request->email, 'password_reset')) {
            return response()->json([
                'success' => false,
                'message' => 'Too many reset codes requested. Please try again later.',
                'data' => [
                    'retry_after_seconds' => $rateLimiter->retryAfterSeconds($request->email, 'password_reset'),
                ],
            ], 429);
        }

        $user = User::where('email', $request->email)->first();

        // Create OTP
        $otp = Otp::createForPasswordReset($request->email);

        if ($user) {
            // Send OTP via notification (queued)
            try {
                $user->notify(new PasswordResetNotification($otp->otp));
            } catch (\Exception $e) {
                Log::error('Failed to queue password reset OTP email', [
                    'email' => $request->email,
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
                    'message' => 'Failed to send reset code. Please try again.',
                ], 500);
            }
        } else {
            // Still return success for security (don't reveal if email exists)
            // But log it for debugging
            Log::warning('Password reset requested for non-existent email', [
                'email' => $request->email,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset code sent to your email.',
            'data' => [
                'expires_at' => $otp->expires_at,
            ],
        ]);
    }

    /**
     * Verify OTP for password reset.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6',
        ]);

        // Find valid OTP
        $otp = Otp::where('email', $request->email)
            ->where('type', 'password_reset')
            ->where('otp', $request->otp)
            ->where('is_used', false)
            ->first();

        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset code.',
            ], 400);
        }

        if ($otp->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Reset code has expired. Please request a new one.',
            ], 400);
        }

        // Mark OTP as used
        $otp->update(['is_used' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Reset code verified. You can now reset your password.',
            'data' => [
                'verified' => true,
            ],
        ]);
    }

    /**
     * Reset password after OTP verification.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. You can now login with your new password.',
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
