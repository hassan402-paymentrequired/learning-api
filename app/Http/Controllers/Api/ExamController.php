<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    /**
     * Get list of available exams.
     */
    public function index(Request $request)
    {
        $query = Exam::where('is_active', true)
            ->withCount('questions');

        // Filter by exam type
        if ($request->has('exam_type')) {
            $query->where('exam_type', $request->exam_type);
        }

        // Filter by type (practice or past_question)
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by subject
        if ($request->has('subject')) {
            $query->where('subject', $request->subject);
        }

        // Filter by year (for past questions)
        if ($request->has('year')) {
            $query->where('year', $request->year);
        }

        $exams = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $exams,
        ]);
    }

    /**
     * Get a specific exam.
     */
    public function show(Exam $exam)
    {
        if (!$exam->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found',
            ], 404);
        }

        $exam->loadCount('questions');

        return response()->json([
            'success' => true,
            'data' => $exam,
        ]);
    }

    /**
     * Get questions for an exam (without correct answers).
     */
    public function questions(Exam $exam)
    {
        if (!$exam->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found',
            ], 404);
        }

        $questions = $exam->questions()
            ->with(['answers' => function ($query) {
                $query->select('id', 'question_id', 'answer_text', 'order')
                    ->orderBy('order');
            }])
            ->orderBy('order')
            ->get()
            ->map(function ($question) {
                return [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'points' => $question->points,
                    'order' => $question->order,
                    'answers' => $question->answers->map(function ($answer) {
                        return [
                            'id' => $answer->id,
                            'answer_text' => $answer->answer_text,
                            'order' => $answer->order,
                        ];
                    }),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'exam' => [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'duration' => $exam->duration,
                    'total_questions' => $exam->questions_count,
                ],
                'questions' => $questions,
            ],
        ]);
    }

    /**
     * Get list of available subjects for an exam type.
     */
    public function subjects(Request $request)
    {
        $query = Exam::where('is_active', true)
            ->whereNotNull('subject');

        // Filter by exam type
        if ($request->has('exam_type')) {
            $query->where('exam_type', $request->exam_type);
        }

        // Filter by type (practice or past_question)
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $subjects = $query->distinct()
            ->pluck('subject')
            ->filter()
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $subjects,
        ]);
    }
}
