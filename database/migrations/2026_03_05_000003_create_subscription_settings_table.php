<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscription_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Seed default settings
        DB::table('subscription_settings')->insert([
            [
                'key'         => 'default_subscription_days',
                'value'       => '365',
                'description' => 'Default number of days a subscription lasts when activated via PIN or manually',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'global_expiry_date',
                'value'       => null,
                'description' => 'If set, all subscriptions will expire on this date regardless of individual expiry',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_settings');
    }
};
