<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('push_notifications_enabled')->default(true);
            $table->string('morning_reminder_time', 5)->default('07:00');
            $table->string('timezone')->default('Africa/Lagos');
            $table->date('last_morning_push_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'push_notifications_enabled',
                'morning_reminder_time',
                'timezone',
                'last_morning_push_date',
            ]);
        });
    }
};
