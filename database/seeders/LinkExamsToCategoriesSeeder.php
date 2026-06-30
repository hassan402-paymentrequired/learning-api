<?php

namespace Database\Seeders;

use App\Services\ExamCategoryResolver;
use Illuminate\Database\Seeder;

class LinkExamsToCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $resolver = app(ExamCategoryResolver::class);
        $resolver->syncAllExams();
    }
}
