<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
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

        // Note: type filter removed - exams are now only for past questions

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
        $examType = $request->input('exam_type');
        $type = $request->input('type', 'past_question'); // Default to past_question

        if ($type === 'practice') {
            // For practice questions, fetch from subjects table based on exam_types
            $subjects = Subject::whereHas('questions', function ($query) use ($examType) {
                if ($examType) {
                    $query->whereJsonContains('exam_types', $examType);
                }
            })
            ->where('is_active', true)
            ->pluck('name')
            ->sort()
            ->values();
        } else {
            // For past questions, fetch from exams table
            $query = Exam::where('is_active', true)
                ->whereNotNull('subject');

            // Filter by exam type
            if ($examType) {
                $query->where('exam_type', $examType);
            }

            $subjects = $query->distinct()
                ->pluck('subject')
                ->filter()
                ->sort()
                ->values();
        }

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
            'exam_type' => 'required|in:JAMB,DLI,UNILAG,GENERAL',
            'subject' => 'required|string',
            'count' => 'required|integer|min:1|max:100',
        ]);

        $examType = $request->input('exam_type');
        $subject = $request->input('subject');
        $count = $request->input('count');

        // For practice questions, fetch directly from questions table based on subject and exam_types
        // Questions can exist independently of exams for practice mode
        $subjectModel = Subject::where('name', $subject)->first();

        if (!$subjectModel) {
            return response()->json([
                'success' => false,
                'message' => "Subject '{$subject}' not found.",
                'data' => [],
            ], 404);
        }

        // Get random questions that:
        // 1. Belong to the requested subject (subject_id)
        // 2. Have the requested exam_type in their exam_types array
        // 3. Questions should be available for practice (can be standalone or linked to any exam)
        $questions = Question::where('subject_id', $subjectModel->id)
            ->whereJsonContains('exam_types', $examType)
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
            'exam_type' => 'required|in:JAMB,DLI,UNILAG,GENERAL',
            'subjects' => 'required|array|min:1',
            'subjects.*' => 'required|string',
        ]);

        // Exams are now only for past questions, so no need to filter by type
        $query = Exam::where('is_active', true)
            ->where('exam_type', $request->input('exam_type'))
            ->whereNotNull('year');

        // Filter by subjects if provided (array of subject names)
        if ($request->has('subjects') && is_array($request->subjects) && count($request->subjects) > 0) {
            $query->whereIn('subject', $request->input('subjects'));
        }

        $years = $query->distinct()
            ->pluck('year')
            ->filter(function ($year) {
                return $year !== null && $year !== '';
            })
            ->map(function ($year) {
                return (int) $year; // Ensure it's an integer
            })
            ->sortDesc()
            ->values();

        // If no years found, return empty array with success
        if ($years->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'No past questions found for the selected exam type and subjects.',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $years,
        ]);
    }
}
