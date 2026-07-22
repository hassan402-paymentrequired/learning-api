<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use Carbon\Carbon;
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
        if ($limit === 10 && !$examType && $type === 'all_time') {
            return $this->getTopPerformersForHome();
        }

        $query = ExamAttempt::query()
            ->select(
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

        $this->applyTimeFilter($query, $type);
        $this->applyExamTypeFilter($query, $examType);

        $leaderboard = $query
            ->orderBy('total_score', 'desc')
            ->orderBy('average_score', 'desc')
            ->limit($limit)
            ->with('user:id,name,email,uuid')
            ->get()
            ->map(function ($item, $index) {
                return $this->formatEntry($item->user, $item, $index + 1);
            })
            ->filter()
            ->values();

        $userRank = null;
        if ($currentUser) {
            $userStatsResult = $this->userStatsQuery($currentUser->id, $type, $examType)->first();

            if ($userStatsResult && $userStatsResult->total_attempts > 0) {
                $usersAbove = $this->rankQuery($type, $examType)
                    ->havingRaw('SUM(score) > ?', [(int) $userStatsResult->total_score])
                    ->count();

                $userRank = $this->formatEntry($currentUser, $userStatsResult, $usersAbove + 1);
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

        $userStatsResult = $this->userStatsQuery($user->id, $type, $examType)->first();

        if (!$userStatsResult || $userStatsResult->total_attempts === 0) {
            return response()->json([
                'success' => true,
                'data' => [
                    'rank' => null,
                    'message' => 'No completed exams found.',
                ],
            ]);
        }

        $usersAbove = $this->rankQuery($type, $examType)
            ->havingRaw('SUM(score) > ?', [(int) $userStatsResult->total_score])
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'rank' => $usersAbove + 1,
                'statistics' => $this->formatStatistics($userStatsResult),
            ],
        ]);
    }

    /**
     * Top performers for home: recent high-accuracy completed attempts (including practice).
     */
    private function getTopPerformersForHome()
    {
        $practiceAttempts = ExamAttempt::query()
            ->where('status', 'completed')
            ->where('total_questions', '>', 0)
            ->with(['user:id,name,email,uuid'])
            ->orderByDesc('completed_at')
            ->limit(200)
            ->get();

        $topPerformers = [];
        $seenUserIds = [];

        foreach ($practiceAttempts as $attempt) {
            if (in_array($attempt->user_id, $seenUserIds, true) || !$attempt->user) {
                continue;
            }

            $percentage = $attempt->percentage;
            if ($percentage < 70) {
                continue;
            }

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

            if (count($topPerformers) >= 10) {
                break;
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

    private function userStatsQuery(int $userId, string $type, ?string $examType)
    {
        $query = ExamAttempt::query()
            ->select(
                DB::raw('COUNT(*) as total_attempts'),
                DB::raw('SUM(score) as total_score'),
                DB::raw('SUM(correct_answers) as total_correct'),
                DB::raw('SUM(total_questions) as total_questions'),
                DB::raw('AVG(score) as average_score'),
                DB::raw('MAX(score) as highest_score')
            )
            ->where('user_id', $userId)
            ->where('status', 'completed');

        $this->applyTimeFilter($query, $type);
        $this->applyExamTypeFilter($query, $examType);

        return $query;
    }

    private function rankQuery(string $type, ?string $examType)
    {
        $query = ExamAttempt::query()
            ->select('user_id', DB::raw('SUM(score) as total_score'))
            ->where('status', 'completed')
            ->groupBy('user_id');

        $this->applyTimeFilter($query, $type);
        $this->applyExamTypeFilter($query, $examType);

        return $query;
    }

    private function applyTimeFilter($query, string $type): void
    {
        $now = Carbon::now('Africa/Lagos');

        if ($type === 'monthly') {
            $query->where('completed_at', '>=', $now->copy()->startOfMonth()->utc());
        } elseif ($type === 'weekly') {
            $query->where('completed_at', '>=', $now->copy()->startOfWeek()->utc());
        }
    }

    /**
     * Match formal exams and practice attempts (exam_id null) by stored exam_type.
     */
    private function applyExamTypeFilter($query, ?string $examType): void
    {
        if (!$examType) {
            return;
        }

        $candidates = $this->examTypeCandidates($examType);
        $lowerCandidates = array_map('strtolower', $candidates);

        $query->where(function ($outer) use ($lowerCandidates) {
            $outer->where(function ($q) use ($lowerCandidates) {
                foreach ($lowerCandidates as $candidate) {
                    $q->orWhereRaw('LOWER(exam_attempts.exam_type) = ?', [$candidate]);
                }
            })->orWhereHas('exam', function ($eq) use ($lowerCandidates) {
                $eq->where(function ($inner) use ($lowerCandidates) {
                    foreach ($lowerCandidates as $candidate) {
                        $inner->orWhereRaw('LOWER(exam_type) = ?', [$candidate]);
                    }
                });
            });
        });
    }

    /**
     * @return array<int, string>
     */
    private function examTypeCandidates(string $examType): array
    {
        return match (strtoupper($examType)) {
            'JAMB' => ['JAMB', 'jamb'],
            'DLI' => ['DLI', 'dli', 'unilag-dli'],
            'UNILAG' => ['UNILAG', 'unilag', 'unilag-dli', 'unilag-post-utme', 'unilag-post-ume'],
            'GENERAL' => ['GENERAL', 'general'],
            default => [$examType, strtolower($examType), strtoupper($examType)],
        };
    }

    private function formatEntry($user, $stats, int $rank): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'rank' => $rank,
            'user' => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'statistics' => $this->formatStatistics($stats),
        ];
    }

    private function formatStatistics($stats): array
    {
        return [
            'total_score' => (int) $stats->total_score,
            'total_attempts' => (int) $stats->total_attempts,
            'average_score' => round((float) $stats->average_score, 2),
            'highest_score' => (int) $stats->highest_score,
            'total_correct' => (int) $stats->total_correct,
            'total_questions' => (int) $stats->total_questions,
            'accuracy' => $stats->total_questions > 0
                ? round(($stats->total_correct / $stats->total_questions) * 100, 2)
                : 0,
        ];
    }
}
