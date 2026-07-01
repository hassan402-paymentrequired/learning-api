<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class SubscriptionDeviceResolver
{
    /**
     * Resolve a device ID for an active subscription that has not been bound yet.
     *
     * @return array{device_id: string, source: string}|null
     */
    public function resolve(Subscription $subscription): ?array
    {
        $user = $subscription->user ?? User::find($subscription->user_id);

        if (!$user) {
            return null;
        }

        if ($legacy = $this->fromLegacyUserColumn($user)) {
            return $legacy;
        }

        if ($fromReference = $this->fromPaystackReference($subscription)) {
            return $fromReference;
        }

        if ($fromPendingReference = $this->fromPendingPaymentRecord($subscription)) {
            return $fromPendingReference;
        }

        if ($fromSibling = $this->fromUserSubscriptionHistory($subscription)) {
            return $fromSibling;
        }

        if ($fromPending = $this->fromClosestPendingSubscription($subscription)) {
            return $fromPending;
        }

        if ($fromPractice = $this->fromLastPremiumPracticeAttempt($subscription)) {
            return $fromPractice;
        }

        return null;
    }

    /**
     * Whether the user has premium practice activity after this subscription started.
     * Used only for reporting; attempts do not store device IDs historically.
     */
    public function hasPremiumPracticeSinceSubscription(Subscription $subscription): bool
    {
        if (!$subscription->starts_at) {
            return false;
        }

        return ExamAttempt::query()
            ->where('user_id', $subscription->user_id)
            ->where('started_at', '>=', $subscription->starts_at)
            ->orderByDesc('started_at')
            ->limit(50)
            ->get()
            ->contains(fn (ExamAttempt $attempt) => $this->attemptUsedPremiumQuota($attempt));
    }

    private function attemptUsedPremiumQuota(ExamAttempt $attempt): bool
    {
        if ($attempt->total_questions > 5) {
            return true;
        }

        foreach ($attempt->subjects ?? [] as $subject) {
            if (($subject['question_count'] ?? 0) > 5) {
                return true;
            }
        }

        return false;
    }

    private function fromLegacyUserColumn(User $user): ?array
    {
        if (!Schema::hasColumn('users', 'subscription_device_id')) {
            return null;
        }

        $deviceId = $user->subscription_device_id ?? null;

        if (empty($deviceId)) {
            return null;
        }

        return [
            'device_id' => $deviceId,
            'source' => 'legacy_users.subscription_device_id',
        ];
    }

    private function fromPaystackReference(Subscription $subscription): ?array
    {
        if (empty($subscription->paystack_reference)) {
            return null;
        }

        $match = Subscription::query()
            ->where('paystack_reference', $subscription->paystack_reference)
            ->where('id', '!=', $subscription->id)
            ->whereNotNull('device_id')
            ->orderByDesc('updated_at')
            ->value('device_id');

        if (empty($match)) {
            return null;
        }

        return [
            'device_id' => $match,
            'source' => 'paystack_reference_match',
        ];
    }

    private function fromPendingPaymentRecord(Subscription $subscription): ?array
    {
        if (empty($subscription->paystack_reference)) {
            return null;
        }

        $match = Subscription::query()
            ->where('user_id', $subscription->user_id)
            ->where('paystack_reference', $subscription->paystack_reference)
            ->where('status', 'pending')
            ->whereNotNull('device_id')
            ->orderByDesc('updated_at')
            ->value('device_id');

        if (empty($match)) {
            return null;
        }

        return [
            'device_id' => $match,
            'source' => 'pending_payment_record',
        ];
    }

    private function fromUserSubscriptionHistory(Subscription $subscription): ?array
    {
        $match = Subscription::query()
            ->where('user_id', $subscription->user_id)
            ->where('id', '!=', $subscription->id)
            ->whereNotNull('device_id')
            ->orderByDesc('updated_at')
            ->value('device_id');

        if (empty($match)) {
            return null;
        }

        return [
            'device_id' => $match,
            'source' => 'user_subscription_history',
        ];
    }

    private function fromClosestPendingSubscription(Subscription $subscription): ?array
    {
        $query = Subscription::query()
            ->where('user_id', $subscription->user_id)
            ->where('id', '!=', $subscription->id)
            ->where('status', 'pending')
            ->whereNotNull('device_id');

        if ($subscription->starts_at) {
            $query->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, created_at, ?))', [$subscription->starts_at]);
        } else {
            $query->orderByDesc('created_at');
        }

        $match = $query->value('device_id');

        if (empty($match)) {
            return null;
        }

        return [
            'device_id' => $match,
            'source' => 'closest_pending_subscription',
        ];
    }

    private function fromLastPremiumPracticeAttempt(Subscription $subscription): ?array
    {
        if (!$subscription->starts_at || !Schema::hasColumn('exam_attempts', 'device_id')) {
            return null;
        }

        $attempts = ExamAttempt::query()
            ->where('user_id', $subscription->user_id)
            ->where('started_at', '>=', $subscription->starts_at)
            ->whereNotNull('device_id')
            ->orderByDesc('started_at')
            ->limit(50)
            ->get();

        $attempt = $attempts->first(fn (ExamAttempt $attempt) => $this->attemptUsedPremiumQuota($attempt));

        if (!$attempt?->device_id) {
            return null;
        }

        return [
            'device_id' => $attempt->device_id,
            'source' => 'last_premium_practice_attempt',
        ];
    }
}
