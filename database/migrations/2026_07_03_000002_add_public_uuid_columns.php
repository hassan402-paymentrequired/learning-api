<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Public UUIDs for client-facing identifiers. Internal bigint PKs remain for joins/FKs.
     *
     * @var array<int, string>
     */
    private array $tables = [
        'users',
        'exam_categories',
        'departments',
        'subjects',
        'subject_tests',
        'exams',
        'questions',
        'answers',
        'exam_attempts',
        'announcements',
        'subscription_plans',
        'subscriptions',
        'referrals',
        'user_streaks',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });

            DB::table($table)
                ->whereNull('uuid')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update(['uuid' => (string) Str::uuid()]);
                    }
                }, 'id');

            Schema::table($table, function (Blueprint $table) {
                $table->uuid('uuid')->nullable(false)->change();
                $table->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique(['uuid']);
                $table->dropColumn('uuid');
            });
        }
    }
};
