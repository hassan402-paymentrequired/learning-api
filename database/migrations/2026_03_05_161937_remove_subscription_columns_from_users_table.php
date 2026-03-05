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
            $table->dropColumn([
                'subscription_status',
                'subscription_type',
                'subscription_expires_at',
                'subscription_device_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('subscription_status')->default('inactive');
            $table->string('subscription_type')->default('paystack');
            $table->timestamp('subscription_expires_at')->nullable();
            $table->string('subscription_device_id')->nullable();
        });
    }
};
