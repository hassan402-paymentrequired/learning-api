<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Aggregate practice-history analytics for the authenticated user:
     * an overview summary, a daily accuracy trend, and a per-subject breakdown.
     *
     * Deliberately avoids ExamAttemptController::results()'s per-attempt
     * question reconstruction — that's fine for a single attempt but would be
     * an N+1-style disaster run across a user's entire history. Instead this
     * aggregates directly with SQL joins over user_answers/questions/subjects.
     */
    public function practiceHistory()
    {
        $userId = auth()->id();

        $completedAttempts = ExamAttempt::where('user_id', $userId)
            ->where('status', 'completed');

        $overview = [
            'total_attempts' => (clone $completedAttempts)->count(),
            'average_accuracy' => round((float) (clone $completedAttempts)
                ->selectRaw('AVG((correct_answers * 100.0) / NULLIF(total_questions, 0)) as avg')
                ->value('avg') ?? 0, 2),
            'total_time_spent' => (int) (clone $completedAttempts)->sum('time_spent'),
            'total_exams' => (clone $completedAttempts)->distinct('exam_id')->count('exam_id'),
        ];

        $trend = (clone $completedAttempts)
            ->where('completed_at', '>=', now()->subDays(30))
            ->selectRaw(
                'DATE(completed_at) as date, COUNT(*) as attempts,
                ROUND(AVG((correct_answers * 100.0) / NULLIF(total_questions, 0)), 2) as average_accuracy,
                SUM(total_questions) as questions_answered'
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $subjectBreakdown = DB::table('user_answers')
            ->join('exam_attempts', 'user_answers.exam_attempt_id', '=', 'exam_attempts.id')
            ->join('questions', 'user_answers.question_id', '=', 'questions.id')
            ->leftJoin('subjects', 'questions.subject_id', '=', 'subjects.id')
            ->where('exam_attempts.user_id', $userId)
            ->where('exam_attempts.status', 'completed')
            ->selectRaw(
                "COALESCE(subjects.name, 'Other') as subject,
                COUNT(*) as total,
                SUM(user_answers.is_correct) as correct"
            )
            ->groupBy('subject')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                return [
                    'subject' => $row->subject,
                    'total' => (int) $row->total,
                    'correct' => (int) $row->correct,
                    'accuracy' => $row->total > 0 ? round(($row->correct / $row->total) * 100, 2) : 0,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => $overview,
                'trend' => $trend,
                'subject_breakdown' => $subjectBreakdown,
            ],
        ]);
    }
}
