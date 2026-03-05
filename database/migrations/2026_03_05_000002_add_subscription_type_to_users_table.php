<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // How the subscription was activated: paystack (online payment), pin (admin PIN), manual (admin override)
            $table->enum('subscription_type', ['paystack', 'pin', 'manual'])
                  ->default('paystack')
                  ->after('subscription_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('subscription_type');
        });
    }
};
