<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\SecurityViolation;
use App\Support\PublicUuidLookup;

class SecurityController extends Controller
{
    /**
     * Log security violations like screenshot attempts.
     */
    public function logViolation(Request $request)
    {
        $request->validate([
            'type' => 'required|in:screenshot_attempt,context_menu,keyboard_shortcut,window_blur,window_hidden,potential_screen_recording',
            'details' => 'nullable|array',
            'attempt_uuid' => 'nullable|uuid|exists:exam_attempts,uuid',
            'url' => 'nullable|string|max:255',
            'user_agent' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $attemptId = null;
        if ($request->filled('attempt_uuid')) {
            $attempt = PublicUuidLookup::findOrFail(ExamAttempt::class, $request->input('attempt_uuid'));
            $attemptId = $attempt->id;
        }

        $violation = [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'violation_type' => $request->input('type'),
            'details' => $request->input('details', []),
            'attempt_id' => $attemptId,
            'url' => $request->input('url'),
            'user_agent' => $request->input('user_agent'),
            'ip_address' => $request->ip(),
            'timestamp' => now(),
        ];

        // Log to Laravel log file
        Log::warning('Security violation detected', $violation);

        // Optionally store in database
        try {
            SecurityViolation::create([
                'user_id' => $user->id,
                'violation_type' => $request->input('type'),
                'details' => $request->input('details', []),
                'attempt_id' => $attemptId,
                'url' => $request->input('url'),
                'user_agent' => $request->input('user_agent'),
                'ip_address' => $request->ip(),
            ]);
        } catch (\Exception $e) {
            // If SecurityViolation model doesn't exist, just log to file
            Log::info('SecurityViolation model not found, logged to file only');
        }

        // Count recent violations for this user
        $recentViolations = SecurityViolation::where('user_id', $user->id)
            ->where('created_at', '>', now()->subHour())
            ->count();

        $response = [
            'success' => true,
            'message' => 'Security violation logged',
            'data' => [
                'violation_count' => $recentViolations,
                'warning_level' => $this->getWarningLevel($recentViolations),
            ],
        ];

        // Escalate response based on violation count
        if ($recentViolations >= 10) {
            $response['data']['action'] = 'session_termination_recommended';
            $response['message'] = 'Multiple violations detected. Consider terminating session.';
        } elseif ($recentViolations >= 5) {
            $response['data']['action'] = 'warning_issued';
            $response['message'] = 'Multiple violations detected. User warned.';
        }

        return response()->json($response);
    }

    /**
     * Get current user's violation count and status.
     */
    public function getViolationStatus(Request $request)
    {
        $user = auth()->user();
        
        $todayViolations = SecurityViolation::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->count();

        $recentViolations = SecurityViolation::where('user_id', $user->id)
            ->where('created_at', '>', now()->subHour())
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'today_violations' => $todayViolations,
                'recent_violations' => $recentViolations,
                'warning_level' => $this->getWarningLevel($recentViolations),
                'status' => $recentViolations >= 10 ? 'blocked' : 'active',
            ],
        ]);
    }

    /**
     * Determine warning level based on violation count.
     */
    private function getWarningLevel(int $violationCount): string
    {
        if ($violationCount >= 10) {
            return 'critical';
        } elseif ($violationCount >= 5) {
            return 'high';
        } elseif ($violationCount >= 3) {
            return 'medium';
        } elseif ($violationCount >= 1) {
            return 'low';
        }

        return 'none';
    }
}