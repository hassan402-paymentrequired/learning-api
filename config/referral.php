<?php

return [
    'reward_amount' => (float) env('REFERRAL_REWARD_AMOUNT', 500),
    'min_withdrawal_amount' => (float) env('REFERRAL_MIN_WITHDRAWAL', 1000),
    'referred_discount_percent' => (float) env('REFERRAL_DISCOUNT_PERCENT', 5),
];
