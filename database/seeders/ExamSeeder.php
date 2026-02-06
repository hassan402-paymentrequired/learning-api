<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Answer;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class ExamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create standalone practice questions (not linked to exams)
        $this->createPracticeQuestions();
        
        // Create JAMB Past Questions Exams
        $this->createJAMBPastQuestions();
        
        // Create DLI Past Questions Exams
        $this->createDLIPastQuestions();
    }

    private function createPracticeQuestions()
    {
        // Create standalone practice questions for JAMB and DLI
        // These questions exist independently of exams for practice mode
        $subjects = [
            'Mathematics' => ['JAMB', 'DLI'],
            'English Language' => ['JAMB', 'DLI'],
            'Physics' => ['JAMB'],
            'Chemistry' => ['JAMB'],
            'Biology' => ['JAMB'],
            'Economics' => ['JAMB', 'DLI'],
            'Government' => ['JAMB', 'DLI'],
        ];

        foreach ($subjects as $subjectName => $examTypes) {
            $subject = Subject::where('name', $subjectName)->first();
            if (!$subject) {
                continue;
            }

            $questionData = $this->getQuestionData($subjectName);
            
            // Create 50 practice questions for each subject/exam_type combination
            foreach ($examTypes as $examType) {
                for ($i = 1; $i <= 50; $i++) {
                    $questionIndex = ($i - 1) % count($questionData);
                    $data = $questionData[$questionIndex];
                    
                    // Check if question already exists
                    $exists = Question::where('subject_id', $subject->id)
                        ->whereJsonContains('exam_types', $examType)
                        ->where('question_text', $data['question'])
                        ->exists();
                    
                    if ($exists) {
                        continue;
                    }
                    
                    $question = Question::create([
                        'subject_id' => $subject->id,
                        'exam_id' => null, // Standalone practice question
                        'question_text' => $data['question'],
                        'question_type' => 'multiple_choice',
                        'explanation' => $data['explanation'] ?? "This is the correct answer because {$data['correct_answer']}.",
                        'exam_types' => [$examType],
                        'points' => 1,
                        'order' => $i,
                    ]);

                    // Create answers
                    $correctIndex = array_search($data['correct_answer'], $data['options']);
                    foreach ($data['options'] as $index => $option) {
                        Answer::create([
                            'question_id' => $question->id,
                            'answer_text' => $option,
                            'is_correct' => $index === $correctIndex,
                            'order' => chr(65 + $index), // A, B, C, D
                        ]);
                    }
                }
            }
        }
    }

    private function createJAMBPastQuestions()
    {
        $subjects = ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology'];
        $years = [2023, 2022, 2021];
        
        foreach ($subjects as $subject) {
            $subjectModel = Subject::where('name', $subject)->first();
            if (!$subjectModel) {
                continue;
            }

            foreach ($years as $year) {
                $exam = Exam::firstOrCreate(
                    [
                        'title' => "JAMB {$year} - {$subject}",
                        'exam_type' => 'JAMB',
                        'subject' => $subject,
                        'year' => $year,
                    ],
                    [
                        'description' => "JAMB {$year} past questions for {$subject}",
                        'total_questions' => 50,
                        'is_active' => true,
                    ]
                );

                $this->createQuestionsForExam($exam, $subjectModel, 50, ['JAMB']);
            }
        }
    }

    private function createDLIPastQuestions()
    {
        $subjects = ['Mathematics', 'English Language', 'Economics', 'Government'];
        $years = [2023, 2022, 2021];
        
        foreach ($subjects as $subject) {
            $subjectModel = Subject::where('name', $subject)->first();
            if (!$subjectModel) {
                continue;
            }

            foreach ($years as $year) {
                $exam = Exam::firstOrCreate(
                    [
                        'title' => "DLI {$year} - {$subject}",
                        'exam_type' => 'DLI',
                        'subject' => $subject,
                        'year' => $year,
                    ],
                    [
                        'description' => "DLI {$year} past questions for {$subject}",
                        'total_questions' => 50,
                        'is_active' => true,
                    ]
                );

                $this->createQuestionsForExam($exam, $subjectModel, 50, ['DLI']);
            }
        }
    }

    private function createQuestionsForExam(Exam $exam, Subject $subject, int $count, array $examTypes)
    {
        // Check if questions already exist
        if ($exam->questions()->count() > 0) {
            return;
        }

        $questionData = $this->getQuestionData($subject->name);

        for ($i = 1; $i <= $count; $i++) {
            $questionIndex = ($i - 1) % count($questionData);
            $data = $questionData[$questionIndex];
            
            $question = Question::create([
                'exam_id' => $exam->id,
                'subject_id' => $subject->id,
                'question_text' => $data['question'],
                'question_type' => 'multiple_choice',
                'explanation' => $data['explanation'] ?? "This is the correct answer because {$data['correct_answer']}.",
                'exam_types' => $examTypes,
                'points' => 1,
                'order' => $i,
            ]);

            // Create answers
            $correctIndex = array_search($data['correct_answer'], $data['options']);
            foreach ($data['options'] as $index => $option) {
                Answer::create([
                    'question_id' => $question->id,
                    'answer_text' => $option,
                    'is_correct' => $index === $correctIndex,
                    'order' => chr(65 + $index), // A, B, C, D
                ]);
            }
        }

        // Update exam total_questions
        $exam->update(['total_questions' => $count]);
    }

    private function getQuestionData(string $subject): array
    {
        $data = [
            'Mathematics' => [
                [
                    'question' => 'What is the value of x in the equation 2x + 5 = 15?',
                    'options' => ['5', '10', '15', '20'],
                    'correct_answer' => '5',
                    'explanation' => 'Subtract 5 from both sides: 2x = 10, then divide by 2: x = 5',
                ],
                [
                    'question' => 'What is the square root of 144?',
                    'options' => ['10', '12', '14', '16'],
                    'correct_answer' => '12',
                    'explanation' => '12 × 12 = 144, so the square root of 144 is 12',
                ],
                [
                    'question' => 'If a triangle has angles of 60°, 60°, and 60°, what type of triangle is it?',
                    'options' => ['Equilateral', 'Isosceles', 'Scalene', 'Right-angled'],
                    'correct_answer' => 'Equilateral',
                    'explanation' => 'An equilateral triangle has all three angles equal to 60°',
                ],
                [
                    'question' => 'What is 25% of 200?',
                    'options' => ['25', '50', '75', '100'],
                    'correct_answer' => '50',
                    'explanation' => '25% of 200 = 0.25 × 200 = 50',
                ],
                [
                    'question' => 'What is the area of a circle with radius 7cm? (Use π = 22/7)',
                    'options' => ['44 cm²', '88 cm²', '154 cm²', '308 cm²'],
                    'correct_answer' => '154 cm²',
                    'explanation' => 'Area = πr² = (22/7) × 7 × 7 = 22 × 7 = 154 cm²',
                ],
            ],
            'English Language' => [
                [
                    'question' => 'Choose the word that best completes the sentence: "She is _____ than her sister."',
                    'options' => ['tall', 'taller', 'tallest', 'more tall'],
                    'correct_answer' => 'taller',
                    'explanation' => 'When comparing two people, we use the comparative form "taller"',
                ],
                [
                    'question' => 'What is the synonym of "happy"?',
                    'options' => ['Sad', 'Angry', 'Joyful', 'Tired'],
                    'correct_answer' => 'Joyful',
                    'explanation' => 'Joyful means feeling or expressing great happiness',
                ],
                [
                    'question' => 'Identify the correct sentence:',
                    'options' => [
                        'He don\'t like coffee',
                        'He doesn\'t like coffee',
                        'He doesn\'t likes coffee',
                        'He don\'t likes coffee'
                    ],
                    'correct_answer' => 'He doesn\'t like coffee',
                    'explanation' => 'With third person singular "he", we use "doesn\'t" and the base form of the verb',
                ],
                [
                    'question' => 'What is the past tense of "go"?',
                    'options' => ['goed', 'went', 'gone', 'going'],
                    'correct_answer' => 'went',
                    'explanation' => 'The past tense of "go" is the irregular form "went"',
                ],
                [
                    'question' => 'Choose the correct preposition: "I arrived _____ the airport at 3pm."',
                    'options' => ['in', 'on', 'at', 'to'],
                    'correct_answer' => 'at',
                    'explanation' => 'We use "at" for specific locations like airports, stations, etc.',
                ],
            ],
            'Physics' => [
                [
                    'question' => 'What is the SI unit of force?',
                    'options' => ['Joule', 'Newton', 'Watt', 'Pascal'],
                    'correct_answer' => 'Newton',
                    'explanation' => 'Force is measured in Newtons (N) in the SI system',
                ],
                [
                    'question' => 'What is the speed of light in vacuum?',
                    'options' => ['3 × 10⁶ m/s', '3 × 10⁸ m/s', '3 × 10¹⁰ m/s', '3 × 10¹² m/s'],
                    'correct_answer' => '3 × 10⁸ m/s',
                    'explanation' => 'The speed of light in vacuum is approximately 3 × 10⁸ meters per second',
                ],
                [
                    'question' => 'What happens to the pressure of a gas when its volume is decreased at constant temperature?',
                    'options' => ['Increases', 'Decreases', 'Remains constant', 'Becomes zero'],
                    'correct_answer' => 'Increases',
                    'explanation' => 'According to Boyle\'s law, pressure is inversely proportional to volume at constant temperature',
                ],
                [
                    'question' => 'What is the formula for kinetic energy?',
                    'options' => ['mv', 'mgh', '½mv²', 'Fd'],
                    'correct_answer' => '½mv²',
                    'explanation' => 'Kinetic energy = ½ × mass × velocity²',
                ],
                [
                    'question' => 'Which of the following is a vector quantity?',
                    'options' => ['Mass', 'Speed', 'Velocity', 'Temperature'],
                    'correct_answer' => 'Velocity',
                    'explanation' => 'Velocity has both magnitude and direction, making it a vector quantity',
                ],
            ],
            'Chemistry' => [
                [
                    'question' => 'What is the chemical symbol for gold?',
                    'options' => ['Go', 'Gd', 'Au', 'Ag'],
                    'correct_answer' => 'Au',
                    'explanation' => 'Au comes from the Latin word "aurum" meaning gold',
                ],
                [
                    'question' => 'What is the pH of a neutral solution?',
                    'options' => ['0', '7', '14', '10'],
                    'correct_answer' => '7',
                    'explanation' => 'A pH of 7 indicates a neutral solution (neither acidic nor basic)',
                ],
                [
                    'question' => 'What is the molecular formula of water?',
                    'options' => ['H₂O', 'H₂O₂', 'HO', 'H₃O'],
                    'correct_answer' => 'H₂O',
                    'explanation' => 'Water consists of two hydrogen atoms and one oxygen atom',
                ],
                [
                    'question' => 'Which gas makes up approximately 78% of the atmosphere?',
                    'options' => ['Oxygen', 'Carbon dioxide', 'Nitrogen', 'Argon'],
                    'correct_answer' => 'Nitrogen',
                    'explanation' => 'Nitrogen makes up about 78% of Earth\'s atmosphere',
                ],
                [
                    'question' => 'What type of reaction is: 2H₂ + O₂ → 2H₂O?',
                    'options' => ['Decomposition', 'Combination', 'Displacement', 'Neutralization'],
                    'correct_answer' => 'Combination',
                    'explanation' => 'Two or more substances combine to form a single product',
                ],
            ],
            'Biology' => [
                [
                    'question' => 'What is the basic unit of life?',
                    'options' => ['Tissue', 'Cell', 'Organ', 'Organism'],
                    'correct_answer' => 'Cell',
                    'explanation' => 'The cell is the basic structural and functional unit of all living organisms',
                ],
                [
                    'question' => 'Which organelle is responsible for protein synthesis?',
                    'options' => ['Mitochondria', 'Ribosome', 'Nucleus', 'Golgi apparatus'],
                    'correct_answer' => 'Ribosome',
                    'explanation' => 'Ribosomes are the sites where proteins are synthesized in cells',
                ],
                [
                    'question' => 'What is the process by which plants make their food?',
                    'options' => ['Respiration', 'Photosynthesis', 'Digestion', 'Excretion'],
                    'correct_answer' => 'Photosynthesis',
                    'explanation' => 'Photosynthesis is the process by which plants convert light energy into chemical energy',
                ],
                [
                    'question' => 'How many chambers does the human heart have?',
                    'options' => ['2', '3', '4', '5'],
                    'correct_answer' => '4',
                    'explanation' => 'The human heart has four chambers: two atria and two ventricles',
                ],
                [
                    'question' => 'What is the largest organ in the human body?',
                    'options' => ['Liver', 'Lung', 'Skin', 'Intestine'],
                    'correct_answer' => 'Skin',
                    'explanation' => 'The skin is the largest organ, covering the entire external surface of the body',
                ],
            ],
            'Economics' => [
                [
                    'question' => 'What is the study of how individuals and societies allocate scarce resources?',
                    'options' => ['Sociology', 'Economics', 'Psychology', 'Anthropology'],
                    'correct_answer' => 'Economics',
                    'explanation' => 'Economics is the social science that studies the production, distribution, and consumption of goods and services',
                ],
                [
                    'question' => 'What is the law of demand?',
                    'options' => [
                        'As price increases, demand increases',
                        'As price increases, demand decreases',
                        'Price and demand are unrelated',
                        'Demand always equals supply'
                    ],
                    'correct_answer' => 'As price increases, demand decreases',
                    'explanation' => 'The law of demand states that, all else being equal, as the price of a good increases, the quantity demanded decreases',
                ],
                [
                    'question' => 'What is GDP an abbreviation for?',
                    'options' => [
                        'Gross Domestic Product',
                        'General Development Program',
                        'Global Distribution Process',
                        'Government Development Plan'
                    ],
                    'correct_answer' => 'Gross Domestic Product',
                    'explanation' => 'GDP stands for Gross Domestic Product, the total value of goods and services produced in a country',
                ],
                [
                    'question' => 'What is inflation?',
                    'options' => [
                        'Decrease in prices',
                        'Increase in prices',
                        'Stable prices',
                        'Zero prices'
                    ],
                    'correct_answer' => 'Increase in prices',
                    'explanation' => 'Inflation is the rate at which the general level of prices for goods and services rises',
                ],
                [
                    'question' => 'What is opportunity cost?',
                    'options' => [
                        'The cost of production',
                        'The value of the next best alternative',
                        'The total cost',
                        'The fixed cost'
                    ],
                    'correct_answer' => 'The value of the next best alternative',
                    'explanation' => 'Opportunity cost is the value of the next best alternative that must be forgone when a choice is made',
                ],
            ],
            'Government' => [
                [
                    'question' => 'What is a democracy?',
                    'options' => [
                        'Rule by one person',
                        'Rule by the people',
                        'Rule by the military',
                        'Rule by the wealthy'
                    ],
                    'correct_answer' => 'Rule by the people',
                    'explanation' => 'Democracy is a system of government where power is held by the people, either directly or through elected representatives',
                ],
                [
                    'question' => 'What are the three arms of government?',
                    'options' => [
                        'Executive, Legislative, Judiciary',
                        'Federal, State, Local',
                        'President, Senate, House',
                        'Executive, Military, Police'
                    ],
                    'correct_answer' => 'Executive, Legislative, Judiciary',
                    'explanation' => 'The three arms of government are the Executive (implements laws), Legislative (makes laws), and Judiciary (interprets laws)',
                ],
                [
                    'question' => 'What is the separation of powers?',
                    'options' => [
                        'Dividing government into branches',
                        'Dividing the country into states',
                        'Dividing power between federal and state',
                        'Dividing the military'
                    ],
                    'correct_answer' => 'Dividing government into branches',
                    'explanation' => 'Separation of powers is the division of government responsibilities into distinct branches to prevent abuse of power',
                ],
                [
                    'question' => 'What is a constitution?',
                    'options' => [
                        'A set of laws',
                        'A fundamental law of a country',
                        'A political party',
                        'A government policy'
                    ],
                    'correct_answer' => 'A fundamental law of a country',
                    'explanation' => 'A constitution is the fundamental law that establishes the framework of government and defines the rights of citizens',
                ],
                [
                    'question' => 'What is federalism?',
                    'options' => [
                        'Rule by one central government',
                        'Division of power between central and regional governments',
                        'Rule by states only',
                        'Military rule'
                    ],
                    'correct_answer' => 'Division of power between central and regional governments',
                    'explanation' => 'Federalism is a system where power is divided between a central government and regional/state governments',
                ],
            ],
        ];

        // Return data for the subject, or default to Mathematics if not found
        return $data[$subject] ?? $data['Mathematics'];
    }
}
