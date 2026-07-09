<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Services\ExamCategoryResolver;
use App\Support\PublicId;
use App\Support\PublicUuidLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
            'data' => PublicId::collection($exams),
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
    public function subjects(Request $request, ExamCategoryResolver $resolver)
    {
        $examType = $request->input('exam_type');
        $type = $request->input('type', 'past_question'); // Default to past_question
        $hasExamCategoryExamPivot = Schema::hasTable('exam_category_exam');

        $examSearchValues = array_unique(array_filter(array_map(
            'strtolower',
            $resolver->matchTokens($examType)
        )));
        $category = $resolver->resolve($examType);
        if ($category) {
            $examSearchValues[] = strtolower($category->slug);
            $examSearchValues[] = strtolower($category->name);
        }
        $examSearchValues = array_values(array_unique($examSearchValues));

        if ($type === 'practice') {
            $subjectsQuery = Subject::where('is_active', true);
            if ($examType) {
                $resolver->applySubjectExamTypeFilter($subjectsQuery, $examType);
            }

            $subjects = $subjectsQuery
                ->pluck('name')
                ->sort()
                ->values();
        } else {
            // For past questions, fetch from exams table
            $query = Exam::where('is_active', true)
                ->whereNotNull('subject');

            // Filter by exam type category (matches legacy column OR new many-to-many link)
            if (!empty($examSearchValues)) {
                $query->where(function($q) use ($examSearchValues, $hasExamCategoryExamPivot) {
                    foreach ($examSearchValues as $val) {
                        $q->orWhereRaw('LOWER(exam_type) = ?', [$val]);
                        if ($hasExamCategoryExamPivot) {
                            $q->orWhereHas('examCategories', function($cq) use ($val) {
                                $cq->whereRaw('LOWER(slug) = ?', [$val])
                                    ->orWhereRaw('LOWER(name) = ?', [$val]);
                            });
                        }
                    }
                });
            }

            $subjects = $query->distinct()
                ->pluck('subject')
                ->filter()
                ->values()
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
     * Requires an active subscription.
     */
    public function getPracticeQuestions(Request $request, ExamCategoryResolver $resolver)
    {
        $user = auth()->user();
        $deviceId = $request->header('X-Device-Id');
        $hasActiveSubscription = $user->hasActiveSubscriptionForDevice($deviceId);

        if (!$hasActiveSubscription) {
            return response()->json([
                'success' => false,
                'message' => 'An active subscription is required to access questions. Please subscribe to continue.',
                'requires_subscription' => true,
            ], 403);
        }

        $maxCount = 100;
        
        $request->validate([
            'exam_type' => 'required',
            'subject' => 'required|string',
            'count' => ['required', 'integer', 'min:1', "max:{$maxCount}"],
            'subject_test_uuid' => 'nullable|uuid|exists:subject_tests,uuid',
        ]);

        $examType = $request->input('exam_type');
        $subject = $request->input('subject');
        $subjectTestUuid = $request->input('subject_test_uuid');
        $count = $request->input('count');

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
        $subjectTest = null;
        if ($subjectTestUuid) {
            $subjectTest = PublicUuidLookup::findOrFail(\App\Models\SubjectTest::class, $subjectTestUuid);
            if ($subjectTest->subject_id !== $subjectModel->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected test does not belong to this course.',
                    'data' => [],
                ], 404);
            }
        }

        $query = Question::forSubjectPractice($subjectModel, $subjectTest);
        $resolver->applyQuestionExamTypeFilter($query, $examType);

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
                $payload = PublicId::question($question);
                $payload['order'] = $index + 1;

                return $payload;
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

        return response()->json($response);
    }

    /**
     * Get available years for past questions.
     * Used to show year selection for past question mode.
     */
    public function getAvailableYears(Request $request, ExamCategoryResolver $resolver)
    {
        $request->validate([
            'exam_type' => 'required|string',
            'subjects' => 'required|array|min:1',
            'subjects.*' => 'required|string',
        ]);

        $examType = $request->input('exam_type');
        $hasExamCategoryExamPivot = Schema::hasTable('exam_category_exam');
        
        $examSearchValues = array_unique(array_filter(array_map(
            'strtolower',
            $resolver->matchTokens($examType)
        )));
        $category = $resolver->resolve($examType);
        if ($category) {
            $examSearchValues[] = strtolower($category->slug);
            $examSearchValues[] = strtolower($category->name);
        }
        $examSearchValues = array_values(array_unique($examSearchValues));

        // Exams are now only for past questions, so no need to filter by type
        $query = Exam::where('is_active', true)
            ->where(function($q) use ($examSearchValues, $hasExamCategoryExamPivot) {
                foreach ($examSearchValues as $val) {
                    $q->orWhereRaw('LOWER(exam_type) = ?', [$val]);
                    if ($hasExamCategoryExamPivot) {
                        $q->orWhereHas('examCategories', function($cq) use ($val) {
                            $cq->whereRaw('LOWER(slug) = ?', [$val])
                                ->orWhereRaw('LOWER(name) = ?', [$val]);
                        });
                    }
                }
            })
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
            ->get(['uuid', 'name', 'slug', 'description']);

        return response()->json([
            'success' => true,
            'data' => PublicId::collection($departments),
        ]);
    }

    /**
     * Get subjects for a specific department (filtered by exam_type UUID or legacy slug).
     */
    public function departmentSubjects(Request $request, ExamCategoryResolver $resolver, Department $department)
    {
        $request->validate([
            'exam_type' => 'required',
        ]);

        if (!$department->is_active) {
            abort(404);
        }

        $subjectsQuery = Subject::query()
            ->where('is_active', true)
            ->whereHas('departments', function ($query) use ($department) {
                $query->where('departments.id', $department->id);
            });

        $resolver->applySubjectExamTypeFilter($subjectsQuery, $request->exam_type);

        $subjects = $subjectsQuery
            ->with('tests:uuid,subject_id,name')
            ->withCount('questions')
            ->orderBy('name')
            ->get(['uuid', 'name', 'slug', 'description', 'id']);

        $data = $subjects->map(function (Subject $subject) {
            return [
                'uuid' => $subject->uuid,
                'name' => $subject->name,
                'slug' => $subject->slug,
                'description' => $subject->description,
                'questions_count' => $subject->questions_count,
                'tests' => $subject->tests->map(fn ($test) => [
                    'uuid' => $test->uuid,
                    'name' => $test->name,
                ])->values(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
