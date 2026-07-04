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

            // Subscription management routes
            Route::post('/{user}/generate-pin', [App\Http\Controllers\Admin\UserController::class, 'generatePin'])->name('generate-pin');
            Route::delete('/{user}/pins/{pin}', [App\Http\Controllers\Admin\UserController::class, 'cancelPin'])->name('cancel-pin');
            Route::post('/{user}/toggle-subscription', [App\Http\Controllers\Admin\UserController::class, 'toggleSubscription'])->name('toggle-subscription');
            Route::post('/{user}/set-expiry', [App\Http\Controllers\Admin\UserController::class, 'setExpiry'])->name('set-expiry');
            Route::post('/{user}/set-type', [App\Http\Controllers\Admin\UserController::class, 'setSubscriptionType'])->name('set-type');
        });

        // Subscription settings routes
        Route::get('subscription-settings', [App\Http\Controllers\Admin\SubscriptionSettingsController::class, 'index'])->name('subscription-settings.index');
        Route::post('subscription-settings', [App\Http\Controllers\Admin\SubscriptionSettingsController::class, 'update'])->name('subscription-settings.update');
        Route::post('subscription-settings/apply-global-expiry', [App\Http\Controllers\Admin\SubscriptionSettingsController::class, 'applyGlobalExpiry'])->name('subscription-settings.apply-global-expiry');

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

            // Exam Questions routes
            Route::prefix('{exam}/questions')->name('questions.')->group(function () {
                Route::get('/create', [App\Http\Controllers\Admin\ExamQuestionController::class, 'create'])->name('create');
                Route::post('/', [App\Http\Controllers\Admin\ExamQuestionController::class, 'store'])->name('store');
                Route::get('/{question}/edit', [App\Http\Controllers\Admin\ExamQuestionController::class, 'edit'])->name('edit');
                Route::patch('/{question}', [App\Http\Controllers\Admin\ExamQuestionController::class, 'update'])->name('update');
                Route::delete('/{question}', [App\Http\Controllers\Admin\ExamQuestionController::class, 'destroy'])->name('destroy');
                Route::post('/{question}/link', [App\Http\Controllers\Admin\ExamQuestionController::class, 'link'])->name('link');
                Route::get('/sample', [App\Http\Controllers\Admin\ExamQuestionController::class, 'downloadSample'])->name('sample');
                Route::post('/bulk-upload', [App\Http\Controllers\Admin\ExamQuestionController::class, 'bulkUpload'])->name('bulkUpload');
            });
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

        // Departments routes
        Route::prefix('departments')->name('departments.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\DepartmentController::class, 'index'])->name('index');
            Route::get('/create', [App\Http\Controllers\Admin\DepartmentController::class, 'create'])->name('create');
            Route::post('/', [App\Http\Controllers\Admin\DepartmentController::class, 'store'])->name('store');
            Route::get('/{department}/courses/{subject}', [App\Http\Controllers\Admin\DepartmentController::class, 'showCourse'])->name('courses.show');
            Route::get('/{department}/edit', [App\Http\Controllers\Admin\DepartmentController::class, 'edit'])->name('edit');
            Route::get('/{department}', [App\Http\Controllers\Admin\DepartmentController::class, 'show'])->name('show');
            Route::patch('/{department}', [App\Http\Controllers\Admin\DepartmentController::class, 'update'])->name('update');
            Route::post('/{department}/toggle-active', [App\Http\Controllers\Admin\DepartmentController::class, 'toggleActive'])->name('toggle-active');
            Route::delete('/{department}', [App\Http\Controllers\Admin\DepartmentController::class, 'destroy'])->name('destroy');
        });

        // Exam Categories routes
        Route::prefix('exam-categories')->name('exam-categories.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\ExamCategoryController::class, 'index'])->name('index');
            Route::get('/create', [App\Http\Controllers\Admin\ExamCategoryController::class, 'create'])->name('create');
            Route::post('/', [App\Http\Controllers\Admin\ExamCategoryController::class, 'store'])->name('store');
            Route::get('/{examCategory}/edit', [App\Http\Controllers\Admin\ExamCategoryController::class, 'edit'])->name('edit');
            Route::patch('/{examCategory}', [App\Http\Controllers\Admin\ExamCategoryController::class, 'update'])->name('update');
            Route::post('/{examCategory}/toggle-active', [App\Http\Controllers\Admin\ExamCategoryController::class, 'toggleActive'])->name('toggle-active');
            Route::delete('/{examCategory}', [App\Http\Controllers\Admin\ExamCategoryController::class, 'destroy'])->name('destroy');
        });

        // Subjects routes
        Route::prefix('subjects')->name('subjects.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\SubjectController::class, 'index'])->name('index');
            Route::get('/create', [App\Http\Controllers\Admin\SubjectController::class, 'create'])->name('create');
            Route::post('/', [App\Http\Controllers\Admin\SubjectController::class, 'store'])->name('store');
            Route::get('/{subject}/tests', [App\Http\Controllers\Admin\SubjectTestController::class, 'index'])->name('tests.index');
            Route::get('/{subject}/tests/create', [App\Http\Controllers\Admin\SubjectTestController::class, 'create'])->name('tests.create');
            Route::post('/{subject}/tests', [App\Http\Controllers\Admin\SubjectTestController::class, 'store'])->name('tests.store');
            Route::get('/{subject}/tests/{subject_test}/edit', [App\Http\Controllers\Admin\SubjectTestController::class, 'edit'])->name('tests.edit');
            Route::patch('/{subject}/tests/{subject_test}', [App\Http\Controllers\Admin\SubjectTestController::class, 'update'])->name('tests.update');
            Route::delete('/{subject}/tests/{subject_test}', [App\Http\Controllers\Admin\SubjectTestController::class, 'destroy'])->name('tests.destroy');
            Route::get('/{subject}', [App\Http\Controllers\Admin\SubjectController::class, 'show'])->name('show');
            Route::get('/{subject}/edit', [App\Http\Controllers\Admin\SubjectController::class, 'edit'])->name('edit');
            Route::patch('/{subject}', [App\Http\Controllers\Admin\SubjectController::class, 'update'])->name('update');
            Route::post('/{subject}/toggle-active', [App\Http\Controllers\Admin\SubjectController::class, 'toggleActive'])->name('toggle-active');
            Route::post('/bulk-update', [App\Http\Controllers\Admin\SubjectController::class, 'bulkUpdate'])->name('bulk-update');
            Route::get('/{subject}/questions/sample', [App\Http\Controllers\Admin\SubjectController::class, 'downloadSample'])->name('questions.sample');
            Route::post('/{subject}/questions/bulk-upload', [App\Http\Controllers\Admin\SubjectController::class, 'bulkUpload'])->name('questions.bulk-upload');
            Route::delete('/{subject}', [App\Http\Controllers\Admin\SubjectController::class, 'destroy'])->name('destroy');
        });

        // Settings routes
        Route::get('settings', [App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
    });
});

require __DIR__.'/settings.php';
