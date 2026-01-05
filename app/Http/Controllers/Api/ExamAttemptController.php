<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\UserAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $attempt = ExamAttempt::create([
            'user_id' => auth()->id(),
            'exam_id' => $exam->id,
            'started_at' => now(),
            'status' => 'in_progress',
            'total_questions' => $exam->questions()->count(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Exam started successfully',
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
            'answer_id' => 'required|exists:answers,id',
            'time_spent' => 'nullable|integer|min:0',
        ]);

        $question = Question::findOrFail($request->question_id);
        $answer = $question->answers()->findOrFail($request->answer_id);

        // Check if answer already exists for this question
        $existingAnswer = UserAnswer::where('exam_attempt_id', $attempt->id)
            ->where('question_id', $question->id)
            ->first();

        if ($existingAnswer) {
            // Update existing answer
            $existingAnswer->update([
                'answer_id' => $answer->id,
                'is_correct' => $answer->is_correct,
                'time_spent' => $request->time_spent ?? $existingAnswer->time_spent,
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

        $attempt->update([
            'completed_at' => now(),
            'time_spent' => $totalTimeSpent,
            'correct_answers' => $correctAnswers,
            'score' => $correctAnswers,
            'status' => 'completed',
        ]);

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

        $results = $attempt->userAnswers()
            ->with(['question.answers', 'answer'])
            ->get()
            ->map(function ($userAnswer) {
                $question = $userAnswer->question;
                $correctAnswer = $question->correctAnswer();

                return [
                    'question' => [
                        'id' => $question->id,
                        'question_text' => $question->question_text,
                        'explanation' => $question->explanation,
                        'points' => $question->points,
                    ],
                    'user_answer' => $userAnswer->answer ? [
                        'id' => $userAnswer->answer->id,
                        'answer_text' => $userAnswer->answer->answer_text,
                        'order' => $userAnswer->answer->order,
                    ] : null,
                    'correct_answer' => $correctAnswer ? [
                        'id' => $correctAnswer->id,
                        'answer_text' => $correctAnswer->answer_text,
                        'order' => $correctAnswer->order,
                    ] : null,
                    'is_correct' => $userAnswer->is_correct,
                    'time_spent' => $userAnswer->time_spent,
                ];
            });

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
                ],
                'results' => $results,
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
