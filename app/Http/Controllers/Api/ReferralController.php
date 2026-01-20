<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    /**
     * Get user's referral code and statistics.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Generate referral code if user doesn't have one
        if (!$user->referral_code) {
            $user->generateReferralCode();
            $user->refresh();
        }

        // Get referral statistics
        $totalReferrals = Referral::where('referrer_id', $user->id)->count();
        $activeReferrals = Referral::where('referrer_id', $user->id)
            ->where('status', 'rewarded')
            ->count();
        $pendingReferrals = Referral::where('referrer_id', $user->id)
            ->where('status', 'pending')
            ->count();
        $totalRewards = Referral::where('referrer_id', $user->id)
            ->where('status', 'rewarded')
            ->sum('referrer_reward_amount');

        // Get recent referrals
        $recentReferrals = Referral::where('referrer_id', $user->id)
            ->with('referred:id,name,email,created_at')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($referral) {
                return [
                    'id' => $referral->id,
                    'referred_user' => [
                        'name' => $referral->referred->name,
                        'email' => $referral->referred->email,
                        'signed_up_at' => $referral->referred->created_at,
                    ],
                    'status' => $referral->status,
                    'reward_amount' => (float) $referral->referrer_reward_amount,
                    'rewarded_at' => $referral->rewarded_at,
                    'created_at' => $referral->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'referral_code' => $user->referral_code,
                'referral_url' => 'Share your code: ' . $user->referral_code,
                'statistics' => [
                    'total_referrals' => $totalReferrals,
                    'active_referrals' => $activeReferrals,
                    'pending_referrals' => $pendingReferrals,
                    'total_rewards' => (float) $totalRewards,
                ],
                'recent_referrals' => $recentReferrals,
            ],
        ]);
    }

    /**
     * Get referral code for sharing.
     */
    public function code(Request $request)
    {
        $user = auth()->user();

        // Generate referral code if user doesn't have one
        if (!$user->referral_code) {
            $user->generateReferralCode();
            $user->refresh();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'referral_code' => $user->referral_code,
                'referral_url' => 'Share your code: ' . $user->referral_code,
            ],
        ]);
    }

    /**
     * Get user's credit balance from referrals.
     */
    public function balance(Request $request)
    {
        $user = auth()->user();

        // Calculate total credit balance from rewarded referrals
        $creditBalance = Referral::where('referrer_id', $user->id)
            ->where('status', 'rewarded')
            ->sum('referrer_reward_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'credit_balance' => (float) $creditBalance,
            ],
        ]);
    }
}
