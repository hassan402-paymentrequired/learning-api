<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->string('exam_type', 64)->nullable()->after('exam_id')->index();
        });

        // Backfill from linked exams so typed leaderboard filters include past formal attempts.
        if (Schema::hasTable('exams')) {
            DB::table('exam_attempts')
                ->whereNotNull('exam_id')
                ->whereNull('exam_type')
                ->orderBy('id')
                ->chunkById(200, function ($attempts) {
                    foreach ($attempts as $attempt) {
                        $examType = DB::table('exams')->where('id', $attempt->exam_id)->value('exam_type');
                        if ($examType) {
                            DB::table('exam_attempts')
                                ->where('id', $attempt->id)
                                ->update(['exam_type' => $examType]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn('exam_type');
        });
    }
};
