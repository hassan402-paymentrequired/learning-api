<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuestionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Exam $exam)
    {
        $questions = $exam->questions()
            ->with('answers')
            ->orderBy('order')
            ->get();

        return Inertia::render('admin/questions/index', [
            'exam' => $exam,
            'questions' => $questions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Exam $exam)
    {
        return Inertia::render('admin/questions/create', [
            'exam' => $exam,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice',
            'explanation' => 'nullable|string',
            'points' => 'required|integer|min:1',
            'order' => 'required|integer|min:1',
            'answers' => 'required|array|min:2',
            'answers.*.answer_text' => 'required|string',
            'answers.*.is_correct' => 'required|boolean',
            'answers.*.order' => 'required|string|in:A,B,C,D,E',
        ]);

        // Ensure at least one correct answer
        $hasCorrectAnswer = collect($validated['answers'])->contains('is_correct', true);
        if (!$hasCorrectAnswer) {
            return back()->withErrors(['answers' => 'At least one answer must be marked as correct.']);
        }

        $question = $exam->questions()->create([
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'explanation' => $validated['explanation'] ?? null,
            'points' => $validated['points'],
            'order' => $validated['order'],
        ]);

        foreach ($validated['answers'] as $answerData) {
            $question->answers()->create([
                'answer_text' => $answerData['answer_text'],
                'is_correct' => $answerData['is_correct'],
                'order' => $answerData['order'],
            ]);
        }

        // Update exam total_questions count
        $exam->update([
            'total_questions' => $exam->questions()->count(),
        ]);

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'Question created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Exam $exam, Question $question)
    {
        $question->load('answers');

        return Inertia::render('admin/questions/edit', [
            'exam' => $exam,
            'question' => $question,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Exam $exam, Question $question)
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice',
            'explanation' => 'nullable|string',
            'points' => 'required|integer|min:1',
            'order' => 'required|integer|min:1',
            'answers' => 'required|array|min:2',
            'answers.*.id' => 'nullable|exists:answers,id',
            'answers.*.answer_text' => 'required|string',
            'answers.*.is_correct' => 'required|boolean',
            'answers.*.order' => 'required|string|in:A,B,C,D,E',
        ]);

        // Ensure at least one correct answer
        $hasCorrectAnswer = collect($validated['answers'])->contains('is_correct', true);
        if (!$hasCorrectAnswer) {
            return back()->withErrors(['answers' => 'At least one answer must be marked as correct.']);
        }

        $question->update([
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'explanation' => $validated['explanation'] ?? null,
            'points' => $validated['points'],
            'order' => $validated['order'],
        ]);

        // Get existing answer IDs
        $existingAnswerIds = $question->answers()->pluck('id')->toArray();
        $submittedAnswerIds = collect($validated['answers'])->pluck('id')->filter()->toArray();

        // Delete answers that are no longer in the request
        $answersToDelete = array_diff($existingAnswerIds, $submittedAnswerIds);
        if (!empty($answersToDelete)) {
            $question->answers()->whereIn('id', $answersToDelete)->delete();
        }

        // Update or create answers
        foreach ($validated['answers'] as $answerData) {
            if (isset($answerData['id']) && in_array($answerData['id'], $existingAnswerIds)) {
                // Update existing answer
                $question->answers()->where('id', $answerData['id'])->update([
                    'answer_text' => $answerData['answer_text'],
                    'is_correct' => $answerData['is_correct'],
                    'order' => $answerData['order'],
                ]);
            } else {
                // Create new answer
                $question->answers()->create([
                    'answer_text' => $answerData['answer_text'],
                    'is_correct' => $answerData['is_correct'],
                    'order' => $answerData['order'],
                ]);
            }
        }

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'Question updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Exam $exam, Question $question)
    {
        $question->delete();

        // Update exam total_questions count
        $exam->update([
            'total_questions' => $exam->questions()->count(),
        ]);

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'Question deleted successfully.');
    }

    /**
     * Download sample CSV template for bulk upload.
     */
    public function downloadSample(Exam $exam)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="questions_template.csv"',
        ];

        $callback = function() use ($exam) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Headers
            fputcsv($file, [
                'Question Text',
                'Answer A',
                'Answer B',
                'Answer C',
                'Answer D',
                'Answer E (Optional)',
                'Correct Answer (A/B/C/D/E)',
                'Explanation (Optional)',
                'Points',
                'Order'
            ]);

            // Sample rows
            fputcsv($file, [
                'What is 2 + 2?',
                '3',
                '4',
                '5',
                '6',
                '',
                'B',
                'Basic addition: 2 + 2 = 4',
                '1',
                '1'
            ]);

            fputcsv($file, [
                'Which of the following is a prime number?',
                '4',
                '5',
                '6',
                '8',
                '',
                'B',
                'A prime number is only divisible by 1 and itself. 5 meets this criteria.',
                '1',
                '2'
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Handle bulk upload of questions from CSV.
     */
    public function bulkUpload(Request $request, Exam $exam)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        
        // Skip BOM if present
        $firstLine = fgets($handle);
        if (substr($firstLine, 0, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        } else {
            fseek($handle, 3);
        }

        // Skip header row
        $header = fgetcsv($handle);
        
        $imported = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Validate row has minimum required columns
            if (count($row) < 7) {
                $errors[] = "Row {$rowNumber}: Insufficient columns. Expected at least 7 columns.";
                continue;
            }

            $questionText = trim($row[0] ?? '');
            $answerA = trim($row[1] ?? '');
            $answerB = trim($row[2] ?? '');
            $answerC = trim($row[3] ?? '');
            $answerD = trim($row[4] ?? '');
            $answerE = trim($row[5] ?? '');
            $correctAnswer = strtoupper(trim($row[6] ?? ''));
            $explanation = trim($row[7] ?? '');
            $points = intval($row[8] ?? 1);
            $order = intval($row[9] ?? $exam->questions()->max('order') + 1);

            // Validation
            if (empty($questionText)) {
                $errors[] = "Row {$rowNumber}: Question text is required.";
                continue;
            }

            if (empty($answerA) || empty($answerB) || empty($answerC) || empty($answerD)) {
                $errors[] = "Row {$rowNumber}: At least 4 answers (A, B, C, D) are required.";
                continue;
            }

            if (!in_array($correctAnswer, ['A', 'B', 'C', 'D', 'E'])) {
                $errors[] = "Row {$rowNumber}: Correct answer must be A, B, C, D, or E.";
                continue;
            }

            if ($correctAnswer === 'E' && empty($answerE)) {
                $errors[] = "Row {$rowNumber}: Answer E is marked as correct but is empty.";
                continue;
            }

            if ($points < 1) {
                $points = 1;
            }

            try {
                // Create question
                $question = $exam->questions()->create([
                    'question_text' => $questionText,
                    'question_type' => 'multiple_choice',
                    'explanation' => $explanation ?: null,
                    'points' => $points,
                    'order' => $order,
                ]);

                // Create answers
                $answers = [
                    ['text' => $answerA, 'order' => 'A', 'correct' => $correctAnswer === 'A'],
                    ['text' => $answerB, 'order' => 'B', 'correct' => $correctAnswer === 'B'],
                    ['text' => $answerC, 'order' => 'C', 'correct' => $correctAnswer === 'C'],
                    ['text' => $answerD, 'order' => 'D', 'correct' => $correctAnswer === 'D'],
                ];

                if (!empty($answerE)) {
                    $answers[] = ['text' => $answerE, 'order' => 'E', 'correct' => $correctAnswer === 'E'];
                }

                foreach ($answers as $answerData) {
                    $question->answers()->create([
                        'answer_text' => $answerData['text'],
                        'is_correct' => $answerData['correct'],
                        'order' => $answerData['order'],
                    ]);
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNumber}: Error - " . $e->getMessage();
            }
        }

        fclose($handle);

        // Update exam total_questions count
        $exam->update([
            'total_questions' => $exam->questions()->count(),
        ]);

        if ($imported > 0) {
            return redirect()->route('admin.exams.show', $exam)
                ->with('success', "Successfully imported {$imported} question(s).")
                ->with('import_errors', $errors);
        } else {
            return redirect()->route('admin.exams.show', $exam)
                ->withErrors(['bulk_upload' => 'No questions were imported. Please check your file format.'])
                ->with('import_errors', $errors);
        }
    }

    /**
     * Display all questions across all exams (Question Bank).
     */
    public function all(Request $request)
    {
        $query = Question::with(['exam', 'answers']);

        // Search
        if ($request->has('search')) {
            $query->where('question_text', 'like', '%' . $request->search . '%');
        }

        // Filter by exam
        if ($request->has('exam_id')) {
            $query->where('exam_id', $request->exam_id);
        }

        // Filter by exam type
        if ($request->has('exam_type')) {
            $query->whereHas('exam', function ($q) use ($request) {
                $q->where('exam_type', $request->exam_type);
            });
        }

        $questions = $query->orderBy('created_at', 'desc')->paginate(20);

        // Get all exams for filter
        $exams = Exam::select('id', 'title', 'exam_type')->orderBy('title')->get();

        return Inertia::render('admin/questions/all', [
            'questions' => $questions,
            'exams' => $exams,
            'filters' => $request->only(['search', 'exam_id', 'exam_type']),
        ]);
    }
}
