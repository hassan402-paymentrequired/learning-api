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
            $table->string('subscription_status')->default('inactive')->after('email_verified_at'); // active, inactive, cancelled, expired
            $table->string('referral_code')->unique()->nullable()->after('subscription_status');
            $table->foreignId('referred_by')->nullable()->constrained('users')->onDelete('set null')->after('referral_code');
            $table->string('paystack_customer_code')->nullable()->after('referred_by');
            $table->timestamp('subscription_expires_at')->nullable()->after('paystack_customer_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by']);
            $table->dropColumn([
                'subscription_status',
                'referral_code',
                'referred_by',
                'paystack_customer_code',
                'subscription_expires_at',
            ]);
        });
    }
};
