<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExamCategory;

class ExamCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'JAMB Practice',
                'slug' => 'jamb',
                'description' => 'Practice with past questions and mock exams',
                'flow_type' => 'standard',
                'is_active' => true,
            ],
            [
                'name' => 'UNILAG DLI',
                'slug' => 'unilag-dli',
                'description' => 'Practice with DLI-specific questions',
                'flow_type' => 'departmental',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            ExamCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
