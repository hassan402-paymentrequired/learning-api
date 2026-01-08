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
            ['name' => 'Mathematics', 'description' => 'Mathematics for JAMB and DLI', 'exam_types' => ['JAMB', 'DLI'], 'order' => 1],
            ['name' => 'English Language', 'description' => 'English Language and Literature', 'exam_types' => ['JAMB', 'DLI'], 'order' => 2],
            ['name' => 'Physics', 'description' => 'Physics for Science students', 'exam_types' => ['JAMB'], 'order' => 3],
            ['name' => 'Chemistry', 'description' => 'Chemistry for Science students', 'exam_types' => ['JAMB'], 'order' => 4],
            ['name' => 'Biology', 'description' => 'Biology for Science students', 'exam_types' => ['JAMB'], 'order' => 5],
            ['name' => 'Economics', 'description' => 'Economics for Social Science students', 'exam_types' => ['JAMB', 'DLI'], 'order' => 6],
            ['name' => 'Government', 'description' => 'Government and Political Science', 'exam_types' => ['JAMB', 'DLI'], 'order' => 7],
            ['name' => 'Literature in English', 'description' => 'Literature in English', 'exam_types' => ['JAMB'], 'order' => 8],
            ['name' => 'Geography', 'description' => 'Geography for Social Science students', 'exam_types' => ['JAMB'], 'order' => 9],
            ['name' => 'Commerce', 'description' => 'Commerce and Business Studies', 'exam_types' => ['JAMB', 'DLI'], 'order' => 10],
        ];

        foreach ($subjects as $subjectData) {
            Subject::firstOrCreate(
                ['name' => $subjectData['name']],
                [
                    'description' => $subjectData['description'],
                    'exam_types' => $subjectData['exam_types'],
                    'is_active' => true,
                    'order' => $subjectData['order'],
                ]
            );
        }
    }
}
