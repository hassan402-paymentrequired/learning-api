<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subjects = [
            ['name' => 'Mathematics', 'description' => 'Mathematics for JAMB and DLI', 'order' => 1],
            ['name' => 'English Language', 'description' => 'English Language and Literature', 'order' => 2],
            ['name' => 'Physics', 'description' => 'Physics for Science students', 'order' => 3],
            ['name' => 'Chemistry', 'description' => 'Chemistry for Science students', 'order' => 4],
            ['name' => 'Biology', 'description' => 'Biology for Science students', 'order' => 5],
            ['name' => 'Economics', 'description' => 'Economics for Social Science students', 'order' => 6],
            ['name' => 'Government', 'description' => 'Government and Political Science', 'order' => 7],
            ['name' => 'Literature in English', 'description' => 'Literature in English', 'order' => 8],
            ['name' => 'Geography', 'description' => 'Geography for Social Science students', 'order' => 9],
            ['name' => 'Commerce', 'description' => 'Commerce and Business Studies', 'order' => 10],
        ];

        foreach ($subjects as $subject) {
            Subject::firstOrCreate(
                ['name' => $subject['name']],
                [
                    'description' => $subject['description'],
                    'is_active' => true,
                    'order' => $subject['order'],
                ]
            );
        }
    }
}
