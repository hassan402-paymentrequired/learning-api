<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

    // Admin routes
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', App\Http\Controllers\Admin\UserController::class)->only(['index', 'show']);
        Route::resource('practice-attempts', App\Http\Controllers\Admin\PracticeAttemptController::class)->only(['index', 'show']);
        Route::resource('exams', App\Http\Controllers\Admin\ExamController::class);
        Route::post('exams/{exam}/duplicate', [App\Http\Controllers\Admin\ExamController::class, 'duplicate'])->name('exams.duplicate');
        Route::post('exams/bulk-update', [App\Http\Controllers\Admin\ExamController::class, 'bulkUpdate'])->name('exams.bulk-update');
        Route::resource('exams.questions', App\Http\Controllers\Admin\QuestionController::class)->except(['index', 'show']);
        Route::get('exams/{exam}/questions', [App\Http\Controllers\Admin\QuestionController::class, 'index'])->name('exams.questions.index');
        Route::get('exams/{exam}/questions/sample', [App\Http\Controllers\Admin\QuestionController::class, 'downloadSample'])->name('exams.questions.sample');
        Route::post('exams/{exam}/questions/bulk-upload', [App\Http\Controllers\Admin\QuestionController::class, 'bulkUpload'])->name('exams.questions.bulk-upload');
        Route::get('questions', [App\Http\Controllers\Admin\QuestionController::class, 'all'])->name('questions.index');
    });
});

require __DIR__.'/settings.php';
