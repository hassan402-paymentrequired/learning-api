<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Set yearly subscription plan price to ₦2,000.
     */
    public function up(): void
    {
        DB::table('subscription_plans')
            ->where('slug', 'yearly-plan')
            ->update(['price' => 2000.00]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally restore previous price if needed
        DB::table('subscription_plans')
            ->where('slug', 'yearly-plan')
            ->update(['price' => 5000.00]);
    }
};
