<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExamCategoryResolver;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\UserAnswer;
use App\Models\UserStreak;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExamAttemptController extends Controller
{
    /**
     * Start a new exam attempt.
     */
    public function start(Request $request, Exam $exam)
    {
        if (!$exam->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found',
            ], 404);
        }

        // Check if user has an in-progress attempt
        $existingAttempt = ExamAttempt::where('user_id', auth()->id())
            ->where('exam_id', $exam->id)
            ->where('status', 'in_progress')
            ->first();

        if ($existingAttempt) {
            return response()->json([
                'success' => true,
                'message' => 'Resuming existing attempt',
                'data' => [
                    'attempt' => $existingAttempt->load('exam'),
                ],
            ]);
        }

        // Get subjects and duration from request if provided (for multi-subject exams)
        $subjects = $request->input('subjects');
        $durationMinutes = $request->input('duration_minutes');
        
        // Calculate total questions from subjects if provided
        $totalQuestions = $exam->questions()->count();
        if ($subjects && is_array($subjects)) {
            $totalQuestions = array_sum(array_column($subjects, 'question_count'));
        }

        $attemptData = [
            'user_id' => auth()->id(),
            'exam_id' => $exam->id,
            'started_at' => now(),
            'status' => 'in_progress',
            'total_questions' => $totalQuestions,
        ];

        if ($subjects !== null) {
            // Process subjects to include question_ids if provided
            $processedSubjects = array_map(function($subject) {
                if (isset($subject['questions']) && is_array($subject['questions'])) {
                    $subject['question_ids'] = array_column($subject['questions'], 'id');
                    unset($subject['questions']); // Remove full objects to save space
                }
                return $subject;
            }, $subjects);
            $attemptData['subjects_data'] = $processedSubjects;
            $attemptData['subjects'] = $processedSubjects;
        }
        if ($durationMinutes !== null) {
            $attemptData['duration_minutes'] = $durationMinutes;
        }

        $attempt = ExamAttempt::create($attemptData);

        return response()->json([
            'success' => true,
            'message' => 'Exam started successfully',
            'data' => [
                'attempt' => $attempt->load('exam'),
            ],
        ], 201);
    }

    public function startPracticeSession(Request $request, ExamCategoryResolver $resolver)
    {
        $user = auth()->user();
        $deviceId = $request->header('X-Device-Id');
        $hasActiveSubscription = $user->hasActiveSubscriptionForDevice($deviceId);
        
        $request->validate([
            'exam_type' => 'required',
            'subjects' => 'required|array|min:1',
            'subjects.*.subject' => 'required|string',
            'subjects.*.year' => 'nullable|integer',
            'subjects.*.question_count' => 'required|integer|min:1|max:100',
            'subjects.*.question_ids' => 'nullable|array',
            'subjects.*.subject_test_id' => 'nullable|integer',
            'duration_minutes' => 'required|integer|min:1|max:300',
        ]);

        $examType = $request->input('exam_type');
        $subjectsInput = $request->input('subjects');
        $durationMinutes = $request->input('duration_minutes');
        
        // Find the exam category to get consistent slug/name for the practice record
        $examCategory = \App\Models\ExamCategory::where('id', $examType)
            ->orWhere('slug', $examType)
            ->first();
        
        $categorySlug = $examCategory ? $examCategory->slug : $examType;
        $categoryName = $examCategory ? $examCategory->name : $examType;

        // No exam record for practice sessions; they exist independently of admin-uploaded exams.
        $examId = null;

        $allQuestionsData = [];
        $processedSubjects = [];
        $totalQuestions = 0;

        foreach ($subjectsInput as $sInput) {
            $subjectName = $sInput['subject'];
            $requestedCount = $sInput['question_count'];
            $subjectTestId = $sInput['subject_test_id'] ?? null;
            $year = $sInput['year'] ?? null;
            $inputQuestionIds = $sInput['question_ids'] ?? null;
            
            // Limit questions for non-subscribed users
            $count = $hasActiveSubscription ? $requestedCount : min($requestedCount, 5);
            
            $subjectModel = Subject::where('name', $subjectName)->first();
            if (!$subjectModel) continue;

            $query = Question::where('subject_id', $subjectModel->id);
            $resolver->applyQuestionExamTypeFilter($query, $examType);

            if ($subjectTestId) {
                $query->whereHas('subjectTests', function ($q) use ($subjectTestId) {
                    $q->where('subject_tests.id', $subjectTestId);
                });
            }

            if ($year) {
                $query->whereHas('exam', function ($q) use ($year) {
                    $q->where('year', $year);
                });
            }

            $questions = $query->inRandomOrder()
                ->with(['answers' => function($aq) { $aq->orderBy('order'); }])
                ->limit($count)
                ->get();
            
            if ($questions->isEmpty()) continue;

            // Format questions for frontend
            $formattedQuestions = $questions->map(function($q) {
                return [
                    'id' => $q->id,
                    'question_text' => $q->question_text,
                    'question_type' => $q->question_type,
                    'image' => $q->image,
                    'explanation' => $q->explanation,
                    'answers' => $q->answers->map(fn($a) => [
                        'id' => $a->id,
                        'answer_text' => $a->answer_text,
                        'order' => $a->order,
                    ])
                ];
            });

            $allQuestionsData[$subjectName] = $formattedQuestions;
            
            $processedSubjects[] = [
                'subject' => $subjectName,
                'question_count' => $questions->count(),
                'question_ids' => $questions->pluck('id')->toArray(),
            ];
            
            $totalQuestions += $questions->count();
        }

        if ($totalQuestions === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No questions found for the selected subjects.',
            ], 404);
        }

        // Create fresh attempt (users cannot continue any practice session)
        $attempt = ExamAttempt::create([
            'user_id' => $user->id,
            'exam_id' => $examId,
            'status' => 'in_progress',
            'started_at' => now(),
            'duration_minutes' => $durationMinutes,
            'total_questions' => $totalQuestions,
            'subjects' => $processedSubjects,
            'subjects_data' => $processedSubjects,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Practice session started successfully',
            'data' => [
                'attempt' => $attempt->load('exam'),
                'questions' => $allQuestionsData,
                'has_active_subscription' => $hasActiveSubscription,
            ],
        ], 201);
    }

    /**
     * Submit an answer for a question.
     */
    public function submitAnswer(Request $request, ExamAttempt $attempt)
    {
        if ($attempt->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'Exam attempt is not in progress',
            ], 400);
        }

        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer_id' => 'nullable|exists:answers,id',
            'answer_text' => 'nullable|string',
            'time_spent' => 'nullable|integer|min:0',
        ]);

        $question = Question::findOrFail($request->question_id);
        
        // Handle different question types
        if (in_array($question->question_type, ['text_input', 'numeric_input'])) {
            // For text/numeric input, we need answer_text
            if (!$request->has('answer_text') || empty($request->answer_text)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Answer text is required for text/numeric input questions',
                ], 400);
            }
            
            $userAnswerText = trim($request->answer_text);
            $expectedAnswer = trim($question->expected_answer ?? '');
            
            // Check if correct by comparing with expected_answer
            // For text input, do case-insensitive comparison
            // For numeric input, compare as numbers
            $isCorrect = false;
            if ($question->question_type === 'numeric_input') {
                // Numeric comparison
                $userNum = is_numeric($userAnswerText) ? (float)$userAnswerText : null;
                $expectedNum = is_numeric($expectedAnswer) ? (float)$expectedAnswer : null;
                $isCorrect = $userNum !== null && $expectedNum !== null && abs($userNum - $expectedNum) < 0.0001;
            } else {
                // Text comparison (case-insensitive, trimmed)
                $isCorrect = strtolower($userAnswerText) === strtolower($expectedAnswer);
            }
            
            // Find or create an answer record for this text
            $answer = $question->answers()->firstOrCreate(
                [
                    'answer_text' => $userAnswerText,
                ],
                [
                    'is_correct' => $isCorrect,
                    'order' => 'A',
                ]
            );
            
            // If answer already existed, update is_correct based on expected_answer
            if (!$answer->wasRecentlyCreated) {
                $answer->update(['is_correct' => $isCorrect]);
            }
        } else {
            // For multiple_choice and true_false, we need answer_id
            if (!$request->has('answer_id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Answer ID is required for multiple choice and true/false questions',
                ], 400);
            }
            
            $answer = $question->answers()->findOrFail($request->answer_id);
        }

        // Check if answer already exists for this question
        $existingUserAnswer = UserAnswer::where('exam_attempt_id', $attempt->id)
            ->where('question_id', $question->id)
            ->first();

        if ($existingUserAnswer) {
            // Update existing answer
            $existingUserAnswer->update([
                'answer_id' => $answer->id,
                'is_correct' => $answer->is_correct,
                'time_spent' => $request->time_spent ?? $existingUserAnswer->time_spent,
            ]);
        } else {
            // Create new answer
            UserAnswer::create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'answer_id' => $answer->id,
                'is_correct' => $answer->is_correct,
                'time_spent' => $request->time_spent ?? 0,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Answer submitted successfully',
        ]);
    }

    /**
     * Submit multiple answers in bulk.
     */
    public function submitAnswersBulk(Request $request, ExamAttempt $attempt)
    {
        if ($attempt->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'Exam attempt is not in progress',
            ], 400);
        }

        $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.answer_id' => 'nullable|exists:answers,id',
            'answers.*.answer_text' => 'nullable|string',
            'answers.*.time_spent' => 'nullable|integer|min:0',
        ]);

        $questionIds = collect($request->answers)->pluck('question_id')->unique()->toArray();
        $questions = \App\Models\Question::whereIn('id', $questionIds)->with('answers')->get()->keyBy('id');

        // Fetch existing answers to update instead of create newly
        $existingAnswers = \App\Models\UserAnswer::where('exam_attempt_id', $attempt->id)
            ->whereIn('question_id', $questionIds)
            ->get()
            ->keyBy('question_id');

        \Illuminate\Support\Facades\DB::beginTransaction();

        try {
            foreach ($request->answers as $answerData) {
                $questionId = $answerData['question_id'] ?? null;
                if (!$questionId || !$questions->has($questionId)) continue;

                $question = $questions[$questionId];
                $answerText = $answerData['answer_text'] ?? null;
                $answerId = $answerData['answer_id'] ?? null;
                $timeSpent = $answerData['time_spent'] ?? 0;

                $finalAnswer = null;

                if (in_array($question->question_type, ['text_input', 'numeric_input'])) {
                    if (empty($answerText)) continue;

                    $userAnswerText = trim($answerText);
                    $expectedAnswer = trim($question->expected_answer ?? '');

                    $isCorrect = false;
                    if ($question->question_type === 'numeric_input') {
                        $userNum = is_numeric($userAnswerText) ? (float)$userAnswerText : null;
                        $expectedNum = is_numeric($expectedAnswer) ? (float)$expectedAnswer : null;
                        $isCorrect = $userNum !== null && $expectedNum !== null && abs($userNum - $expectedNum) < 0.0001;
                    } else {
                        $isCorrect = strtolower($userAnswerText) === strtolower($expectedAnswer);
                    }

                    $finalAnswer = $question->answers()->firstOrCreate(
                        ['answer_text' => $userAnswerText],
                        ['is_correct' => $isCorrect, 'order' => 'A']
                    );

                    if (!$finalAnswer->wasRecentlyCreated) {
                        $finalAnswer->update(['is_correct' => $isCorrect]);
                    }
                } else {
                    if (!$answerId) continue;
                    // For multiple choice, we get the answer from the DB or the loaded relation
                    $finalAnswer = $question->answers->where('id', $answerId)->first();
                    if (!$finalAnswer) {
                        $finalAnswer = $question->answers()->find($answerId);
                    }
                }

                if (!$finalAnswer) continue;

                if ($existingAnswers->has($questionId)) {
                    $existing = $existingAnswers[$questionId];
                    $existing->update([
                        'answer_id' => $finalAnswer->id,
                        'is_correct' => $finalAnswer->is_correct,
                        'time_spent' => $timeSpent > 0 ? $timeSpent : $existing->time_spent,
                    ]);
                } else {
                    \App\Models\UserAnswer::create([
                        'exam_attempt_id' => $attempt->id,
                        'question_id' => $question->id,
                        'answer_id' => $finalAnswer->id,
                        'is_correct' => $finalAnswer->is_correct,
                        'time_spent' => $timeSpent,
                    ]);
                }
            }

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Answers submitted successfully',
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit answers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete an exam attempt.
     */
    public function complete(Request $request, ExamAttempt $attempt)
    {
        if ($attempt->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'Exam attempt is not in progress',
            ], 400);
        }

        // Calculate score
        $correctAnswers = UserAnswer::where('exam_attempt_id', $attempt->id)
            ->where('is_correct', true)
            ->count();

        $totalTimeSpent = UserAnswer::where('exam_attempt_id', $attempt->id)
            ->sum('time_spent');

        // Get subjects and duration from request if provided (for multi-subject exams)
        $subjects = $request->input('subjects', $attempt->subjects);
        $durationMinutes = $request->input('duration_minutes', $attempt->duration_minutes);

        // Calculate score based on exam type
        $exam = $attempt->exam;
        $totalQuestions = $attempt->total_questions;
        
        // Determine if this is a JAMB exam
        $isJamb = false;
        if ($exam && $exam->exam_type === 'JAMB') {
            $isJamb = true;
        } else {
            // For practice sessions, check the exam_type from request or metadata
            $providedExamType = $request->input('exam_type');
            if ($providedExamType) {
                $category = \App\Models\ExamCategory::where('id', $providedExamType)
                    ->orWhere('slug', $providedExamType)
                    ->first();
                if ($category && $category->slug === 'JAMB') {
                    $isJamb = true;
                }
            }
        }

        if ($isJamb && $totalQuestions > 0) {
            $score = round(($correctAnswers / $totalQuestions) * 400);
        } else {
            $score = $correctAnswers;
        }

        // Merge subjects and duration cautiously - do not overwrite rich subjects_data if it exists
        $updateData = [
            'completed_at' => now(),
            'time_spent' => $totalTimeSpent,
            'correct_answers' => $correctAnswers,
            'score' => $score,
            'status' => 'completed',
        ];

        // Merge subjects and duration cautiously - do not overwrite rich metadata
        if ($subjects !== null) {
            // Robust check for richness: does the CURRENT data have question_ids?
            $currentData = $attempt->subjects_data ?? $attempt->subjects;
            $currentIsRich = false;
            if (is_array($currentData) && count($currentData) > 0) {
                foreach ($currentData as $s) {
                    if (isset($s['question_ids']) && !empty($s['question_ids'])) {
                        $currentIsRich = true;
                        break;
                    }
                }
            }

            // Robust check for incoming data richness
            $incomingIsRich = false;
            if (is_array($subjects) && count($subjects) > 0) {
                foreach ($subjects as $s) {
                    if (is_array($s) && isset($s['question_ids']) && !empty($s['question_ids'])) {
                        $incomingIsRich = true;
                        break;
                    }
                }
            }

            // Only update if incoming is rich OR if current is NOT rich
            if ($incomingIsRich || !$currentIsRich) {
                $updateData['subjects'] = $subjects;
                // If subjects is rich, also sync to subjects_data
                if ($incomingIsRich) {
                    $updateData['subjects_data'] = $subjects;
                }
            }
        }
        
        if ($durationMinutes !== null) {
            $updateData['duration_minutes'] = $durationMinutes;
        }

        $attempt->update($updateData);

        // Record streak for today if not already recorded
        $today = Carbon::today();
        $existingStreak = UserStreak::where('user_id', auth()->id())
            ->where('date', $today)
            ->first();

        if (!$existingStreak) {
            UserStreak::create([
                'user_id' => auth()->id(),
                'date' => $today,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Exam completed successfully',
            'data' => [
                'attempt' => $attempt->fresh(),
                'percentage' => $attempt->percentage,
            ],
        ]);
    }

    /**
     * Get user's exam attempts.
     */
    public function index(Request $request)
    {
        $query = ExamAttempt::where('user_id', auth()->id())
            ->with('exam')
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('exam_id')) {
            $query->where('exam_id', $request->exam_id);
        }

        $attempts = $query->get()->map(function ($attempt) {
            return [
                'id' => $attempt->id,
                'exam' => [
                    'id' => $attempt->exam_id,
                    'title' => $attempt->exam ? $attempt->exam->title : "Practice Session",
                    'type' => $attempt->exam ? $attempt->exam->exam_type : "Practice",
                ],
                'status' => $attempt->status,
                'score' => $attempt->score,
                'correct_answers' => $attempt->correct_answers,
                'total_questions' => $attempt->total_questions,
                'percentage' => $attempt->percentage,
                'started_at' => $attempt->started_at,
                'completed_at' => $attempt->completed_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $attempts,
        ]);
    }

    /**
     * Get a specific exam attempt.
     */
    public function show(ExamAttempt $attempt)
    {
        if ($attempt->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $attempt->load(['exam', 'userAnswers.question', 'userAnswers.answer']);

        return response()->json([
            'success' => true,
            'data' => $attempt,
        ]);
    }

    /**
     * Get detailed results for an exam attempt.
     */
    public function results(ExamAttempt $attempt)
    {
        if ($attempt->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($attempt->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Exam not completed yet',
            ], 400);
        }

        // Load exam relationship for percentage calculation
        $attempt->load('exam');

        // Identify all questions that SHOULD have been answered
        $assignedQuestionIds = [];
        $subjectsData = $attempt->subjects_data;
        
        // Fallback to subjects column if subjects_data is empty
        if (empty($subjectsData)) {
            $subjectsData = $attempt->subjects;
        }
        
        $hasRichMetadata = false;
        if ($subjectsData && is_array($subjectsData)) {
            foreach ($subjectsData as $subject) {
                if (isset($subject['question_ids']) && is_array($subject['question_ids']) && !empty($subject['question_ids'])) {
                    $assignedQuestionIds = array_merge($assignedQuestionIds, $subject['question_ids']);
                    $hasRichMetadata = true;
                }
            }
        }

        // ONLY fallback to all exam questions if we have ZERO assigned question IDs and an exam_id exists
        if (!$hasRichMetadata && empty($assignedQuestionIds) && $attempt->exam_id) {
            $assignedQuestionIds = $attempt->exam->questions()->pluck('id')->toArray();
        }

        // Fetch all assigned questions with their correct answers and user's answer
        $userAnswers = $attempt->userAnswers()->get()->keyBy('question_id');
        
        $results = Question::whereIn('id', $assignedQuestionIds)
            ->with(['answers', 'subject', 'exam'])
            ->get()
            ->map(function ($question) use ($userAnswers) {
                $userAnswer = $userAnswers->get($question->id);
                $correctAnswer = $question->correctAnswer();

                // For text_input and numeric_input, get expected_answer as correct answer
                $correctAnswerData = null;
                if (in_array($question->question_type, ['text_input', 'numeric_input'])) {
                    if ($question->expected_answer) {
                        $correctAnswerData = [
                            'id' => null,
                            'answer_text' => $question->expected_answer,
                            'order' => null,
                        ];
                    }
                } else if ($correctAnswer) {
                    $correctAnswerData = [
                        'id' => $correctAnswer->id,
                        'answer_text' => $correctAnswer->answer_text,
                        'order' => $correctAnswer->order,
                    ];
                }

                // User answer data
                $userAnswerData = null;
                if ($userAnswer && $userAnswer->answer) {
                    $userAnswerData = [
                        'id' => $userAnswer->answer->id,
                        'answer_text' => $userAnswer->answer->answer_text,
                        'order' => $userAnswer->answer->order ?? null,
                    ];
                }

                // Include all answer options for multiple_choice and true_false (for corrections view)
                $questionAnswers = null;
                if (in_array($question->question_type, ['multiple_choice', 'true_false'])) {
                    $questionAnswers = $question->answers->map(fn ($a) => [
                        'id' => $a->id,
                        'answer_text' => $a->answer_text,
                        'order' => $a->order,
                        'is_correct' => $a->is_correct,
                    ])->values()->all();
                }

                return [
                    'question' => [
                        'id' => $question->id,
                        'question_text' => $question->question_text,
                        'question_type' => $question->question_type,
                        'explanation' => $question->explanation,
                        'expected_answer' => $question->expected_answer,
                        'image' => $question->image,
                        'subject' => $question->subject->name ?? ($question->exam->subject ?? null),
                        'answers' => $questionAnswers,
                    ],
                    'user_answer' => $userAnswerData,
                    'correct_answer' => $correctAnswerData,
                    'is_correct' => $userAnswer ? $userAnswer->is_correct : false,
                    'time_spent' => $userAnswer ? $userAnswer->time_spent : 0,
                ];
            })
            ->values();

        // Calculate subject-based analytics if multiple subjects
        $subjectAnalytics = [];
        if ($attempt->subjects && is_array($attempt->subjects) && count($attempt->subjects) > 0) {
            // Group results by subject based on question metadata
            $resultsBySubject = [];
            foreach ($results as $result) {
                $qSubject = trim($result['question']['subject'] ?? '');
                if ($qSubject) {
                    if (!isset($resultsBySubject[$qSubject])) {
                        $resultsBySubject[$qSubject] = [];
                    }
                    $resultsBySubject[$qSubject][] = $result;
                }
            }

            // Calculate analytics for each subject
            foreach ($attempt->subjects as $subjectData) {
                $subject = is_array($subjectData) ? ($subjectData['subject'] ?? '') : $subjectData;
                $subject = trim($subject);
                
                // Try to find the expected count in the metadata
                $expectedCount = 0;
                if (is_array($subjectData)) {
                    $expectedCount = $subjectData['question_count'] ?? ($subjectData['count'] ?? 0);
                }
                
                // Find results for this subject (case-insensitive and trimmed)
                $subjectResults = [];
                foreach ($resultsBySubject as $subjName => $items) {
                    if (strcasecmp(trim($subjName), $subject) === 0) {
                        $subjectResults = array_merge($subjectResults, $items);
                    }
                }
                
                $subjectCorrect = collect($subjectResults)->where('is_correct', true)->count();
                // Use expectedCount if available, otherwise fallback to results count
                $subjectTotal = $expectedCount > 0 ? $expectedCount : count($subjectResults);
                
                $subjectAnalytics[] = [
                    'subject' => $subject,
                    'correct' => $subjectCorrect,
                    'total' => $subjectTotal,
                    'percentage' => $subjectTotal > 0 ? round(($subjectCorrect / $subjectTotal) * 100, 2) : 0,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'attempt' => [
                    'id' => $attempt->id,
                    'score' => $attempt->score,
                    'correct_answers' => $attempt->correct_answers,
                    'total_questions' => $attempt->total_questions,
                    'percentage' => $attempt->percentage,
                    'time_spent' => $attempt->time_spent,
                    'completed_at' => $attempt->completed_at,
                    'subjects' => $attempt->subjects,
                    'duration_minutes' => $attempt->duration_minutes,
                ],
                'results' => $results,
                'subject_analytics' => $subjectAnalytics,
            ],
        ]);
    }

    /**
     * Get user analytics.
     */
    public function analytics(Request $request)
    {
        $userId = auth()->id();

        $totalAttempts = ExamAttempt::where('user_id', $userId)
            ->where('status', 'completed')
            ->count();

        $totalExams = ExamAttempt::where('user_id', $userId)
            ->where('status', 'completed')
            ->distinct('exam_id')
            ->count('exam_id');

        $averageScore = ExamAttempt::where('user_id', $userId)
            ->where('status', 'completed')
            ->selectRaw('AVG((correct_answers * 100.0) / NULLIF(total_questions, 0)) as avg_score')
            ->value('avg_score') ?? 0;

        $totalTimeSpent = ExamAttempt::where('user_id', $userId)
            ->where('status', 'completed')
            ->sum('time_spent');

        $recentAttempts = ExamAttempt::where('user_id', $userId)
            ->where('status', 'completed')
            ->with('exam')
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($attempt) {
                return [
                    'id' => $attempt->id,
                    'exam_title' => $attempt->exam ? $attempt->exam->title : "Practice Session",
                    'score' => $attempt->score,
                    'percentage' => $attempt->percentage,
                    'completed_at' => $attempt->completed_at,
                ];
            });

        $subjectPerformance = ExamAttempt::where('user_id', $userId)
            ->where('status', 'completed')
            ->join('exams', 'exam_attempts.exam_id', '=', 'exams.id')
            ->select('exams.subject')
            ->selectRaw('AVG((exam_attempts.correct_answers * 100.0) / NULLIF(exam_attempts.total_questions, 0)) as avg_score')
            ->selectRaw('COUNT(*) as attempts')
            ->whereNotNull('exams.subject')
            ->groupBy('exams.subject')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => [
                    'total_attempts' => $totalAttempts,
                    'total_exams' => $totalExams,
                    'average_score' => round($averageScore, 2),
                    'total_time_spent' => $totalTimeSpent, // in seconds
                ],
                'recent_attempts' => $recentAttempts,
                'subject_performance' => $subjectPerformance,
            ],
        ]);
    }
}
