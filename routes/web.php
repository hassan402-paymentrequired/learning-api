<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return to_route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

    // Admin routes - require admin access
    Route::middleware([\App\Http\Middleware\EnsureUserIsAdmin::class])->prefix('admin')->name('admin.')->group(function () {
        // Users routes
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\UserController::class, 'index'])->name('index');
            Route::get('/{user}', [App\Http\Controllers\Admin\UserController::class, 'show'])->name('show');
            Route::get('/{user}/edit', [App\Http\Controllers\Admin\UserController::class, 'edit'])->name('edit');
            Route::patch('/{user}', [App\Http\Controllers\Admin\UserController::class, 'update'])->name('update');
            Route::post('/{user}/toggle-admin', [App\Http\Controllers\Admin\UserController::class, 'toggleAdmin'])->name('toggle-admin');
            Route::delete('/{user}', [App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('destroy');
        });

        // Practice Attempts routes
        Route::prefix('practice-attempts')->name('practice-attempts.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\PracticeAttemptController::class, 'index'])->name('index');
            Route::get('/{practiceAttempt}', [App\Http\Controllers\Admin\PracticeAttemptController::class, 'show'])->name('show');
            Route::delete('/{practiceAttempt}', [App\Http\Controllers\Admin\PracticeAttemptController::class, 'destroy'])->name('destroy');
        });

        // Exams routes
        Route::prefix('exams')->name('exams.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\ExamController::class, 'index'])->name('index');
            Route::get('/create', [App\Http\Controllers\Admin\ExamController::class, 'create'])->name('create');
            Route::post('/', [App\Http\Controllers\Admin\ExamController::class, 'store'])->name('store');
            Route::get('/{exam}', [App\Http\Controllers\Admin\ExamController::class, 'show'])->name('show');
            Route::get('/{exam}/edit', [App\Http\Controllers\Admin\ExamController::class, 'edit'])->name('edit');
            Route::patch('/{exam}', [App\Http\Controllers\Admin\ExamController::class, 'update'])->name('update');
            Route::post('/{exam}/toggle-active', [App\Http\Controllers\Admin\ExamController::class, 'toggleActive'])->name('toggle-active');
            Route::delete('/{exam}', [App\Http\Controllers\Admin\ExamController::class, 'destroy'])->name('destroy');
            Route::post('/{exam}/duplicate', [App\Http\Controllers\Admin\ExamController::class, 'duplicate'])->name('duplicate');
            Route::post('/bulk-update', [App\Http\Controllers\Admin\ExamController::class, 'bulkUpdate'])->name('bulk-update');
        });

        // Questions routes
        Route::prefix('questions')->name('questions.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\QuestionController::class, 'index'])->name('index');
            Route::get('/create', [App\Http\Controllers\Admin\QuestionController::class, 'create'])->name('create');
            Route::post('/', [App\Http\Controllers\Admin\QuestionController::class, 'store'])->name('store');
            Route::get('/{question}', [App\Http\Controllers\Admin\QuestionController::class, 'show'])->name('show');
            Route::get('/{question}/edit', [App\Http\Controllers\Admin\QuestionController::class, 'edit'])->name('edit');
            Route::patch('/{question}', [App\Http\Controllers\Admin\QuestionController::class, 'update'])->name('update');
            Route::post('/{question}/toggle-active', [App\Http\Controllers\Admin\QuestionController::class, 'toggleActive'])->name('toggle-active');
            Route::delete('/{question}', [App\Http\Controllers\Admin\QuestionController::class, 'destroy'])->name('destroy');
            Route::get('/sample/download', [App\Http\Controllers\Admin\QuestionController::class, 'downloadSample'])->name('sample');
            Route::post('/bulk-upload', [App\Http\Controllers\Admin\QuestionController::class, 'bulkUpload'])->name('bulk-upload');
        });

        // Subjects routes
        Route::prefix('subjects')->name('subjects.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\SubjectController::class, 'index'])->name('index');
            Route::get('/create', [App\Http\Controllers\Admin\SubjectController::class, 'create'])->name('create');
            Route::post('/', [App\Http\Controllers\Admin\SubjectController::class, 'store'])->name('store');
            Route::get('/{subject}', [App\Http\Controllers\Admin\SubjectController::class, 'show'])->name('show');
            Route::get('/{subject}/edit', [App\Http\Controllers\Admin\SubjectController::class, 'edit'])->name('edit');
            Route::patch('/{subject}', [App\Http\Controllers\Admin\SubjectController::class, 'update'])->name('update');
            Route::post('/{subject}/toggle-active', [App\Http\Controllers\Admin\SubjectController::class, 'toggleActive'])->name('toggle-active');
            Route::post('/bulk-update', [App\Http\Controllers\Admin\SubjectController::class, 'bulkUpdate'])->name('bulk-update');
            Route::delete('/{subject}', [App\Http\Controllers\Admin\SubjectController::class, 'destroy'])->name('destroy');
        });

        // Settings routes
        Route::get('settings', [App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
    });
});

require __DIR__.'/settings.php';
