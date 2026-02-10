<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Binds subscription to the client IP used when subscribing (one IP/device per subscription).
     * Column stores the client IP address, not a localStorage device ID.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('subscription_device_id', 45)->nullable()->after('subscription_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('subscription_device_id');
        });
    }
};
