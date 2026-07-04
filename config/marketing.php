<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Marketing email frequency
    |--------------------------------------------------------------------------
    |
    | Minimum days between any marketing email to the same user.
    |
    */
    'min_days_between_emails' => (int) env('MARKETING_MIN_DAYS_BETWEEN', 7),

    /*
    |--------------------------------------------------------------------------
    | Re-engagement settings
    |--------------------------------------------------------------------------
    */
    'inactive_days' => (int) env('MARKETING_INACTIVE_DAYS', 14),
    'reengagement_min_days_between' => (int) env('MARKETING_REENGAGEMENT_MIN_DAYS', 30),
    'min_account_age_days' => (int) env('MARKETING_MIN_ACCOUNT_AGE_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Weekly study tips (rotated by ISO week number)
    |--------------------------------------------------------------------------
    */
    'study_tips' => [
        [
            'title' => 'Practice in short bursts',
            'body' => 'Twenty focused minutes beats three hours of distracted reading. Pick one subject, set a timer, and work through a small set of questions without switching tabs.',
            'cta_label' => 'Start a quick session',
            'cta_path' => '/dashboard',
        ],
        [
            'title' => 'Review wrong answers the same day',
            'body' => 'When you miss a question, read the explanation immediately while the mistake is still fresh. That same-day review is one of the fastest ways to improve.',
            'cta_label' => 'Review your last attempt',
            'cta_path' => '/dashboard',
        ],
        [
            'title' => 'Build a streak, not a marathon',
            'body' => 'Consistent daily practice — even 10 questions — compounds over weeks. Enable morning reminders in your profile if you want a gentle nudge.',
            'cta_label' => 'Keep your streak going',
            'cta_path' => '/dashboard',
        ],
        [
            'title' => 'Simulate exam conditions',
            'body' => 'Once a week, run a timed session with no pauses. It trains pace and pressure-handling, not just knowledge.',
            'cta_label' => 'Try a timed practice',
            'cta_path' => '/dashboard',
        ],
        [
            'title' => 'Focus on weak subjects first',
            'body' => 'Check your analytics to see which subjects pull your score down. Spend your first session each week on the weakest area — gains there move the needle fastest.',
            'cta_label' => 'View your progress',
            'cta_path' => '/dashboard',
        ],
    ],
];
