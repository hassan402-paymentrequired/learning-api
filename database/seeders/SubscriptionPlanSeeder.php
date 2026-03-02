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
        // Create or update yearly subscription plan (₦2,000)
        SubscriptionPlan::updateOrCreate(
            [
                'slug' => 'yearly-plan',
            ],
            [
                'name' => 'Yearly Plan',
                'description' => 'Full access to all exam questions, practice tests, and past questions for 1 year.',
                'price' => 2500.00,
                'interval' => 'year',
                'interval_count' => 1,
                'currency' => 'NGN',
                'is_active' => true,
                'trial_days' => 0,
            ]
        );
    }
}
