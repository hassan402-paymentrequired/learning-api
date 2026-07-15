<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_withdrawals', function (Blueprint $table) {
            $table->string('account_name')->nullable()->after('amount');
            $table->string('account_number', 20)->nullable()->after('account_name');
            $table->string('bank_name')->nullable()->after('account_number');
        });
    }

    public function down(): void
    {
        Schema::table('referral_withdrawals', function (Blueprint $table) {
            $table->dropColumn(['account_name', 'account_number', 'bank_name']);
        });
    }
};
