<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Small question set for local testing (JAMB practice + one past paper).
 */
class SampleQuestionsSeeder extends Seeder
{
    private const PRACTICE_SUBJECTS = ['Mathematics', 'English Language'];

    private const PRACTICE_COUNT = 5;

    public function run(): void
    {
        $this->seedPracticeQuestions();
        $this->seedPastExam();
    }

    private function seedPracticeQuestions(): void
    {
        foreach (self::PRACTICE_SUBJECTS as $subjectName) {
            $subject = Subject::where('name', $subjectName)->first();
            if (! $subject) {
                continue;
            }

            $questionData = $this->getQuestionData($subjectName);

            for ($i = 1; $i <= self::PRACTICE_COUNT; $i++) {
                $data = $questionData[($i - 1) % count($questionData)];

                $exists = Question::where('subject_id', $subject->id)
                    ->whereJsonContains('exam_types', 'JAMB')
                    ->where('question_text', $data['question'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $question = Question::create([
                    'subject_id' => $subject->id,
                    'exam_id' => null,
                    'question_text' => $data['question'],
                    'question_type' => 'multiple_choice',
                    'explanation' => $data['explanation'] ?? null,
                    'exam_types' => ['JAMB'],
                    'is_active' => true,
                ]);

                $this->createAnswers($question->id, $data);
            }
        }
    }

    private function seedPastExam(): void
    {
        $subject = Subject::where('name', 'Mathematics')->first();
        if (! $subject) {
            return;
        }

        $exam = Exam::firstOrCreate(
            [
                'title' => 'JAMB 2023 - Mathematics',
                'exam_type' => 'JAMB',
                'subject' => 'Mathematics',
                'year' => 2023,
            ],
            [
                'description' => 'JAMB 2023 past questions for Mathematics (sample)',
                'total_questions' => self::PRACTICE_COUNT,
                'is_active' => true,
            ]
        );

        if ($exam->questions()->count() > 0) {
            return;
        }

        $questionData = $this->getQuestionData('Mathematics');

        for ($i = 1; $i <= self::PRACTICE_COUNT; $i++) {
            $data = $questionData[($i - 1) % count($questionData)];

            $question = Question::create([
                'exam_id' => $exam->id,
                'subject_id' => $subject->id,
                'question_text' => $data['question'],
                'question_type' => 'multiple_choice',
                'explanation' => $data['explanation'] ?? null,
                'exam_types' => ['JAMB'],
                'is_active' => true,
            ]);

            $this->createAnswers($question->id, $data);
        }
    }

    private function createAnswers(int $questionId, array $data): void
    {
        $correctIndex = array_search($data['correct_answer'], $data['options'], true);

        foreach ($data['options'] as $index => $option) {
            Answer::create([
                'question_id' => $questionId,
                'answer_text' => $option,
                'is_correct' => $index === $correctIndex,
                'order' => chr(65 + $index),
            ]);
        }
    }

    private function getQuestionData(string $subject): array
    {
        return match ($subject) {
            'English Language' => [
                [
                    'question' => 'Choose the word that best completes the sentence: "She is _____ than her sister."',
                    'options' => ['tall', 'taller', 'tallest', 'more tall'],
                    'correct_answer' => 'taller',
                    'explanation' => 'When comparing two people, use the comparative form "taller".',
                ],
                [
                    'question' => 'What is the synonym of "happy"?',
                    'options' => ['Sad', 'Angry', 'Joyful', 'Tired'],
                    'correct_answer' => 'Joyful',
                    'explanation' => 'Joyful means feeling or expressing great happiness.',
                ],
                [
                    'question' => 'Identify the correct sentence:',
                    'options' => [
                        "He don't like coffee",
                        "He doesn't like coffee",
                        "He doesn't likes coffee",
                        "He don't likes coffee",
                    ],
                    'correct_answer' => "He doesn't like coffee",
                    'explanation' => 'Third person singular uses "doesn\'t" + base verb.',
                ],
                [
                    'question' => 'What is the past tense of "go"?',
                    'options' => ['goed', 'went', 'gone', 'going'],
                    'correct_answer' => 'went',
                    'explanation' => 'The past tense of "go" is "went".',
                ],
                [
                    'question' => 'Choose the correct preposition: "I arrived _____ the airport at 3pm."',
                    'options' => ['in', 'on', 'at', 'to'],
                    'correct_answer' => 'at',
                    'explanation' => 'We use "at" for specific locations.',
                ],
            ],
            default => [
                [
                    'question' => 'What is the value of x in the equation 2x + 5 = 15?',
                    'options' => ['5', '10', '15', '20'],
                    'correct_answer' => '5',
                    'explanation' => 'Subtract 5: 2x = 10, divide by 2: x = 5.',
                ],
                [
                    'question' => 'What is the square root of 144?',
                    'options' => ['10', '12', '14', '16'],
                    'correct_answer' => '12',
                    'explanation' => '12 × 12 = 144.',
                ],
                [
                    'question' => 'If a triangle has angles of 60°, 60°, and 60°, what type is it?',
                    'options' => ['Equilateral', 'Isosceles', 'Scalene', 'Right-angled'],
                    'correct_answer' => 'Equilateral',
                    'explanation' => 'All angles equal 60°.',
                ],
                [
                    'question' => 'What is 25% of 200?',
                    'options' => ['25', '50', '75', '100'],
                    'correct_answer' => '50',
                    'explanation' => '0.25 × 200 = 50.',
                ],
                [
                    'question' => 'What is the area of a circle with radius 7cm? (π = 22/7)',
                    'options' => ['44 cm²', '88 cm²', '154 cm²', '308 cm²'],
                    'correct_answer' => '154 cm²',
                    'explanation' => 'Area = πr² = (22/7) × 7 × 7 = 154 cm².',
                ],
            ],
        };
    }
}
