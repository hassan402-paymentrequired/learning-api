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
                'slug' => 'JAMB',
                'description' => 'Practice with past questions and mock exams',
                'icon_name' => 'school',
                'flow_type' => 'standard',
                'is_active' => true,
            ],
            [
                'name' => 'DLI Practice',
                'slug' => 'DLI',
                'description' => 'Practice with DLI-specific questions',
                'icon_name' => 'menu-book',
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
