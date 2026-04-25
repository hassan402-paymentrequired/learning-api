<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LinkQuestionsToExamCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = \App\Models\ExamCategory::all()->keyBy('slug');
        $questions = \Illuminate\Support\Facades\DB::table('questions')->whereNotNull('exam_types')->get();
        
        $pivotData = [];
        
        foreach ($questions as $question) {
            $types = json_decode($question->exam_types, true);
            if (is_array($types)) {
                foreach ($types as $type) {
                    if (isset($categories[$type])) {
                        $categoryId = $categories[$type]->id;
                        $pivotData[] = [
                            'exam_category_id' => $categoryId,
                            'question_id' => $question->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }
        }
        
        // Insert in chunks to avoid memory issues
        foreach (array_chunk($pivotData, 1000) as $chunk) {
            \Illuminate\Support\Facades\DB::table('exam_category_question')->insertOrIgnore($chunk);
        }
    }
}
