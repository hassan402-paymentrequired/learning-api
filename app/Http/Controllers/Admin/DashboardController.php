<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Statistics
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'total_exams' => Exam::count(),
            'active_exams' => Exam::where('is_active', true)->count(),
            'total_questions' => Question::count(),
            'total_attempts' => ExamAttempt::count(),
            'completed_attempts' => ExamAttempt::where('status', 'completed')->count(),
            'average_score' => ExamAttempt::where('status', 'completed')
                ->selectRaw('AVG((correct_answers * 100.0) / NULLIF(total_questions, 0)) as avg_score')
                ->value('avg_score') ?? 0,
        ];

        // Recent activity
        $recentAttempts = ExamAttempt::with(['user', 'exam'])
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($attempt) {
                return [
                    'id' => $attempt->id,
                    'user_name' => $attempt->user->name,
                    'exam_title' => $attempt->exam->title,
                    'score' => $attempt->score,
                    'percentage' => $attempt->percentage,
                    'completed_at' => $attempt->completed_at->diffForHumans(),
                ];
            });

        $recentUsers = User::orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at->diffForHumans(),
                ];
            });

        $recentExams = Exam::orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($exam) {
                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'type' => $exam->type,
                    'exam_type' => $exam->exam_type,
                    'created_at' => $exam->created_at->diffForHumans(),
                ];
            });

        // Performance metrics
        $topExams = ExamAttempt::where('status', 'completed')
            ->join('exams', 'exam_attempts.exam_id', '=', 'exams.id')
            ->select('exams.id', 'exams.title', DB::raw('COUNT(*) as attempts'), DB::raw('AVG((exam_attempts.correct_answers * 100.0) / NULLIF(exam_attempts.total_questions, 0)) as avg_score'))
            ->groupBy('exams.id', 'exams.title')
            ->orderBy('attempts', 'desc')
            ->limit(5)
            ->get();

        $subjectPerformance = ExamAttempt::where('status', 'completed')
            ->join('exams', 'exam_attempts.exam_id', '=', 'exams.id')
            ->select('exams.subject', DB::raw('COUNT(*) as attempts'), DB::raw('AVG((exam_attempts.correct_answers * 100.0) / NULLIF(exam_attempts.total_questions, 0)) as avg_score'))
            ->whereNotNull('exams.subject')
            ->groupBy('exams.subject')
            ->orderBy('attempts', 'desc')
            ->get();

        return Inertia::render('dashboard', [
            'stats' => $stats,
            'recentAttempts' => $recentAttempts,
            'recentUsers' => $recentUsers,
            'recentExams' => $recentExams,
            'topExams' => $topExams,
            'subjectPerformance' => $subjectPerformance,
        ]);
    }
}
