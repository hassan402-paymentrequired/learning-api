<?php

return [
    'reward_amount' => (float) env('REFERRAL_REWARD_AMOUNT', 1000),
    'min_withdrawal_amount' => (float) env('REFERRAL_MIN_WITHDRAWAL', 1000),
    // Kept for backwards compatibility — subscription discounts are disabled (always 0).
    'referred_discount_percent' => 0,
];
