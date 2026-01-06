<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $query = User::withCount('examAttempts');

        // Search
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        // Filter by registration date
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => $request->only(['search', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $user->loadCount('examAttempts');

        // Get user statistics
        $stats = [
            'total_attempts' => $user->examAttempts()->count(),
            'completed_attempts' => $user->examAttempts()->where('status', 'completed')->count(),
            'average_score' => $user->examAttempts()
                ->where('status', 'completed')
                ->selectRaw('AVG((correct_answers * 100.0) / NULLIF(total_questions, 0)) as avg_score')
                ->value('avg_score') ?? 0,
            'total_time_spent' => $user->examAttempts()
                ->where('status', 'completed')
                ->sum('time_spent'),
        ];

        // Get practice history
        $practiceHistory = $user->examAttempts()
            ->with('exam')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($attempt) {
                return [
                    'id' => $attempt->id,
                    'exam_title' => $attempt->exam->title,
                    'exam_type' => $attempt->exam->exam_type,
                    'status' => $attempt->status,
                    'score' => $attempt->score,
                    'correct_answers' => $attempt->correct_answers,
                    'total_questions' => $attempt->total_questions,
                    'percentage' => $attempt->percentage,
                    'started_at' => $attempt->started_at,
                    'completed_at' => $attempt->completed_at,
                ];
            });

        // Get subject performance
        $subjectPerformance = $user->examAttempts()
            ->where('status', 'completed')
            ->join('exams', 'exam_attempts.exam_id', '=', 'exams.id')
            ->select('exams.subject')
            ->selectRaw('AVG((exam_attempts.correct_answers * 100.0) / NULLIF(exam_attempts.total_questions, 0)) as avg_score')
            ->selectRaw('COUNT(*) as attempts')
            ->whereNotNull('exams.subject')
            ->groupBy('exams.subject')
            ->get();

        return Inertia::render('admin/users/show', [
            'user' => $user,
            'stats' => $stats,
            'practiceHistory' => $practiceHistory,
            'subjectPerformance' => $subjectPerformance,
        ]);
    }
}
