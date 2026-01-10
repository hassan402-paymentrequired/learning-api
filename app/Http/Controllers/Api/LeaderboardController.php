<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    /**
     * Get leaderboard rankings.
     */
    public function index(Request $request)
    {
        $request->validate([
            'type' => 'nullable|in:all_time,monthly,weekly',
            'exam_type' => 'nullable|in:JAMB,DLI,UNILAG,GENERAL',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $type = $request->input('type', 'all_time');
        $examType = $request->input('exam_type');
        $limit = $request->input('limit', 50);
        $currentUser = auth()->user();

        // Build query based on time period
        $query = ExamAttempt::select(
                'user_id',
                DB::raw('COUNT(*) as total_attempts'),
                DB::raw('SUM(score) as total_score'),
                DB::raw('SUM(correct_answers) as total_correct'),
                DB::raw('SUM(total_questions) as total_questions'),
                DB::raw('AVG(score) as average_score'),
                DB::raw('MAX(score) as highest_score')
            )
            ->where('status', 'completed')
            ->groupBy('user_id');

        // Filter by time period
        if ($type === 'monthly') {
            $query->where('completed_at', '>=', now()->startOfMonth());
        } elseif ($type === 'weekly') {
            $query->where('completed_at', '>=', now()->startOfWeek());
        }

        // Filter by exam type if provided
        if ($examType) {
            $query->whereHas('exam', function ($q) use ($examType) {
                $q->where('exam_type', $examType);
            });
        }

        // Get top users
        $leaderboard = $query
            ->orderBy('total_score', 'desc')
            ->orderBy('average_score', 'desc')
            ->limit($limit)
            ->with('user:id,name,email')
            ->get()
            ->map(function ($item, $index) use ($examType) {
                return [
                    'rank' => $index + 1,
                    'user' => [
                        'id' => $item->user->id,
                        'name' => $item->user->name,
                        'email' => $item->user->email,
                    ],
                    'statistics' => [
                        'total_score' => (int) $item->total_score,
                        'total_attempts' => (int) $item->total_attempts,
                        'average_score' => round((float) $item->average_score, 2),
                        'highest_score' => (int) $item->highest_score,
                        'total_correct' => (int) $item->total_correct,
                        'total_questions' => (int) $item->total_questions,
                        'accuracy' => $item->total_questions > 0 
                            ? round(($item->total_correct / $item->total_questions) * 100, 2) 
                            : 0,
                    ],
                ];
            });

        // Get current user's rank
        $userRank = null;
        if ($currentUser) {
            $userStats = ExamAttempt::select(
                    DB::raw('COUNT(*) as total_attempts'),
                    DB::raw('SUM(score) as total_score'),
                    DB::raw('SUM(correct_answers) as total_correct'),
                    DB::raw('SUM(total_questions) as total_questions'),
                    DB::raw('AVG(score) as average_score'),
                    DB::raw('MAX(score) as highest_score')
                )
                ->where('user_id', $currentUser->id)
                ->where('status', 'completed');

            if ($type === 'monthly') {
                $userStats->where('completed_at', '>=', now()->startOfMonth());
            } elseif ($type === 'weekly') {
                $userStats->where('completed_at', '>=', now()->startOfWeek());
            }

            if ($examType) {
                $userStats->whereHas('exam', function ($q) use ($examType) {
                    $q->where('exam_type', $examType);
                });
            }

            $userStatsResult = $userStats->first();

            if ($userStatsResult && $userStatsResult->total_attempts > 0) {
                // Calculate user's rank
                $rankQuery = ExamAttempt::select('user_id', DB::raw('SUM(score) as total_score'))
                    ->where('status', 'completed');

                if ($type === 'monthly') {
                    $rankQuery->where('completed_at', '>=', now()->startOfMonth());
                } elseif ($type === 'weekly') {
                    $rankQuery->where('completed_at', '>=', now()->startOfWeek());
                }

                if ($examType) {
                    $rankQuery->whereHas('exam', function ($q) use ($examType) {
                        $q->where('exam_type', $examType);
                    });
                }

                $usersAbove = $rankQuery
                    ->groupBy('user_id')
                    ->havingRaw('SUM(score) > ?', [(int) $userStatsResult->total_score])
                    ->count();

                $userRank = [
                    'rank' => $usersAbove + 1,
                    'user' => [
                        'id' => $currentUser->id,
                        'name' => $currentUser->name,
                        'email' => $currentUser->email,
                    ],
                    'statistics' => [
                        'total_score' => (int) $userStatsResult->total_score,
                        'total_attempts' => (int) $userStatsResult->total_attempts,
                        'average_score' => round((float) $userStatsResult->average_score, 2),
                        'highest_score' => (int) $userStatsResult->highest_score,
                        'total_correct' => (int) $userStatsResult->total_correct,
                        'total_questions' => (int) $userStatsResult->total_questions,
                        'accuracy' => $userStatsResult->total_questions > 0 
                            ? round(($userStatsResult->total_correct / $userStatsResult->total_questions) * 100, 2) 
                            : 0,
                    ],
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'type' => $type,
                'exam_type' => $examType,
                'leaderboard' => $leaderboard,
                'current_user' => $userRank,
            ],
        ]);
    }

    /**
     * Get current user's rank.
     */
    public function myRank(Request $request)
    {
        $request->validate([
            'type' => 'nullable|in:all_time,monthly,weekly',
            'exam_type' => 'nullable|in:JAMB,DLI,UNILAG,GENERAL',
        ]);

        $user = auth()->user();
        $type = $request->input('type', 'all_time');
        $examType = $request->input('exam_type');

        $userStats = ExamAttempt::select(
                DB::raw('COUNT(*) as total_attempts'),
                DB::raw('SUM(score) as total_score'),
                DB::raw('SUM(correct_answers) as total_correct'),
                DB::raw('SUM(total_questions) as total_questions'),
                DB::raw('AVG(score) as average_score'),
                DB::raw('MAX(score) as highest_score')
            )
            ->where('user_id', $user->id)
            ->where('status', 'completed');

        if ($type === 'monthly') {
            $userStats->where('completed_at', '>=', now()->startOfMonth());
        } elseif ($type === 'weekly') {
            $userStats->where('completed_at', '>=', now()->startOfWeek());
        }

        if ($examType) {
            $userStats->whereHas('exam', function ($q) use ($examType) {
                $q->where('exam_type', $examType);
            });
        }

        $userStatsResult = $userStats->first();

        if (!$userStatsResult || $userStatsResult->total_attempts === 0) {
            return response()->json([
                'success' => true,
                'data' => [
                    'rank' => null,
                    'message' => 'No completed exams found.',
                ],
            ]);
        }

        // Calculate rank
        $rankQuery = ExamAttempt::select('user_id', DB::raw('SUM(score) as total_score'))
            ->where('status', 'completed');

        if ($type === 'monthly') {
            $rankQuery->where('completed_at', '>=', now()->startOfMonth());
        } elseif ($type === 'weekly') {
            $rankQuery->where('completed_at', '>=', now()->startOfWeek());
        }

        if ($examType) {
            $rankQuery->whereHas('exam', function ($q) use ($examType) {
                $q->where('exam_type', $examType);
            });
        }

        $usersAbove = $rankQuery
            ->groupBy('user_id')
            ->havingRaw('SUM(score) > ?', [(int) $userStatsResult->total_score])
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'rank' => $usersAbove + 1,
                'statistics' => [
                    'total_score' => (int) $userStatsResult->total_score,
                    'total_attempts' => (int) $userStatsResult->total_attempts,
                    'average_score' => round((float) $userStatsResult->average_score, 2),
                    'highest_score' => (int) $userStatsResult->highest_score,
                    'total_correct' => (int) $userStatsResult->total_correct,
                    'total_questions' => (int) $userStatsResult->total_questions,
                    'accuracy' => $userStatsResult->total_questions > 0 
                        ? round(($userStatsResult->total_correct / $userStatsResult->total_questions) * 100, 2) 
                        : 0,
                ],
            ],
        ]);
    }
}
