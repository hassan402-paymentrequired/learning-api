<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserStreak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StreakController extends Controller
{
    /**
     * Get user's streak information.
     */
    public function index(Request $request)
    {
        $userId = auth()->id();
        
        // Get current streak count
        $currentStreak = $this->calculateCurrentStreak($userId);
        
        // Get longest streak
        $longestStreak = $this->calculateLongestStreak($userId);
        
        // Get streak days for the last 30 days (for display)
        $streakDays = $this->getStreakDays($userId, 30);
        
        // Get all streak dates for the user
        $allStreaks = UserStreak::where('user_id', $userId)
            ->orderBy('date', 'desc')
            ->get()
            ->pluck('date')
            ->map(function ($date) {
                return $date->format('Y-m-d');
            })
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'current_streak' => $currentStreak,
                'longest_streak' => $longestStreak,
                'streak_days' => $streakDays,
                'all_streaks' => $allStreaks,
            ],
        ]);
    }

    /**
     * Record a streak for today.
     */
    public function record(Request $request)
    {
        $userId = auth()->id();
        $today = Carbon::today();

        // Check if streak already exists for today
        $existingStreak = UserStreak::where('user_id', $userId)
            ->where('date', $today)
            ->first();

        if ($existingStreak) {
            return response()->json([
                'success' => true,
                'message' => 'Streak already recorded for today',
                'data' => [
                    'streak' => $existingStreak,
                ],
            ]);
        }

        // Create new streak
        $streak = UserStreak::create([
            'user_id' => $userId,
            'date' => $today,
        ]);

        // Recalculate current streak
        $currentStreak = $this->calculateCurrentStreak($userId);

        return response()->json([
            'success' => true,
            'message' => 'Streak recorded successfully',
            'data' => [
                'streak' => $streak,
                'current_streak' => $currentStreak,
            ],
        ], 201);
    }

    /**
     * Calculate current streak (consecutive days) - Duolingo style.
     * Streak resets to 0 if any day is missed.
     */
    private function calculateCurrentStreak($userId): int
    {
        $streaks = UserStreak::where('user_id', $userId)
            ->orderBy('date', 'desc')
            ->get()
            ->pluck('date')
            ->map(fn($d) => $d->format('Y-m-d'))
            ->toArray();

        if (empty($streaks)) {
            return 0;
        }

        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();
        $todayStr = $today->format('Y-m-d');
        $yesterdayStr = $yesterday->format('Y-m-d');

        // For Duolingo-style: check if user practiced today OR yesterday
        // If they practiced today, start from today
        // If they didn't practice today but practiced yesterday, start from yesterday
        $startDate = $today->copy();
        
        if (!in_array($todayStr, $streaks)) {
            // No streak today, check if there's one yesterday
            if (!in_array($yesterdayStr, $streaks)) {
                // No streak today or yesterday - streak is broken
                return 0;
            }
            // Streak yesterday but not today - start from yesterday
            $startDate = $yesterday->copy();
        }
        // If today has a streak, start from today

        // Count consecutive days backwards from start date
        // If any day is missing, streak breaks
        $currentStreak = 0;
        $checkDate = $startDate->copy();

        while (true) {
            $checkDateStr = $checkDate->format('Y-m-d');
            
            // If this date has a streak, increment counter
            if (in_array($checkDateStr, $streaks)) {
                $currentStreak++;
                $checkDate->subDay();
            } else {
                // Missing day found - streak is broken
                break;
            }

            // Safety check to prevent infinite loop (max 10 years back)
            if ($currentStreak > 3650) {
                break;
            }
        }

        return $currentStreak;
    }

    /**
     * Calculate longest streak.
     */
    private function calculateLongestStreak($userId): int
    {
        $streaks = UserStreak::where('user_id', $userId)
            ->orderBy('date', 'asc')
            ->get()
            ->pluck('date')
            ->toArray();

        if (empty($streaks)) {
            return 0;
        }

        $longestStreak = 1;
        $currentStreak = 1;

        for ($i = 1; $i < count($streaks); $i++) {
            $prevDate = Carbon::parse($streaks[$i - 1]);
            $currDate = Carbon::parse($streaks[$i]);

            if ($prevDate->diffInDays($currDate) === 1) {
                $currentStreak++;
                $longestStreak = max($longestStreak, $currentStreak);
            } else {
                $currentStreak = 1;
            }
        }

        return $longestStreak;
    }

    /**
     * Get streak days for the last N days.
     */
    private function getStreakDays($userId, $days = 30): array
    {
        $startDate = Carbon::today()->subDays($days - 1);
        $endDate = Carbon::today();

        $streaks = UserStreak::where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->pluck('date')
            ->map(function ($date) {
                return $date->format('Y-m-d');
            })
            ->toArray();

        $streakDays = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $streakDays[] = [
                'date' => $dateStr,
                'has_streak' => in_array($dateStr, $streaks),
                'day_name' => $currentDate->format('D'),
                'day_number' => $currentDate->day,
            ];
            $currentDate->addDay();
        }

        return $streakDays;
    }
}
