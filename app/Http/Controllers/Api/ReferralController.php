<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralWithdrawal;
use App\Services\ReferralService;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(private ReferralService $referralService)
    {
    }

    /**
     * Get user's referral code, balance, and statistics.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $user = $this->referralService->ensureReferralCode($user);

        $totalReferrals = Referral::where('referrer_id', $user->id)->count();
        $activeReferrals = Referral::where('referrer_id', $user->id)
            ->where('status', 'rewarded')
            ->count();
        $pendingReferrals = Referral::where('referrer_id', $user->id)
            ->where('status', 'pending')
            ->count();
        $totalRewards = $this->referralService->totalEarnings($user);
        $availableBalance = $this->referralService->availableBalance($user);

        $recentReferrals = Referral::where('referrer_id', $user->id)
            ->with('referred:id,name,email,created_at')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($referral) {
                return [
                    'uuid' => $referral->uuid,
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

        $recentWithdrawals = ReferralWithdrawal::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (ReferralWithdrawal $withdrawal) => $this->formatWithdrawal($withdrawal));

        return response()->json([
            'success' => true,
            'data' => [
                'referral_code' => $user->referral_code,
                'referral_url' => $this->referralService->referralUrl($user),
                'credit_balance' => $availableBalance,
                'total_earnings' => $totalRewards,
                'min_withdrawal_amount' => $this->referralService->minWithdrawalAmount(),
                'reward_amount' => $this->referralService->rewardAmount(),
                'statistics' => [
                    'total_referrals' => $totalReferrals,
                    'active_referrals' => $activeReferrals,
                    'pending_referrals' => $pendingReferrals,
                    'total_rewards' => $totalRewards,
                ],
                'recent_referrals' => $recentReferrals,
                'recent_withdrawals' => $recentWithdrawals,
            ],
        ]);
    }

    public function code(Request $request)
    {
        $user = $this->referralService->ensureReferralCode(auth()->user());

        return response()->json([
            'success' => true,
            'data' => [
                'referral_code' => $user->referral_code,
                'referral_url' => $this->referralService->referralUrl($user),
            ],
        ]);
    }

    public function balance(Request $request)
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => [
                'credit_balance' => $this->referralService->availableBalance($user),
                'total_earnings' => $this->referralService->totalEarnings($user),
                'min_withdrawal_amount' => $this->referralService->minWithdrawalAmount(),
            ],
        ]);
    }

    public function withdrawals(Request $request)
    {
        $withdrawals = ReferralWithdrawal::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => [
                'withdrawals' => $withdrawals->through(fn (ReferralWithdrawal $w) => $this->formatWithdrawal($w)),
                'pagination' => [
                    'current_page' => $withdrawals->currentPage(),
                    'last_page' => $withdrawals->lastPage(),
                    'total' => $withdrawals->total(),
                ],
            ],
        ]);
    }

    public function withdraw(Request $request)
    {
        $minAmount = $this->referralService->minWithdrawalAmount();

        $request->validate([
            'phone_number' => 'required|string|min:10|max:20',
            'network' => 'required|string|in:mtn,airtel,glo,9mobile',
            'amount' => 'required|numeric|min:' . $minAmount,
        ]);

        $result = $this->referralService->requestWithdrawal(
            auth()->user(),
            (float) $request->input('amount'),
            $request->input('phone_number'),
            $request->input('network')
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'withdrawal' => $this->formatWithdrawal($result['withdrawal']),
            ],
        ], 201);
    }

    private function formatWithdrawal(ReferralWithdrawal $withdrawal): array
    {
        return [
            'uuid' => $withdrawal->uuid,
            'amount' => (float) $withdrawal->amount,
            'phone_number' => $withdrawal->phone_number,
            'network' => $withdrawal->network,
            'status' => $withdrawal->status,
            'admin_notes' => $withdrawal->admin_notes,
            'processed_at' => $withdrawal->processed_at,
            'created_at' => $withdrawal->created_at,
        ];
    }
}
