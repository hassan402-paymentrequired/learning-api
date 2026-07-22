<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\ReferralWithdrawal;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\ReferralRewardEarnedNotification;
use App\Notifications\ReferralWithdrawalRequestedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ReferralService
{
    public function __construct(private UserPushNotifier $pushNotifier)
    {
    }

    public function rewardAmount(): float
    {
        return (float) config('referral.reward_amount', 1000);
    }

    public function minWithdrawalAmount(): float
    {
        return (float) config('referral.min_withdrawal_amount', 1000);
    }

    public function totalEarnings(User $user): float
    {
        return (float) Referral::query()
            ->where('referrer_id', $user->id)
            ->where('status', 'rewarded')
            ->sum('referrer_reward_amount');
    }

    public function reservedWithdrawalTotal(User $user): float
    {
        return (float) ReferralWithdrawal::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'paid'])
            ->sum('amount');
    }

    public function availableBalance(User $user): float
    {
        return max(0, $this->totalEarnings($user) - $this->reservedWithdrawalTotal($user));
    }

    public function frontendBaseUrl(): string
    {
        return rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
    }

    public function referralUrl(User $user): string
    {
        return $this->frontendBaseUrl() . '/authenticate/register?ref=' . $user->referral_code;
    }

    /**
     * Award ₦1,000 to referrer when a referred user activates a subscription.
     */
    public function rewardOnSubscription(User $referredUser, Subscription $subscription): void
    {
        $referral = Referral::query()
            ->where('referred_id', $referredUser->id)
            ->where('status', 'pending')
            ->first();

        if (!$referral) {
            return;
        }

        $rewardAmount = $this->rewardAmount();

        $referral->update([
            'subscription_id' => $subscription->id,
            'referrer_reward_amount' => $rewardAmount,
            'status' => 'rewarded',
            'rewarded_at' => now(),
        ]);

        Log::info('Referral rewarded', [
            'referrer_id' => $referral->referrer_id,
            'referred_id' => $referredUser->id,
            'subscription_id' => $subscription->id,
            'amount' => $rewardAmount,
        ]);

        $referrer = User::find($referral->referrer_id);

        if ($referrer) {
            $referrer->notify(new ReferralRewardEarnedNotification($referral->fresh(), $rewardAmount));

            $this->pushNotifier->send($referrer, [
                'title' => 'Referral reward earned',
                'body' => 'You earned ₦' . number_format($rewardAmount, 0) . ' because someone you referred subscribed.',
                'url' => '/referral',
            ]);
        }
    }

    public function ensureReferralCode(User $user): User
    {
        if (!$user->referral_code) {
            $user->generateReferralCode();
            $user->refresh();
        }

        return $user;
    }

    /**
     * @return array{success: bool, message: string, withdrawal?: ReferralWithdrawal}
     */
    public function requestWithdrawal(
        User $user,
        float $amount,
        string $accountName,
        string $accountNumber,
        string $bankName
    ): array {
        $minAmount = $this->minWithdrawalAmount();
        $available = $this->availableBalance($user);

        if ($amount < $minAmount) {
            return [
                'success' => false,
                'message' => "Minimum withdrawal amount is ₦" . number_format($minAmount, 0) . '.',
            ];
        }

        if ($amount > $available) {
            return [
                'success' => false,
                'message' => 'Insufficient balance for this withdrawal.',
            ];
        }

        $hasPending = ReferralWithdrawal::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return [
                'success' => false,
                'message' => 'You already have a pending withdrawal request. Please wait for it to be processed.',
            ];
        }

        $withdrawal = DB::transaction(function () use ($user, $amount, $accountName, $accountNumber, $bankName) {
            return ReferralWithdrawal::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'account_name' => $accountName,
                'account_number' => $accountNumber,
                'bank_name' => $bankName,
                // Legacy airtime columns kept for older rows compatibility.
                'phone_number' => '',
                'network' => '',
                'status' => 'pending',
            ]);
        });

        $this->notifyAdminsOfWithdrawal($withdrawal->load('user'));

        return [
            'success' => true,
            'message' => 'Withdrawal request submitted successfully.',
            'withdrawal' => $withdrawal,
        ];
    }

    public function notifyAdminsOfWithdrawal(ReferralWithdrawal $withdrawal): void
    {
        $admins = User::query()->where('is_admin', true)->get();

        if ($admins->isEmpty()) {
            Log::warning('No admin users found to notify about referral withdrawal', [
                'withdrawal_id' => $withdrawal->id,
            ]);

            return;
        }

        Notification::send($admins, new ReferralWithdrawalRequestedNotification($withdrawal));
    }
}
