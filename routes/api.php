<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\ExamAttemptController;
use App\Http\Controllers\Api\StreakController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SubscriptionPinController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ExamCategoryController;
use App\Http\Controllers\Api\MarketingUnsubscribeController;
use App\Http\Controllers\Api\NotificationSettingsController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\DevicePushTokenController;
use App\Http\Controllers\Api\WaitlistController;
use App\Http\Controllers\Api\AppVersionController;

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
Route::get('/app-version', [AppVersionController::class, 'show']);

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

Route::post('/waitlist', [WaitlistController::class, 'store']);

Route::get('/push/vapid-public-key', [PushSubscriptionController::class, 'vapidPublicKey']);

Route::get('/marketing/unsubscribe', MarketingUnsubscribeController::class)
    ->name('marketing.unsubscribe');

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
    Route::get('/exam-categories', [ExamCategoryController::class, 'index']);
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
    Route::get('/exam-attempts/{attempt}/resume', [ExamAttemptController::class, 'resume']);
    Route::get('/exam-attempts/{attempt}', [ExamAttemptController::class, 'show']);
    Route::get('/exam-attempts/{attempt}/results', [ExamAttemptController::class, 'results']);
    Route::get('/analytics', [ExamAttemptController::class, 'analytics']);
    Route::get('/analytics/practice-history', [AnalyticsController::class, 'practiceHistory']);

    // Streak routes
    Route::get('/streaks', [StreakController::class, 'index']);
    Route::post('/streaks/record', [StreakController::class, 'record']);

    // Announcement routes
    Route::get('/announcements', [AnnouncementController::class, 'index']);

    // Campaign routes (marquee, countdown, popup)
    Route::get('/campaigns', [CampaignController::class, 'index']);

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
    Route::get('/referrals/withdrawals', [ReferralController::class, 'withdrawals']);
    Route::post('/referrals/withdraw', [ReferralController::class, 'withdraw']);

    // Leaderboard routes
    Route::get('/leaderboard', [LeaderboardController::class, 'index']);
    Route::get('/leaderboard/my-rank', [LeaderboardController::class, 'myRank']);

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    // Push notifications
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy']);
    Route::post('/device-push-tokens', [DevicePushTokenController::class, 'store']);
    Route::delete('/device-push-tokens', [DevicePushTokenController::class, 'destroy']);
    Route::get('/notification-settings', [NotificationSettingsController::class, 'show']);
    Route::put('/notification-settings', [NotificationSettingsController::class, 'update']);
});
