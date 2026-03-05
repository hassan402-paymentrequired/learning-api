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
        Schema::create('subscription_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // The intended recipient
            $table->foreignId('generated_by')->constrained('users')->onDelete('cascade'); // Admin who generated
            $table->string('pin', 6)->unique(); // 6-digit PIN
            $table->enum('status', ['unused', 'used', 'cancelled'])->default('unused');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Optional pin-level expiry
            $table->text('notes')->nullable(); // Admin memo
            $table->timestamps();

            $table->index('user_id');
            $table->index('pin');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_pins');
    }
};
