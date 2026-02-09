<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create single yearly subscription plan
        SubscriptionPlan::firstOrCreate(
            [
                'slug' => 'yearly-plan',
            ],
            [
                'name' => 'Yearly Plan',
                'description' => 'Full access to all exam questions, practice tests, and past questions for 1 year.',
                'price' => 2000.00, // 2000 Naira
                'interval' => 'year',
                'interval_count' => 1,
                'currency' => 'NGN',
                'is_active' => true,
                'trial_days' => 0,
            ]
        );
    }
}
