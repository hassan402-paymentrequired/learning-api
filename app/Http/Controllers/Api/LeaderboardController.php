<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Support\PublicId;
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

        // Special handling for home page top performers (limit = 10, no exam_type)
        // Return 10 recent users who took practice and got about 70% or above
        if ($limit === 10 && !$examType && $type === 'all_time') {
            return $this->getTopPerformersForHome();
        }

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
            ->with('user:id,name,email,uuid')
            ->get()
            ->map(function ($item, $index) use ($examType) {
                return [
                    'rank' => $index + 1,
                    'user' => [
                        'uuid' => $item->user->uuid,
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
                        'uuid' => $currentUser->uuid,
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

    /**
     * Get top performers for home page.
     * Returns 10 recent users who took practice sessions and scored about 70% or above.
     */
    private function getTopPerformersForHome()
    {
        // Get practice sessions (exams with "Practice Session" in title)
        $practiceAttempts = ExamAttempt::where('status', 'completed')
            ->whereHas('exam', function ($q) {
                $q->where('title', 'LIKE', '%Practice Session%');
            })
            ->with(['user:id,name,email,uuid', 'exam:id,title,exam_type,uuid'])
            ->orderBy('completed_at', 'desc')
            ->get();

        // Filter for users who scored 70% or above and get unique users
        $topPerformers = [];
        $seenUserIds = [];

        foreach ($practiceAttempts as $attempt) {
            // Skip if we've already seen this user
            if (in_array($attempt->user_id, $seenUserIds)) {
                continue;
            }

            // Calculate percentage score using the model's percentage attribute
            $percentage = $attempt->percentage;

            // Only include users who scored 70% or above
            if ($percentage >= 70) {
                $seenUserIds[] = $attempt->user_id;
                
                $topPerformers[] = [
                    'rank' => count($topPerformers) + 1,
                    'user' => [
                        'uuid' => $attempt->user->uuid,
                        'name' => $attempt->user->name,
                        'email' => $attempt->user->email,
                    ],
                    'statistics' => [
                        'total_score' => (int) $attempt->score,
                        'total_attempts' => 1,
                        'average_score' => round((float) $attempt->score, 2),
                        'highest_score' => (int) $attempt->score,
                        'total_correct' => (int) $attempt->correct_answers,
                        'total_questions' => (int) $attempt->total_questions,
                        'accuracy' => $percentage,
                    ],
                ];

                // Stop when we have 10 users
                if (count($topPerformers) >= 10) {
                    break;
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'type' => 'all_time',
                'exam_type' => null,
                'leaderboard' => $topPerformers,
                'current_user' => null,
            ],
        ]);
    }
}
