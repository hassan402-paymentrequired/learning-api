<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('subscription_reminder_emails_enabled')->default(true)->after('last_morning_push_date');
            $table->boolean('marketing_emails_enabled')->default(false)->after('subscription_reminder_emails_enabled');
            $table->date('last_push_notification_date')->nullable()->after('marketing_emails_enabled');
        });

        DB::table('users')
            ->whereNotNull('last_morning_push_date')
            ->update(['last_push_notification_date' => DB::raw('last_morning_push_date')]);

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('expiry_reminder_7d_sent_at')->nullable()->after('expires_at');
            $table->timestamp('expiry_reminder_1d_sent_at')->nullable()->after('expiry_reminder_7d_sent_at');
            $table->timestamp('receipt_email_sent_at')->nullable()->after('expiry_reminder_1d_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'expiry_reminder_7d_sent_at',
                'expiry_reminder_1d_sent_at',
                'receipt_email_sent_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_reminder_emails_enabled',
                'marketing_emails_enabled',
                'last_push_notification_date',
            ]);
        });
    }
};
