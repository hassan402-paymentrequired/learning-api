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
        Schema::table('questions', function (Blueprint $table) {
            // JSON array: ['JAMB'], ['DLI'], or ['JAMB', 'DLI']
            $table->json('exam_types')->nullable()->after('exam_id');
        });
        
        // Set exam_types for existing questions based on their exam's exam_type
        // This will be done in a separate step to handle different database drivers
        $questions = DB::table('questions')
            ->join('exams', 'questions.exam_id', '=', 'exams.id')
            ->whereNull('questions.exam_types')
            ->select('questions.id', 'exams.exam_type')
            ->get();
        
        foreach ($questions as $question) {
            DB::table('questions')
                ->where('id', $question->id)
                ->update(['exam_types' => json_encode([$question->exam_type])]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('exam_types');
        });
    }
};
