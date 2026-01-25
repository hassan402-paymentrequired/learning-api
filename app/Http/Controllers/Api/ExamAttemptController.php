<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
            $attemptData['subjects'] = $subjects;
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

    /**
     * Start a new practice session.
     * This creates an exam attempt for practice questions without requiring a specific exam.
     */
    public function startPracticeSession(Request $request)
    {
        $request->validate([
            'exam_type' => 'required|in:JAMB,DLI,UNILAG,GENERAL',
            'subjects' => 'required|array|min:1',
            'subjects.*.subject' => 'required|string',
            'subjects.*.question_count' => 'required|integer|min:1|max:100',
            'duration_minutes' => 'required|integer|min:1|max:300',
        ]);

        $examType = $request->input('exam_type');
        $subjects = $request->input('subjects');
        $durationMinutes = $request->input('duration_minutes');
        
        // Calculate total questions
        $totalQuestions = collect($subjects)->sum('question_count');
        
        // Try to find an existing exam for this exam type, or create a virtual one
        $exam = Exam::where('exam_type', $examType)->where('is_active', true)->first();
        
        if (!$exam) {
            // Create a virtual/temporary exam record for practice sessions
            $exam = Exam::firstOrCreate(
                [
                    'title' => "{$examType} Practice Session",
                    'exam_type' => $examType,
                    'subject' => null, // Multi-subject practice
                    'year' => null,
                ],
                [
                    'description' => "Practice session for {$examType} questions",
                    'total_questions' => $totalQuestions,
                    'is_active' => true,
                ]
            );
        }

        // Check if user has an in-progress practice attempt
        $existingAttempt = ExamAttempt::where('user_id', auth()->id())
            ->where('exam_id', $exam->id)
            ->where('status', 'in_progress')
            ->where('created_at', '>', now()->subHours(6)) // Only check recent attempts
            ->first();

        if ($existingAttempt) {
            return response()->json([
                'success' => true,
                'message' => 'Resuming existing practice session',
                'data' => [
                    'attempt' => $existingAttempt->load('exam'),
                ],
            ]);
        }

        // Create new practice attempt
        $attemptData = [
            'user_id' => auth()->id(),
            'exam_id' => $exam->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'duration_minutes' => $durationMinutes,
            'total_questions' => $totalQuestions,
        ];

        // Add subjects data if provided (for multi-subject tracking)
        if ($subjects) {
            $attemptData['subjects_data'] = $subjects;
        }

        $attempt = ExamAttempt::create($attemptData);

        return response()->json([
            'success' => true,
            'message' => 'Practice session started successfully',
            'data' => [
                'attempt' => $attempt->load('exam'),
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
        // For JAMB (UTME), scale to 400 marks
        $exam = $attempt->exam;
        $totalQuestions = $attempt->total_questions;
        
        if ($exam && $exam->exam_type === 'JAMB' && $totalQuestions > 0) {
            // JAMB scoring: (correct_answers / total_questions) * 400
            $score = round(($correctAnswers / $totalQuestions) * 400);
        } else {
            // For other exams, use raw score
            $score = $correctAnswers;
        }

        $updateData = [
            'completed_at' => now(),
            'time_spent' => $totalTimeSpent,
            'correct_answers' => $correctAnswers,
            'score' => $score,
            'status' => 'completed',
        ];

        // Only update subjects and duration if provided
        if ($subjects !== null) {
            $updateData['subjects'] = $subjects;
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
                    'id' => $attempt->exam->id,
                    'title' => $attempt->exam->title,
                    'type' => $attempt->exam->type,
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

        $results = $attempt->userAnswers()
            ->with(['question.answers', 'answer', 'question.exam'])
            ->get()
            ->map(function ($userAnswer) {
                $question = $userAnswer->question;
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
                if ($userAnswer->answer) {
                    $userAnswerData = [
                        'id' => $userAnswer->answer->id,
                        'answer_text' => $userAnswer->answer->answer_text,
                        'order' => $userAnswer->answer->order ?? null,
                    ];
                }

                return [
                    'question' => [
                        'id' => $question->id,
                        'question_text' => $question->question_text,
                        'question_type' => $question->question_type,
                        'explanation' => $question->explanation,
                        'expected_answer' => $question->expected_answer,
                    ],
                    'user_answer' => $userAnswerData,
                    'correct_answer' => $correctAnswerData,
                    'is_correct' => $userAnswer->is_correct,
                    'time_spent' => $userAnswer->time_spent,
                ];
            });

        // Calculate subject-based analytics if multiple subjects
        $subjectAnalytics = [];
        if ($attempt->subjects && is_array($attempt->subjects) && count($attempt->subjects) > 0) {
            // Group results by subject based on exam subject
            $resultsBySubject = [];
            foreach ($results as $result) {
                // Get the exam for this question to find its subject
                $question = \App\Models\Question::with('exam')->find($result['question']['id']);
                if ($question && $question->exam) {
                    $examSubject = $question->exam->subject;
                    if (!isset($resultsBySubject[$examSubject])) {
                        $resultsBySubject[$examSubject] = [];
                    }
                    $resultsBySubject[$examSubject][] = $result;
                }
            }

            // Calculate analytics for each subject
            foreach ($attempt->subjects as $subjectData) {
                $subject = is_array($subjectData) ? $subjectData['subject'] : $subjectData;
                $subjectResults = $resultsBySubject[$subject] ?? [];
                
                $subjectCorrect = collect($subjectResults)->where('is_correct', true)->count();
                $subjectTotal = count($subjectResults);
                
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
                    'exam_title' => $attempt->exam->title,
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
