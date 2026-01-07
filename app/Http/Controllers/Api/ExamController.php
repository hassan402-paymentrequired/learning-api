<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Question;
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

    /**
     * Get random practice questions by exam_type and subject.
     * Used for practice mode where questions are randomly selected.
     */
    public function getPracticeQuestions(Request $request)
    {
        $request->validate([
            'exam_type' => 'required|in:JAMB,DLI',
            'subject' => 'required|string',
            'count' => 'required|integer|min:1|max:100',
        ]);

        $examType = $request->input('exam_type');
        $subject = $request->input('subject');
        $count = $request->input('count');

        // Get random questions from practice exams matching criteria
        $questions = Question::whereHas('exam', function ($query) use ($subject) {
            $query->where('type', 'practice')
                  ->where('subject', $subject)
                  ->where('is_active', true);
        })
        ->where(function ($query) use ($examType) {
            // Question must have this exam_type in its exam_types array
            // Or if exam_types is null, fallback to exam's exam_type
            $query->whereJsonContains('exam_types', $examType)
                  ->orWhere(function ($q) use ($examType) {
                      $q->whereNull('exam_types')
                        ->whereHas('exam', function ($examQuery) use ($examType) {
                            $examQuery->where('exam_type', $examType);
                        });
                  });
        })
        ->inRandomOrder()
        ->limit($count)
        ->with(['answers' => function ($query) {
            $query->select('id', 'question_id', 'answer_text', 'order')
                  ->orderBy('order');
        }])
        ->get()
        ->map(function ($question, $index) {
            return [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'points' => $question->points,
                'order' => $index + 1, // Reorder from 1
                'answers' => $question->answers->map(function ($answer) {
                    return [
                        'id' => $answer->id,
                        'answer_text' => $answer->answer_text,
                        'order' => $answer->order,
                    ];
                }),
            ];
        });

        if ($questions->count() < $count) {
            return response()->json([
                'success' => true,
                'data' => $questions,
                'warning' => "Only {$questions->count()} questions available (requested {$count})",
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $questions,
        ]);
    }

    /**
     * Get available years for past questions.
     * Used to show year selection for past question mode.
     */
    public function getAvailableYears(Request $request)
    {
        $request->validate([
            'exam_type' => 'required|in:JAMB,DLI',
            'subject' => 'nullable|string',
            'type' => 'required|in:past_question',
        ]);

        $query = Exam::where('is_active', true)
            ->where('type', 'past_question')
            ->where('exam_type', $request->input('exam_type'))
            ->whereNotNull('year');

        // Filter by subject if provided
        if ($request->has('subject') && $request->subject) {
            $query->where('subject', $request->subject);
        }

        $years = $query->distinct()
            ->pluck('year')
            ->filter()
            ->sortDesc()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $years,
        ]);
    }
}
