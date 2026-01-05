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
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // Admin routes
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('exams', App\Http\Controllers\Admin\ExamController::class);
        Route::resource('exams.questions', App\Http\Controllers\Admin\QuestionController::class)->except(['index', 'show']);
        Route::get('exams/{exam}/questions', [App\Http\Controllers\Admin\QuestionController::class, 'index'])->name('exams.questions.index');
        Route::get('exams/{exam}/questions/sample', [App\Http\Controllers\Admin\QuestionController::class, 'downloadSample'])->name('exams.questions.sample');
        Route::post('exams/{exam}/questions/bulk-upload', [App\Http\Controllers\Admin\QuestionController::class, 'bulkUpload'])->name('exams.questions.bulk-upload');
    });
});

require __DIR__.'/settings.php';
