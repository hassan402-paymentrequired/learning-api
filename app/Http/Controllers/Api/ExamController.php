<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        // OPTIMIZATION: Use database-level filtering and bulk operations
        // Step 1: Get question IDs first (lightweight query)
        $questionIds = $exam->questions()
            ->orderBy('id')
            ->pluck('id')
            ->toArray();

        if (empty($questionIds)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'exam' => [
                        'id' => $exam->id,
                        'title' => $exam->title,
                        'total_questions' => $exam->questions_count,
                    ],
                    'questions' => [],
                ],
            ]);
        }

        // Step 2: Identify true/false questions that need answers using database-level filtering
        // Use a subquery to find questions without both True and False answers (avoids N+1 queries)
        $questionsNeedingAnswers = Question::whereIn('id', $questionIds)
            ->where('question_type', 'true_false')
            ->whereRaw('(SELECT COUNT(*) FROM answers WHERE answers.question_id = questions.id AND answers.answer_text IN ("True", "False")) < 2')
            ->get(['id', 'expected_answer']);

        // Step 3: Bulk create missing answers if needed (chunked bulk insert)
        if ($questionsNeedingAnswers->isNotEmpty()) {
            $answersToInsert = [];
            $questionIdsToCheck = $questionsNeedingAnswers->pluck('id')->toArray();
            
            // Get existing answers in one query to avoid duplicates
            $existingAnswers = Answer::whereIn('question_id', $questionIdsToCheck)
                ->whereIn('answer_text', ['True', 'False'])
                ->get()
                ->groupBy('question_id')
                ->map(function ($answers) {
                    return $answers->pluck('answer_text')->toArray();
                });
            
            foreach ($questionsNeedingAnswers as $question) {
                $expectedAnswerLower = strtolower(trim($question->expected_answer ?? ''));
                $existing = $existingAnswers->get($question->id, []);
                
                // Only insert answers that don't exist
                if (!in_array('True', $existing)) {
                    $answersToInsert[] = [
                        'question_id' => $question->id,
                        'answer_text' => 'True',
                        'order' => 'A',
                        'is_correct' => $expectedAnswerLower === 'true',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                
                if (!in_array('False', $existing)) {
                    $answersToInsert[] = [
                        'question_id' => $question->id,
                        'answer_text' => 'False',
                        'order' => 'B',
                        'is_correct' => $expectedAnswerLower === 'false',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Bulk insert in chunks to avoid memory issues
            if (!empty($answersToInsert)) {
                collect($answersToInsert)->chunk(500)->each(function ($chunk) {
                    Answer::insert($chunk->toArray());
                });
            }
        }

        // Step 4: Fetch questions with answers in one optimized query
        $questions = Question::whereIn('id', $questionIds)
            ->with(['answers' => function ($query) {
                $query->select('id', 'question_id', 'answer_text', 'order')
                    ->orderBy('order');
            }])
            ->orderBy('id')
            ->get()
            ->map(function ($question, $index) {
                return [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'image' => $question->image,
                    'order' => $index + 1,
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
                                $q->whereJsonContains('exam_types', $examType)
                                  ->orWhereHas('examCategories', function ($cq) use ($examType) {
                                      if (is_numeric($examType)) {
                                          $cq->where('exam_categories.id', (int) $examType);
                                      } else {
                                          $cq->where('exam_categories.slug', $examType);
                                      }
                                  });
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
        $deviceId = $request->header('X-Device-Id');
        $hasActiveSubscription = $user->hasActiveSubscriptionForDevice($deviceId);

        // Determine max count based on subscription status (valid for this device)
        $maxCount = $hasActiveSubscription ? 100 : 5;
        
        $request->validate([
            'exam_type' => 'required',
            'subject' => 'required|string',
            'count' => ['required', 'integer', 'min:1', "max:{$maxCount}"],
            'subject_test_id' => 'nullable|integer|exists:subject_tests,id',
        ]);

        $examType = $request->input('exam_type');
        $subject = $request->input('subject');
        $subjectTestId = $request->input('subject_test_id');
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

        // OPTIMIZATION: Use database-level filtering and bulk operations
        // Step 1: Get question IDs first (lightweight query)
        $query = Question::where('subject_id', $subjectModel->id)
            ->where(function ($q) use ($examType) {
                $q->whereJsonContains('exam_types', $examType)
                  ->orWhereHas('examCategories', function ($cq) use ($examType) {
                      if (is_numeric($examType)) {
                          $cq->where('exam_categories.id', (int) $examType);
                      } else {
                          $cq->where('exam_categories.slug', $examType);
                      }
                  });
            });

        // When subject_test_id provided (DLI), filter to questions belonging to that test
        if ($subjectTestId) {
            $query->whereHas('subjectTests', function ($q) use ($subjectTestId) {
                $q->where('subject_tests.id', $subjectTestId);
            });
        }

        $questionIds = $query->inRandomOrder()
            ->limit($count)
            ->pluck('id')
            ->toArray();

        if (empty($questionIds)) {
            return response()->json([
                'success' => true,
                'data' => [],
                'has_active_subscription' => $hasActiveSubscription,
                'max_questions_allowed' => $maxCount,
            ]);
        }

        // Step 2: Identify true/false questions that need answers using database-level filtering
        // Use a subquery to find questions without both True and False answers (avoids loading into memory)
        $questionsNeedingAnswers = Question::whereIn('id', $questionIds)
            ->where('question_type', 'true_false')
            ->whereRaw('(SELECT COUNT(*) FROM answers WHERE answers.question_id = questions.id AND answers.answer_text IN ("True", "False")) < 2')
            ->get(['id', 'expected_answer']);

        // Step 3: Bulk create missing answers if needed (chunked bulk insert)
        if ($questionsNeedingAnswers->isNotEmpty()) {
            $answersToInsert = [];
            $questionIdsToCheck = $questionsNeedingAnswers->pluck('id')->toArray();
            
            // Get existing answers in one query to avoid duplicates
            $existingAnswers = Answer::whereIn('question_id', $questionIdsToCheck)
                ->whereIn('answer_text', ['True', 'False'])
                ->get()
                ->groupBy('question_id')
                ->map(function ($answers) {
                    return $answers->pluck('answer_text')->toArray();
                });
            
            foreach ($questionsNeedingAnswers as $question) {
                $expectedAnswerLower = strtolower(trim($question->expected_answer ?? ''));
                $existing = $existingAnswers->get($question->id, []);
                
                // Only insert answers that don't exist
                if (!in_array('True', $existing)) {
                    $answersToInsert[] = [
                        'question_id' => $question->id,
                        'answer_text' => 'True',
                        'order' => 'A',
                        'is_correct' => $expectedAnswerLower === 'true',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                
                if (!in_array('False', $existing)) {
                    $answersToInsert[] = [
                        'question_id' => $question->id,
                        'answer_text' => 'False',
                        'order' => 'B',
                        'is_correct' => $expectedAnswerLower === 'false',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Bulk insert in chunks to avoid memory issues with very large datasets
            if (!empty($answersToInsert)) {
                collect($answersToInsert)->chunk(500)->each(function ($chunk) {
                    Answer::insert($chunk->toArray());
                });
            }
        }

        // Step 4: Fetch questions with answers in one optimized query
        $questions = Question::whereIn('id', $questionIds)
            ->with(['answers' => function ($query) {
                $query->select('id', 'question_id', 'answer_text', 'order')
                    ->orderBy('order');
            }])
            ->get()
            ->sortBy(function ($question) use ($questionIds) {
                // Maintain original random order
                return array_search($question->id, $questionIds);
            })
            ->values()
            ->map(function ($question, $index) {
                return [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'image' => $question->image,
                    'order' => $index + 1,
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
            'exam_type' => 'required|string',
            'subjects' => 'required|array|min:1',
            'subjects.*' => 'required|string',
        ]);

        $examType = $request->input('exam_type');

        // If numeric ID provided, resolve to slug for the exams table
        if (is_numeric($examType)) {
            $category = \App\Models\ExamCategory::find($examType);
            if ($category) {
                $examType = $category->slug;
            }
        }

        // Exams are now only for past questions, so no need to filter by type
        $query = Exam::where('is_active', true)
            ->where('exam_type', $examType)
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
            ->withCount('subjects')
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
       $v = $request->validate([
            'exam_type' => 'required',
        ]);

        Log::info('Department subjects request: ', $v);

        $department = Department::where('id', $departmentId)
            ->where('is_active', true)
            ->firstOrFail();

        $subjects = Subject::where('is_active', true)
            ->where('department_id', $department->id)
            ->where(function ($query) use ($request) {
                $query->whereJsonContains('exam_types', $request->exam_type)
                    ->orWhereHas('questions', function ($q) use ($request) {
                        $q->whereJsonContains('exam_types', $request->exam_type)
                          ->orWhereHas('examCategories', function($cq) use ($request) {
                            //   if (is_numeric($request->exam_type)) {
                            //       $cq->where('exam_categories.id', $request->exam_type);
                            //   } else {
                            //       $cq->where('exam_categories.slug', $request->exam_type);
                            //   }
                            $cq->where('exam_categories.id', (int) $request->exam_type);
                          });
                    });
            })
            ->with('tests:id,subject_id,name')
            ->withCount('questions')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        return response()->json([
            'success' => true,
            'data' => $subjects,
        ]);
    }
}
