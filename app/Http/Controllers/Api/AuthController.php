<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PublicId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'referral_code' => 'nullable|string|exists:users,referral_code',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check referral code if provided
        $referredBy = null;
        if ($request->has('referral_code') && $request->referral_code) {
            $referrer = User::where('referral_code', $request->referral_code)->first();
            if ($referrer) {
                $referredBy = $referrer->id;
            }
        }

        try {

            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'referred_by' => $referredBy,
                'email_verified_at' => null, // Email not verified yet
            ]);

            // Generate referral code for new user
            $user->generateReferralCode();

            // Create referral record if user was referred
            if ($referredBy) {
                \App\Models\Referral::create([
                    'referrer_id' => $referredBy,
                    'referred_id' => $user->id,
                    'status' => 'pending', // Will be changed to 'rewarded' when user subscribes
                ]);
            }

            // Generate and send OTP for email verification
            $otp = \App\Models\Otp::createForEmailVerification($user);

            // Send OTP via notification (queued)
            $user->notify(new \App\Notifications\EmailVerificationNotification($otp->otp));

            $token = JWTAuth::fromUser($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully. Please verify your email.',
                'data' => [
                    'user' => PublicId::user($user),
                    'token' => $token,
                    'token_type' => 'bearer',
                    'email_verified' => false,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Failed to register user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to register user',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Login user and create token.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        if (! $token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password',
            ], 401);
        }

        $user = JWTAuth::user();

        // Allow login even if email is not verified (user will be redirected to verification page)
        return response()->json([
            'success' => true,
            'message' => $user->email_verified_at ? 'Login successful' : 'Login successful. Please verify your email.',
            'data' => [
                'user' => PublicId::user($user),
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60, // in seconds
                'email_verified' => !empty($user->email_verified_at),
            ],
        ]);
    }

    /**
     * Get the authenticated user.
     */
    public function me()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => PublicId::user(JWTAuth::user()),
            ],
        ]);
    }

    /**
     * Logout the user (Invalidate the token).
     */
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Refresh a token.
     */
    public function refresh()
    {
        $token = JWTAuth::refresh(JWTAuth::getToken());

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
            ],
        ]);
    }
}
