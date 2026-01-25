<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ExamQuestionController extends Controller
{
    /**
     * Show the form for creating a new question for an exam.
     */
    public function create(Exam $exam)
    {
        $subjects = Subject::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('admin/exams/questions/create', [
            'exam' => $exam,
            'subjects' => $subjects,
        ]);
    }

    /**
     * Store a newly created question for an exam.
     */
    public function store(Request $request, Exam $exam)
    {
        Log::info('Exam question creation request', [
            'exam_id' => $exam->id,
            'data' => $request->all(),
        ]);

        // First validate question_type to determine conditional rules
        $request->validate([
            'question_type' => 'required|in:multiple_choice,text_input,numeric_input,true_false',
        ]);

        // Base validation rules
        $rules = [
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,text_input,numeric_input,true_false',
            'explanation' => 'nullable|string',
        ];

        // Conditional validation based on question type
        if ($request->question_type === 'multiple_choice') {
            $rules['answers'] = 'required|array|min:2';
            $rules['answers.*.answer_text'] = 'required|string';
            $rules['answers.*.is_correct'] = 'required|boolean';
            $rules['answers.*.order'] = 'required|string|in:A,B,C,D,E';
        } else {
            $rules['expected_answer'] = 'required|string';
            $rules['answers'] = 'prohibited';
        }

        $validated = $request->validate($rules);

        try {
            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('questions', 'public');
            }

            // Determine exam_types from exam
            $examTypes = [$exam->exam_type];

            $question = Question::create([
                'exam_id' => $exam->id,
                'subject_id' => $exam->subject ? Subject::where('name', $exam->subject)->first()?->id : null,
                'question_text' => $validated['question_text'],
                'image' => $imagePath,
                'question_type' => $validated['question_type'],
                'explanation' => $validated['explanation'] ?? null,
                'expected_answer' => $validated['expected_answer'] ?? null,
                'exam_types' => $examTypes,
                'is_active' => true,
            ]);

            Log::info('Exam question created successfully', [
                'question_id' => $question->id,
                'exam_id' => $exam->id,
            ]);

            if ($validated['question_type'] === 'multiple_choice') {
                foreach ($validated['answers'] as $answerData) {
                    $question->answers()->create([
                        'answer_text' => $answerData['answer_text'],
                        'is_correct' => $answerData['is_correct'],
                        'order' => $answerData['order'],
                    ]);
                }
            }

            // Update exam total_questions
            $exam->update([
                'total_questions' => $exam->questions()->count(),
            ]);

            return redirect()->route('admin.exams.show', $exam)
                ->with('success', 'Question created successfully.');
        } catch (\Exception $e) {
            Log::error('Error creating exam question', [
                'exam_id' => $exam->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to create question: ' . $e->getMessage()]);
        }
    }

    /**
     * Show the form for editing a question for an exam.
     */
    public function edit(Exam $exam, Question $question)
    {
        // Ensure question belongs to exam
        if ($question->exam_id !== $exam->id) {
            abort(404);
        }

        $question->load('answers');
        $subjects = Subject::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('admin/exams/questions/edit', [
            'exam' => $exam,
            'question' => $question,
            'subjects' => $subjects,
        ]);
    }

    /**
     * Update a question for an exam.
     */
    public function update(Request $request, Exam $exam, Question $question)
    {
        // Ensure question belongs to exam
        if ($question->exam_id !== $exam->id) {
            abort(404);
        }

        // First validate question_type to determine conditional rules
        $request->validate([
            'question_type' => 'required|in:multiple_choice,text_input,numeric_input,true_false',
        ]);

        // Base validation rules
        $rules = [
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,text_input,numeric_input,true_false',
            'explanation' => 'nullable|string',
        ];

        // Conditional validation based on question type
        if ($request->question_type === 'multiple_choice') {
            $rules['answers'] = 'required|array|min:2';
            $rules['answers.*.id'] = 'nullable|exists:answers,id';
            $rules['answers.*.answer_text'] = 'required|string';
            $rules['answers.*.is_correct'] = 'required|boolean';
            $rules['answers.*.order'] = 'required|string|in:A,B,C,D,E';
        } else {
            $rules['expected_answer'] = 'required|string';
            $rules['answers'] = 'prohibited';
        }

        $validated = $request->validate($rules);

        try {
            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($question->image) {
                    Storage::disk('public')->delete($question->image);
                }
                $imagePath = $request->file('image')->store('questions', 'public');
                $validated['image'] = $imagePath;
            } else {
                // Keep existing image if no new image uploaded
                $validated['image'] = $question->image;
            }

            // Update question
            $question->update([
                'question_text' => $validated['question_text'],
                'image' => $validated['image'],
                'question_type' => $validated['question_type'],
                'explanation' => $validated['explanation'] ?? null,
                'expected_answer' => $validated['expected_answer'] ?? null,
            ]);

            // Handle answers for multiple choice
            if ($validated['question_type'] === 'multiple_choice') {
                $existingAnswerIds = $question->answers()->pluck('id')->toArray();
                $submittedAnswerIds = array_filter(array_column($validated['answers'], 'id'));

                // Delete answers that were removed
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
            } else {
                // Delete all answers for non-multiple-choice questions
                $question->answers()->delete();
            }

            return redirect()->route('admin.exams.show', $exam)
                ->with('success', 'Question updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error updating exam question', [
                'question_id' => $question->id,
                'exam_id' => $exam->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to update question: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove a question from an exam.
     */
    public function destroy(Exam $exam, Question $question)
    {
        // Ensure question belongs to exam
        if ($question->exam_id !== $exam->id) {
            abort(404);
        }

        // Delete image if exists
        if ($question->image) {
            Storage::disk('public')->delete($question->image);
        }

        $question->delete();

        // Update exam total_questions
        $exam->update([
            'total_questions' => $exam->questions()->count(),
        ]);

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'Question deleted successfully.');
    }

    /**
     * Download sample CSV file for bulk upload.
     */
    public function downloadSample(Exam $exam, Request $request)
    {
        $questionType = $request->query('question_type', 'multiple_choice');

        $filename = "exam_{$exam->id}_questions_sample_{$questionType}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($questionType, $exam) {
            $file = fopen('php://output', 'w');

            // Add BOM for Excel compatibility
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if ($questionType === 'text_input' || $questionType === 'numeric_input') {
                fputcsv($file, [
                    'Question Text',
                    'Expected Answer',
                    'Alternative Answers (comma-separated, optional)',
                    'Explanation (Optional)',
                ]);

                fputcsv($file, [
                    'What is the capital city of Nigeria?',
                    'Abuja',
                    'abuja,ABUJA',
                    'Abuja became the capital of Nigeria in 1991, replacing Lagos.',
                ]);
            } else if ($questionType === 'true_false') {
                fputcsv($file, [
                    'Question Text',
                    'Expected Answer (true/false)',
                    'Explanation (Optional)',
                ]);

                fputcsv($file, [
                    'The sum of 2 and 2 equals 4.',
                    'true',
                    'This is a basic arithmetic fact: 2 + 2 = 4.',
                ]);
            } else {
                fputcsv($file, [
                    'Question Text',
                    'Answer A',
                    'Answer B',
                    'Answer C',
                    'Answer D',
                    'Answer E (Optional)',
                    'Correct Answer (A/B/C/D/E)',
                    'Explanation (Optional)',
                ]);

                fputcsv($file, [
                    'What is 2 + 2?',
                    '3',
                    '4',
                    '5',
                    '6',
                    '',
                    'B',
                    'Basic addition: 2 + 2 = 4',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Handle bulk upload of questions for an exam.
     */
    public function bulkUpload(Request $request, Exam $exam)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
            'question_type' => 'required|in:multiple_choice,text_input,numeric_input,true_false',
        ]);

        $questionType = $request->input('question_type');
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

            try {
                $questionText = trim($row[0] ?? '');

                if (empty($questionText)) {
                    $errors[] = "Row {$rowNumber}: Question text is required.";
                    continue;
                }

                $explanation = trim($row[$questionType === 'multiple_choice' ? 7 : ($questionType === 'true_false' ? 2 : 3)] ?? '');

                if ($questionType === 'multiple_choice') {
                    $answerA = trim($row[1] ?? '');
                    $answerB = trim($row[2] ?? '');
                    $answerC = trim($row[3] ?? '');
                    $answerD = trim($row[4] ?? '');
                    $answerE = trim($row[5] ?? '');
                    $correctAnswer = strtoupper(trim($row[6] ?? ''));

                    if (empty($answerA) || empty($answerB) || empty($answerC) || empty($answerD)) {
                        $errors[] = "Row {$rowNumber}: At least 4 answers (A, B, C, D) are required.";
                        continue;
                    }

                    if (!in_array($correctAnswer, ['A', 'B', 'C', 'D', 'E'])) {
                        $errors[] = "Row {$rowNumber}: Correct answer must be A, B, C, D, or E.";
                        continue;
                    }

                    $question = Question::create([
                        'exam_id' => $exam->id,
                        'subject_id' => $exam->subject ? Subject::where('name', $exam->subject)->first()?->id : null,
                        'question_text' => $questionText,
                        'question_type' => 'multiple_choice',
                        'explanation' => $explanation ?: null,
                        'exam_types' => [$exam->exam_type],
                        'is_active' => true,
                    ]);

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
                } else {
                    $expectedAnswer = trim($row[1] ?? '');

                    if (empty($expectedAnswer)) {
                        $errors[] = "Row {$rowNumber}: Expected answer is required.";
                        continue;
                    }

                    $question = Question::create([
                        'exam_id' => $exam->id,
                        'subject_id' => $exam->subject ? Subject::where('name', $exam->subject)->first()?->id : null,
                        'question_text' => $questionText,
                        'question_type' => $questionType,
                        'expected_answer' => $expectedAnswer,
                        'explanation' => $explanation ?: null,
                        'exam_types' => [$exam->exam_type],
                        'is_active' => true,
                    ]);
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNumber}: Error - " . $e->getMessage();
                Log::error('Bulk upload error', [
                    'row_number' => $rowNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        fclose($handle);

        // Update exam total_questions
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
}
