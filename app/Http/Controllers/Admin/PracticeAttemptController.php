<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PracticeAttemptController extends Controller
{
    /**
     * Display a listing of practice attempts.
     */
    public function index(Request $request)
    {
        $query = ExamAttempt::with(['user', 'exam']);

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by exam
        if ($request->has('exam_id')) {
            $query->where('exam_id', $request->exam_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $attempts = $query->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('admin/practice-attempts/index', [
            'attempts' => $attempts,
            'filters' => $request->only(['user_id', 'exam_id', 'status', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Display the specified practice attempt.
     */
    public function show($id)
    {
        $practiceAttempt = ExamAttempt::findOrFail($id);
        $practiceAttempt->load([
            'user',
            'exam',
            'userAnswers.question.answers',
            'userAnswers.answer'
        ]);

        // Get detailed results
        $results = $practiceAttempt->userAnswers->map(function ($userAnswer) {
            $question = $userAnswer->question;
            $correctAnswer = $question->answers()->where('is_correct', true)->first();

            return [
                'question' => [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'explanation' => $question->explanation,
                    'points' => $question->points,
                    'order' => $question->order,
                ],
                'user_answer' => $userAnswer->answer ? [
                    'id' => $userAnswer->answer->id,
                    'answer_text' => $userAnswer->answer->answer_text,
                    'order' => $userAnswer->answer->order,
                ] : null,
                'correct_answer' => $correctAnswer ? [
                    'id' => $correctAnswer->id,
                    'answer_text' => $correctAnswer->answer_text,
                    'order' => $correctAnswer->order,
                ] : null,
                'is_correct' => $userAnswer->is_correct,
                'time_spent' => $userAnswer->time_spent,
            ];
        });

        return Inertia::render('admin/practice-attempts/show', [
            'attempt' => $practiceAttempt,
            'results' => $results,
        ]);
    }
}
