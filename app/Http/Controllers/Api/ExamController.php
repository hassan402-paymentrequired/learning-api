<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    /**
     * Get list of available exams.
     */
    public function index(Request $request)
    {
        $query = Exam::where('is_active', true)
            ->withCount('questions');

        // Filter by exam type
        if ($request->has('exam_type')) {
            $query->where('exam_type', $request->exam_type);
        }

        // Note: type filter removed - exams are now only for past questions

        // Filter by subject
        if ($request->has('subject')) {
            $query->where('subject', $request->subject);
        }

        // Filter by year (for past questions)
        if ($request->has('year')) {
            $query->where('year', $request->year);
        }

        $exams = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $exams,
        ]);
    }

    /**
     * Get a specific exam.
     */
    public function show(Exam $exam)
    {
        if (!$exam->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found',
            ], 404);
        }

        $exam->loadCount('questions');

        return response()->json([
            'success' => true,
            'data' => $exam,
        ]);
    }

    /**
     * Get questions for an exam (without correct answers).
     */
    public function questions(Exam $exam)
    {
        if (!$exam->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found',
            ], 404);
        }

        $questions = $exam->questions()
            ->with(['answers' => function ($query) {
                $query->select('id', 'question_id', 'answer_text', 'order')
                    ->orderBy('order');
            }])
            ->orderBy('id')
            ->get()
            ->map(function ($question, $index) {
                // For true/false questions, create answer records if they don't exist
                if ($question->question_type === 'true_false' && $question->answers->isEmpty()) {
                    // Create answer records for true/false questions
                    $expectedAnswer = strtolower(trim($question->expected_answer ?? ''));
                    $trueAnswer = $question->answers()->firstOrCreate(
                        [
                            'question_id' => $question->id,
                            'answer_text' => 'True',
                        ],
                        [
                            'order' => 'A',
                            'is_correct' => $expectedAnswer === 'true',
                        ]
                    );
                    
                    $falseAnswer = $question->answers()->firstOrCreate(
                        [
                            'question_id' => $question->id,
                            'answer_text' => 'False',
                        ],
                        [
                            'order' => 'B',
                            'is_correct' => $expectedAnswer === 'false',
                        ]
                    );
                    
                    // Reload answers
                    $question->load('answers');
                }
                
                return [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'image' => $question->image,
                    'order' => $index + 1, // Use index-based ordering
                    'answers' => $question->answers->map(function ($answer) {
                        return [
                            'id' => $answer->id,
                            'answer_text' => $answer->answer_text,
                            'order' => $answer->order,
                        ];
                    }),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'exam' => [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'total_questions' => $exam->questions_count,
                ],
                'questions' => $questions,
            ],
        ]);
    }

    /**
     * Get list of available subjects for an exam type.
     */
    public function subjects(Request $request)
    {
        $examType = $request->input('exam_type');
        $type = $request->input('type', 'past_question'); // Default to past_question

        if ($type === 'practice') {
            // For practice questions, fetch from subjects table based on exam_types
            // Check both subject's exam_types field and questions' exam_types
            $subjects = Subject::where('is_active', true)
                ->where(function ($query) use ($examType) {
                    // Subjects that have the exam_type in their exam_types array
                    if ($examType) {
                        $query->whereJsonContains('exam_types', $examType)
                            ->orWhereHas('questions', function ($q) use ($examType) {
                                $q->whereJsonContains('exam_types', $examType);
                            });
                    }
                })
                ->pluck('name')
                ->sort()
                ->values();
        } else {
            // For past questions, fetch from exams table
            $query = Exam::where('is_active', true)
                ->whereNotNull('subject');

            // Filter by exam type
            if ($examType) {
                $query->where('exam_type', $examType);
            }

            $subjects = $query->distinct()
                ->pluck('subject')
                ->filter()
                ->sort()
                ->values();
        }

        return response()->json([
            'success' => true,
            'data' => $subjects,
        ]);
    }

    /**
     * Get random practice questions by exam_type and subject.
     * Used for practice mode where questions are randomly selected.
     * Non-subscribed users are limited to 5 questions maximum.
     */
    public function getPracticeQuestions(Request $request)
    {
        $user = auth()->user();
        $clientIp = $request->ip() ?? '';
        $hasActiveSubscription = $user->hasActiveSubscriptionForDevice($clientIp);

        // Determine max count based on subscription status (valid for this device)
        $maxCount = $hasActiveSubscription ? 100 : 5;
        
        $request->validate([
            'exam_type' => 'required|in:JAMB,DLI,UNILAG,GENERAL',
            'subject' => 'required|string',
            'count' => ['required', 'integer', 'min:1', "max:{$maxCount}"],
        ]);

        $examType = $request->input('exam_type');
        $subject = $request->input('subject');
        $requestedCount = $request->input('count');
        
        // Enforce 5-question limit for non-subscribed users
        $count = $hasActiveSubscription ? $requestedCount : min($requestedCount, 5);

        // For practice questions, fetch directly from questions table based on subject and exam_types
        // Questions can exist independently of exams for practice mode
        $subjectModel = Subject::where('name', $subject)->first();

        if (!$subjectModel) {
            return response()->json([
                'success' => false,
                'message' => "Subject '{$subject}' not found.",
                'data' => [],
            ], 404);
        }

        // Get random questions that:
        // 1. Belong to the requested subject (subject_id)
        // 2. Have the requested exam_type in their exam_types array
        // 3. Questions should be available for practice (can be standalone or linked to any exam)
        $questions = Question::where('subject_id', $subjectModel->id)
            ->whereJsonContains('exam_types', $examType)
            ->inRandomOrder()
            ->limit($count)
            ->with(['answers' => function ($query) {
                $query->select('id', 'question_id', 'answer_text', 'order')
                    ->orderBy('order');
            }])
            ->get()
            ->map(function ($question, $index) {
                // For true/false questions, create answer records if they don't exist
                if ($question->question_type === 'true_false' && $question->answers->isEmpty()) {
                    // Create answer records for true/false questions
                    $expectedAnswer = strtolower(trim($question->expected_answer ?? ''));
                    $trueAnswer = $question->answers()->firstOrCreate(
                        [
                            'question_id' => $question->id,
                            'answer_text' => 'True',
                        ],
                        [
                            'order' => 'A',
                            'is_correct' => $expectedAnswer === 'true',
                        ]
                    );
                    
                    $falseAnswer = $question->answers()->firstOrCreate(
                        [
                            'question_id' => $question->id,
                            'answer_text' => 'False',
                        ],
                        [
                            'order' => 'B',
                            'is_correct' => $expectedAnswer === 'false',
                        ]
                    );
                    
                    // Reload answers
                    $question->load('answers');
                }
                
                return [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'image' => $question->image,
                    'order' => $index + 1, // Use index-based ordering
                    'answers' => $question->answers->map(function ($answer) {
                        return [
                            'id' => $answer->id,
                            'answer_text' => $answer->answer_text,
                            'order' => $answer->order,
                        ];
                    }),
                ];
            });

        $response = [
            'success' => true,
            'data' => $questions,
            'has_active_subscription' => $hasActiveSubscription,
            'max_questions_allowed' => $maxCount,
        ];

        if ($questions->count() < $count) {
            $response['warning'] = "Only {$questions->count()} questions available (requested {$count})";
        }

        // Add message for non-subscribed users if they requested more than 5
        if (!$hasActiveSubscription && $requestedCount > 5) {
            $response['message'] = 'Non-subscribed users are limited to 5 questions per practice session. Subscribe to unlock unlimited practice questions.';
            $response['questions_returned'] = $questions->count();
        }

        return response()->json($response);
    }

    /**
     * Get available years for past questions.
     * Used to show year selection for past question mode.
     */
    public function getAvailableYears(Request $request)
    {
        $request->validate([
            'exam_type' => 'required|in:JAMB,DLI,UNILAG,GENERAL',
            'subjects' => 'required|array|min:1',
            'subjects.*' => 'required|string',
        ]);

        // Exams are now only for past questions, so no need to filter by type
        $query = Exam::where('is_active', true)
            ->where('exam_type', $request->input('exam_type'))
            ->whereNotNull('year');

        // Filter by subjects if provided (array of subject names)
        if ($request->has('subjects') && is_array($request->subjects) && count($request->subjects) > 0) {
            $query->whereIn('subject', $request->input('subjects'));
        }

        $years = $query->distinct()
            ->pluck('year')
            ->filter(function ($year) {
                return $year !== null && $year !== '';
            })
            ->map(function ($year) {
                return (int) $year; // Ensure it's an integer
            })
            ->sortDesc()
            ->values();

        // If no years found, return empty array with success
        if ($years->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'No past questions found for the selected exam type and subjects.',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $years,
        ]);
    }

    /**
     * Get list of active departments (for Unilag/DLI practice flow).
     */
    public function departments(Request $request)
    {
        $departments = Department::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        return response()->json([
            'success' => true,
            'data' => $departments,
        ]);
    }

    /**
     * Get subjects for a specific department (filtered by exam_type).
     */
    public function departmentSubjects(Request $request, $departmentId)
    {
        $request->validate([
            'exam_type' => 'required|in:DLI,UNILAG',
        ]);

        $department = Department::where('id', $departmentId)
            ->where('is_active', true)
            ->firstOrFail();

        $subjects = Subject::where('is_active', true)
            ->where('department_id', $department->id)
            ->where(function ($query) use ($request) {
                $query->whereJsonContains('exam_types', $request->exam_type)
                    ->orWhereHas('questions', function ($q) use ($request) {
                        $q->whereJsonContains('exam_types', $request->exam_type);
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json([
            'success' => true,
            'data' => $subjects,
        ]);
    }
}
