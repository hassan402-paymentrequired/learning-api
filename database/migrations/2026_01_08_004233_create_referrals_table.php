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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->onDelete('cascade'); // User who referred
            $table->foreignId('referred_id')->constrained('users')->onDelete('cascade'); // User who was referred
            $table->foreignId('subscription_id')->nullable()->constrained()->onDelete('set null'); // Subscription created by referred user
            $table->decimal('referrer_reward_amount', 10, 2)->default(0); // 10% of subscription
            $table->decimal('referred_discount_amount', 10, 2)->default(0); // 5% discount
            $table->string('status')->default('pending'); // pending (signup), rewarded (after subscription)
            $table->timestamp('rewarded_at')->nullable(); // When referral reward was given
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('referred_id'); // A user can only be referred once
            $table->index('referrer_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
