<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_marketing_email_sent_at')->nullable()->after('marketing_emails_enabled');
            $table->timestamp('last_reengagement_email_sent_at')->nullable()->after('last_marketing_email_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'last_marketing_email_sent_at',
                'last_reengagement_email_sent_at',
            ]);
        });
    }
};
