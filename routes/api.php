<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\ExamAttemptController;
use App\Http\Controllers\Api\StreakController;
use App\Http\Controllers\Api\AnnouncementController;

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

// Protected routes
Route::middleware('auth:api')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    // Exam routes
    Route::get('/exams', [ExamController::class, 'index']);
    Route::get('/exams/subjects', [ExamController::class, 'subjects']);
    Route::get('/exams/years', [ExamController::class, 'getAvailableYears']);
    Route::get('/exams/{exam}', [ExamController::class, 'show']);
    Route::get('/exams/{exam}/questions', [ExamController::class, 'questions']);
    Route::get('/questions/practice', [ExamController::class, 'getPracticeQuestions']);

    // Exam attempt routes
    Route::post('/exams/{exam}/start', [ExamAttemptController::class, 'start']);
    Route::post('/exam-attempts/{attempt}/submit-answer', [ExamAttemptController::class, 'submitAnswer']);
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
});
