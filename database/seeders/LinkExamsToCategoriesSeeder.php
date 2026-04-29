<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Exam;
use App\Models\ExamCategory;
use Illuminate\Support\Facades\DB;

class LinkExamsToCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = ExamCategory::all()->keyBy('slug');
        $exams = Exam::whereNotNull('exam_type')->get();
        
        $pivotData = [];
        
        foreach ($exams as $exam) {
            $type = strtolower($exam->exam_type);
            
            // Check if exam_type matches any category slug or name
            foreach ($categories as $slug => $category) {
                if ($type === strtolower($slug) || $type === strtolower($category->name)) {
                    $pivotData[] = [
                        'exam_category_id' => $category->id,
                        'exam_id' => $exam->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }
        
        if (!empty($pivotData)) {
            DB::table('exam_category_exam')->insertOrIgnore($pivotData);
        }
    }
}
