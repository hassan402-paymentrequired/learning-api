<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\ExamAttemptController;
use App\Http\Controllers\Api\StreakController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SubscriptionPinController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProfileController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Email verification (public routes)
Route::post('/email-verification/send-otp', [EmailVerificationController::class, 'sendOtp']);
Route::post('/email-verification/verify-otp', [EmailVerificationController::class, 'verifyOtp']);
Route::post('/email-verification/resend-otp', [EmailVerificationController::class, 'resendOtp']);

// Password reset (public routes)
Route::post('/password-reset/send-otp', [PasswordResetController::class, 'sendOtp']);
Route::post('/password-reset/verify-otp', [PasswordResetController::class, 'verifyOtp']);
Route::post('/password-reset/reset', [PasswordResetController::class, 'resetPassword']);
Route::post('/password-reset/resend-otp', [PasswordResetController::class, 'resendOtp']);

// Paystack callback and cancel (public routes - called by Paystack)
Route::get('/subscriptions/callback', [SubscriptionController::class, 'callback']);
Route::get('/subscriptions/cancel', [SubscriptionController::class, 'cancel']);

// Protected routes - allow /me and /logout without email verification (needed for verification flow)
Route::middleware('auth:api')->group(function () {
    // Auth routes (accessible even without email verification)
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

});

// Protected routes requiring email verification
Route::middleware(['auth:api', \App\Http\Middleware\EnsureEmailIsVerified::class])->group(function () {
    // Exam routes
    Route::get('/exams', [ExamController::class, 'index']);
    Route::get('/exams/subjects', [ExamController::class, 'subjects']);
    Route::get('/exams/years', [ExamController::class, 'getAvailableYears']);
    Route::get('/exams/{exam}', [ExamController::class, 'show']);
    Route::get('/exams/{exam}/questions', [ExamController::class, 'questions']);
    Route::get('/questions/practice', [ExamController::class, 'getPracticeQuestions']);
    
    // Department routes (for Unilag/DLI practice flow)
    Route::get('/departments', [ExamController::class, 'departments']);
    Route::get('/departments/{department}/subjects', [ExamController::class, 'departmentSubjects']);

    // Exam attempt routes
    Route::post('/exams/{exam}/start', [ExamAttemptController::class, 'start']);
    Route::post('/practice/start', [ExamAttemptController::class, 'startPracticeSession']);
    
    // Security violation logging
    Route::post('/security/violations', [App\Http\Controllers\Api\SecurityController::class, 'logViolation']);
    Route::get('/security/violations/status', [App\Http\Controllers\Api\SecurityController::class, 'getViolationStatus']);
    Route::post('/exam-attempts/{attempt}/submit-answer', [ExamAttemptController::class, 'submitAnswer']);
    Route::post('/exam-attempts/{attempt}/submit-answers-bulk', [ExamAttemptController::class, 'submitAnswersBulk']);
    Route::post('/exam-attempts/{attempt}/complete', [ExamAttemptController::class, 'complete']);
    Route::get('/exam-attempts', [ExamAttemptController::class, 'index']);
    Route::get('/exam-attempts/{attempt}', [ExamAttemptController::class, 'show']);
    Route::get('/exam-attempts/{attempt}/results', [ExamAttemptController::class, 'results']);
    Route::get('/analytics', [ExamAttemptController::class, 'analytics']);

    // Streak routes
    Route::get('/streaks', [StreakController::class, 'index']);
    Route::post('/streaks/record', [StreakController::class, 'record']);

    // Announcement routes
    Route::get('/announcements', [AnnouncementController::class, 'index']);

    // Subscription routes
    Route::get('/subscriptions/plans', [SubscriptionController::class, 'plans']);
    Route::get('/subscriptions/status', [SubscriptionController::class, 'status']);
    Route::post('/subscriptions/initialize-payment', [SubscriptionController::class, 'initializePayment']);
    Route::post('/subscriptions/verify-payment', [SubscriptionController::class, 'verifyPayment']);
    Route::post('/subscriptions/register-device', [SubscriptionController::class, 'registerDevice']);
    Route::post('/subscriptions/redeem-pin', [SubscriptionPinController::class, 'redeem']);

    // Referral routes
    Route::get('/referrals', [ReferralController::class, 'index']);
    Route::get('/referrals/code', [ReferralController::class, 'code']);
    Route::get('/referrals/balance', [ReferralController::class, 'balance']);

    // Leaderboard routes
    Route::get('/leaderboard', [LeaderboardController::class, 'index']);
    Route::get('/leaderboard/my-rank', [LeaderboardController::class, 'myRank']);

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
});
