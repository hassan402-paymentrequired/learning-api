<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_exam', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['question_id', 'exam_id']);
        });

        DB::table('questions')
            ->whereNotNull('exam_id')
            ->orderBy('id')
            ->chunk(500, function ($questions) {
                $rows = [];

                foreach ($questions as $question) {
                    $rows[] = [
                        'question_id' => $question->id,
                        'exam_id' => $question->exam_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (!empty($rows)) {
                    DB::table('question_exam')->insertOrIgnore($rows);
                }
            });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['exam_id']);
            $table->dropColumn('exam_id');
        });

        // Refresh cached totals on all exams
        DB::table('exams')->orderBy('id')->chunk(100, function ($exams) {
            foreach ($exams as $exam) {
                $count = DB::table('question_exam')
                    ->where('exam_id', $exam->id)
                    ->count();

                DB::table('exams')
                    ->where('id', $exam->id)
                    ->update(['total_questions' => $count]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('exam_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Restore a single exam_id per question (first linked exam only)
        DB::table('question_exam')
            ->orderBy('question_id')
            ->orderBy('exam_id')
            ->get()
            ->groupBy('question_id')
            ->each(function ($links, $questionId) {
                $firstExamId = $links->first()->exam_id;

                DB::table('questions')
                    ->where('id', $questionId)
                    ->update(['exam_id' => $firstExamId]);
            });

        Schema::dropIfExists('question_exam');
    }
};
