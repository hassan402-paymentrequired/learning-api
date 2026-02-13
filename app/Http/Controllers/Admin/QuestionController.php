<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class QuestionController extends Controller
{
    /**
     * Display a listing of all questions (standalone).
     */
    public function index(Request $request)
    {
        $query = Question::with(['subject', 'exam', 'answers']);

        // Search
        if ($request->has('search')) {
            $query->where('question_text', 'like', '%' . $request->search . '%');
        }

        // Filter by subject
        if ($request->has('subject_id') && $request->subject_id !== 'all') {
            $query->where('subject_id', $request->subject_id);
        }

        // Filter by exam type (using exam_types JSON column)
        if ($request->has('exam_type') && $request->exam_type !== 'all') {
            $query->whereJsonContains('exam_types', $request->exam_type);
        }

        // Filter by question type
        if ($request->has('question_type') && $request->question_type !== 'all') {
            $query->where('question_type', $request->question_type);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $questions = $query->orderBy('created_at', 'desc')->paginate(20);

        // Get all subjects for filter
        $subjects = Subject::select('id', 'name')->where('is_active', true)->orderBy('name')->get();

        return Inertia::render('admin/questions/index', [
            'questions' => $questions,
            'subjects' => $subjects,
            'filters' => $request->only(['search', 'subject_id', 'exam_type', 'question_type']),
        ]);
    }

    /**
     * Show the form for creating a new question (standalone).
     */
    public function create()
    {
        $subjects = Subject::where('is_active', true)
            ->with('department:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'department_id']);
        $departments = \App\Models\Department::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('admin/questions/create', [
            'subjects' => $subjects,
            'departments' => $departments,
        ]);
    }

    /**
     * Store a newly created question (standalone).
     */
    public function store(Request $request)
    {
        // Log incoming request data for debugging
        Log::info('Question creation request', [
            'data' => $request->all(),
        ]);

        // First validate question_type to determine conditional rules
        $request->validate([
            'question_type' => 'required|in:multiple_choice,text_input,numeric_input,true_false',
        ]);

        // Base validation rules
        $rules = [
            'subject_id' => 'required|exists:subjects,id',
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,text_input,numeric_input,true_false',
            'explanation' => 'nullable|string',
            'exam_types' => 'required|array|min:1',
            'exam_types.*' => 'required|in:JAMB,DLI,UNILAG,GENERAL',
        ];

        // Conditional validation based on question type
        if ($request->question_type === 'multiple_choice') {
            $rules['answers'] = 'required|array|min:2';
            $rules['answers.*.answer_text'] = 'required|string';
            $rules['answers.*.is_correct'] = 'required|boolean';
            $rules['answers.*.order'] = 'required|string|in:A,B,C,D,E';
        } else {
            // For non-multiple_choice questions, expected_answer is required and answers should not be present
            $rules['expected_answer'] = 'required|string';
            // Explicitly prohibit answers from being present for non-multiple_choice
            $rules['answers'] = 'prohibited';
        }

        $validated = $request->validate($rules);

        try {
            // Conditional validation
            if ($validated['question_type'] === 'multiple_choice') {
                if (empty($validated['answers']) || count($validated['answers']) < 2) {
                    return back()->withErrors(['answers' => 'Multiple choice questions require at least two answers.']);
                }
                $hasCorrectAnswer = collect($validated['answers'])->contains('is_correct', true);
                if (!$hasCorrectAnswer) {
                    return back()->withErrors(['answers' => 'At least one answer must be marked as correct for multiple choice questions.']);
                }
            } else if ($validated['question_type'] === 'true_false') {
                if (empty($validated['expected_answer'])) {
                    return back()->withErrors(['expected_answer' => 'True/False questions require an expected answer (true or false).']);
                }
                $expectedAnswer = strtolower(trim($validated['expected_answer']));
                if (!in_array($expectedAnswer, ['true', 'false'])) {
                    return back()->withErrors(['expected_answer' => 'True/False questions must have an expected answer of either "true" or "false".']);
                }
                $validated['expected_answer'] = $expectedAnswer; // Normalize to lowercase
                $validated['answers'] = [];
            } else {
                if (empty($validated['expected_answer'])) {
                    return back()->withErrors(['expected_answer' => 'Text/Numeric input questions require an expected answer.']);
                }
                $validated['answers'] = [];
            }

            Log::info('Creating question with validated data', [
                'validated' => $validated,
            ]);

            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('questions', 'public');
            }

            $question = Question::create([
                'subject_id' => $validated['subject_id'],
                'question_text' => $validated['question_text'],
                'image' => $imagePath,
                'question_type' => $validated['question_type'],
                'explanation' => $validated['explanation'] ?? null,
                'expected_answer' => $validated['expected_answer'] ?? null,
                'exam_types' => $validated['exam_types'],
                'is_active' => true, // New questions are active by default
            ]);

            Log::info('Question created successfully', [
                'question_id' => $question->id,
            ]);

            if ($validated['question_type'] === 'multiple_choice') {
                foreach ($validated['answers'] as $answerData) {
                    $question->answers()->create([
                        'answer_text' => $answerData['answer_text'],
                        'is_correct' => $answerData['is_correct'],
                        'order' => $answerData['order'],
                    ]);
                }
                Log::info('Answers created successfully', [
                    'question_id' => $question->id,
                    'answer_count' => count($validated['answers']),
                ]);
            }

            return redirect()->route('admin.questions.index')
                ->with('success', 'Question created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error creating question', [
                'errors' => $e->errors(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error creating question', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors(['error' => 'Error creating question: ' . $e->getMessage()]);
        }
    }

    /**
     * Show the specified question (for viewing in modal).
     */
    public function show(Question $question)
    {
        $question->load('answers', 'subject', 'exam');

        // Ensure exam_types is an array
        if (!$question->exam_types || !is_array($question->exam_types)) {
            $question->exam_types = [];
        }

        return response()->json([
            'success' => true,
            'data' => $question,
        ]);
    }

    /**
     * Show the form for editing the specified question (standalone).
     */
    public function edit(Question $question)
    {
        $question->load('answers', 'subject', 'subject.department');
        $subjects = Subject::where('is_active', true)
            ->with('department:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'department_id']);
        $departments = \App\Models\Department::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        // Ensure exam_types is an array
        if (!$question->exam_types || !is_array($question->exam_types)) {
            $question->exam_types = [];
        }

        return Inertia::render('admin/questions/edit', [
            'question' => $question,
            'subjects' => $subjects,
            'departments' => $departments,
        ]);
    }

    /**
     * Update the specified question (standalone).
     */
    public function update(Request $request, Question $question)
    {
        // First validate question_type to determine conditional rules
        $request->validate([
            'question_type' => 'required|in:multiple_choice,text_input,numeric_input,true_false',
        ]);

       

        // Base validation rules
        $rules = [
            'subject_id' => 'required|exists:subjects,id',
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,text_input,numeric_input,true_false',
            'explanation' => 'nullable|string',
            'exam_types' => 'required|array|min:1',
            'exam_types.*' => 'required|in:JAMB,DLI,UNILAG,GENERAL',
        ];

        // Conditional validation based on question type
        if ($request->question_type === 'multiple_choice') {
            $rules['answers'] = 'required|array|min:2';
            $rules['answers.*.id'] = 'nullable|exists:answers,id';
            $rules['answers.*.answer_text'] = 'required|string';
            $rules['answers.*.is_correct'] = 'required|boolean';
            $rules['answers.*.order'] = 'required|string|in:A,B,C,D,E';
        } else {
            // For non-multiple_choice questions, expected_answer is required and answers should not be present
            $rules['expected_answer'] = 'required|string';
            // Explicitly prohibit answers from being present for non-multiple_choice
            $rules['answers'] = 'prohibited';
        }

        $validated = $request->validate($rules);

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

        // Conditional validation
        if ($validated['question_type'] === 'multiple_choice') {
            if (empty($validated['answers']) || count($validated['answers']) < 2) {
                return back()->withErrors(['answers' => 'Multiple choice questions require at least two answers.']);
            }
            $hasCorrectAnswer = collect($validated['answers'])->contains('is_correct', true);
            if (!$hasCorrectAnswer) {
                return back()->withErrors(['answers' => 'At least one answer must be marked as correct for multiple choice questions.']);
            }
        } else if ($validated['question_type'] === 'true_false') {
            if (empty($validated['expected_answer'])) {
                return back()->withErrors(['expected_answer' => 'True/False questions require an expected answer (true or false).']);
            }
            $expectedAnswer = strtolower(trim($validated['expected_answer']));
            if (!in_array($expectedAnswer, ['true', 'false'])) {
                return back()->withErrors(['expected_answer' => 'True/False questions must have an expected answer of either "true" or "false".']);
            }
            $validated['expected_answer'] = $expectedAnswer; // Normalize to lowercase
            // Delete all answers if question type changes from multiple_choice
            $question->answers()->delete();
            $validated['answers'] = [];
        } else {
            if (empty($validated['expected_answer'])) {
                return back()->withErrors(['expected_answer' => 'Text/Numeric input questions require an expected answer.']);
            }
            // Delete all answers if question type changes from multiple_choice
            $question->answers()->delete();
            $validated['answers'] = [];
        }

        $question->update([
            'subject_id' => $validated['subject_id'],
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'explanation' => $validated['explanation'] ?? null,
            'expected_answer' => $validated['expected_answer'] ?? null,
            'exam_types' => $validated['exam_types'],
            'image' => $validated['image'],
        ]);

        if ($validated['question_type'] === 'multiple_choice') {
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
        }

        return redirect()->route('admin.questions.index')
            ->with('success-toast', 'Question updated successfully.');
    }

    /**
     * Toggle active status of a question.
     */
    public function toggleActive(Request $request, Question $question)
    {
        $question->update([
            'is_active' => !$question->is_active,
        ]);

        return redirect()->route('admin.questions.index')
            ->with('success', $question->is_active ? 'Question activated successfully.' : 'Question deactivated successfully.');
    }

    /**
     * Remove the specified question from storage.
     */
    public function destroy(Question $question)
    {
        $question->delete();

        return redirect()->route('admin.questions.index')
            ->with('success-toast', 'Question deleted successfully.');
    }

    /**
     * Download sample CSV template for bulk upload.
     */
    public function downloadSample(Request $request)
    {
        $questionType = $request->get('question_type', 'multiple_choice');

        if ($questionType === 'text_input') {
            $filename = 'questions_text_input_template.csv';
        } else if ($questionType === 'numeric_input') {
            $filename = 'questions_numeric_input_template.csv';
        } else if ($questionType === 'true_false') {
            $filename = 'questions_true_false_template.csv';
        } else {
            $filename = 'questions_multiple_choice_template.csv';
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($questionType) {
            $file = fopen('php://output', 'w');

            // Add BOM for Excel compatibility
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if ($questionType === 'text_input' || $questionType === 'numeric_input') {
                // Headers for text/numeric input questions
                fputcsv($file, [
                    'Subject Name',
                    'Question Text',
                    'Expected Answer',
                    'Alternative Answers (comma-separated, optional)',
                    'Explanation (Optional)',
                    'Exam Types (comma-separated: JAMB,DLI,UNILAG,GENERAL)'
                ]);

                // Sample rows
                fputcsv($file, [
                    'Mathematics',
                    'What is the capital city of Nigeria?',
                    'Abuja',
                    'abuja,ABUJA',
                    'Abuja became the capital of Nigeria in 1991, replacing Lagos.',
                    'JAMB,DLI'
                ]);
            } else if ($questionType === 'true_false') {
                // Headers for true/false questions
                fputcsv($file, [
                    'Subject Name',
                    'Question Text',
                    'Expected Answer (true/false)',
                    'Explanation (Optional)',
                    'Exam Types (comma-separated: JAMB,DLI,UNILAG,GENERAL)'
                ]);

                // Sample rows
                fputcsv($file, [
                    'Mathematics',
                    'The sum of 2 and 2 equals 4.',
                    'true',
                    'This is a basic arithmetic fact: 2 + 2 = 4.',
                    'JAMB,DLI'
                ]);
            } else {
                // Headers for multiple choice questions
                fputcsv($file, [
                    'Subject Name',
                    'Question Text',
                    'Answer A',
                    'Answer B',
                    'Answer C',
                    'Answer D',
                    'Answer E (Optional)',
                    'Correct Answer (A/B/C/D/E)',
                    'Explanation (Optional)',
                    'Exam Types (comma-separated: JAMB,DLI,UNILAG,GENERAL)'
                ]);

                // Sample rows
                fputcsv($file, [
                    'Mathematics',
                    'What is 2 + 2?',
                    '3',
                    '4',
                    '5',
                    '6',
                    '',
                    'B',
                    'Basic addition: 2 + 2 = 4',
                    'JAMB,DLI'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Handle bulk upload of questions from CSV.
     */
    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
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

            $subjectName = trim($row[0] ?? '');
            $questionText = trim($row[1] ?? '');

            // Validation
            if (empty($subjectName)) {
                $errors[] = "Row {$rowNumber}: Subject name is required.";
                continue;
            }

            if (empty($questionText)) {
                $errors[] = "Row {$rowNumber}: Question text is required.";
                continue;
            }

            // Find subject
            $subject = Subject::where('name', $subjectName)->orWhere('slug', \Illuminate\Support\Str::slug($subjectName))->first();
            if (!$subject) {
                $errors[] = "Row {$rowNumber}: Subject '{$subjectName}' not found.";
                continue;
            }

            // Parse exam types (column index depends on question type)
            if ($questionType === 'multiple_choice') {
                $examTypesColumn = 9;
            } else if ($questionType === 'true_false') {
                $examTypesColumn = 4;
            } else {
                $examTypesColumn = 5; // text_input, numeric_input
            }
            $examTypesString = trim($row[$examTypesColumn] ?? '');
            $examTypes = [];
            if (!empty($examTypesString)) {
                $examTypes = array_map('trim', explode(',', $examTypesString));
                $examTypes = array_filter($examTypes, function ($type) {
                    return in_array(strtoupper($type), ['JAMB', 'DLI', 'UNILAG', 'GENERAL']);
                });
                $examTypes = array_map('strtoupper', $examTypes);
            }
            if (empty($examTypes)) {
                $errors[] = "Row {$rowNumber}: At least one exam type (JAMB, DLI, UNILAG, GENERAL) is required.";
                continue;
            }

            // Get explanation column (depends on question type)
            if ($questionType === 'multiple_choice') {
                $explanationColumn = 8;
            } else if ($questionType === 'true_false') {
                $explanationColumn = 3;
            } else {
                $explanationColumn = 4; // text_input, numeric_input
            }

            $explanation = trim($row[$explanationColumn] ?? '');

            try {
                if ($questionType === 'text_input' || $questionType === 'numeric_input') {
                    // Handle text/numeric input questions
                    $expectedAnswer = trim($row[2] ?? '');
                    $alternativeAnswers = trim($row[3] ?? '');

                    if (empty($expectedAnswer)) {
                        $errors[] = "Row {$rowNumber}: Expected answer is required for text/numeric input questions.";
                        continue;
                    }

                    // Combine expected answer and alternatives
                    $allAnswers = [$expectedAnswer];
                    if (!empty($alternativeAnswers)) {
                        $alternatives = array_map('trim', explode(',', $alternativeAnswers));
                        $allAnswers = array_merge($allAnswers, $alternatives);
                    }
                    $expectedAnswerString = implode(',', array_unique($allAnswers));

                    // Create question
                    $question = Question::create([
                        'subject_id' => $subject->id,
                        'question_text' => $questionText,
                        'question_type' => $questionType,
                        'expected_answer' => $expectedAnswerString,
                        'explanation' => $explanation ?: null,
                        'exam_types' => $examTypes,
                        'is_active' => true,
                    ]);
                } else if ($questionType === 'true_false') {
                    // Handle true/false questions
                    $expectedAnswer = strtolower(trim($row[2] ?? ''));

                    if (empty($expectedAnswer)) {
                        $errors[] = "Row {$rowNumber}: Expected answer is required for true/false questions.";
                        continue;
                    }

                    if (!in_array($expectedAnswer, ['true', 'false'])) {
                        $errors[] = "Row {$rowNumber}: True/false questions must have expected answer of 'true' or 'false'.";
                        continue;
                    }

                    // Create question
                    $question = Question::create([
                        'subject_id' => $subject->id,
                        'question_text' => $questionText,
                        'question_type' => 'true_false',
                        'expected_answer' => $expectedAnswer,
                        'explanation' => $explanation ?: null,
                        'exam_types' => $examTypes,
                        'is_active' => true,
                    ]);
                } else {
                    // Handle multiple choice questions
                    if (count($row) < 8) {
                        $errors[] = "Row {$rowNumber}: Insufficient columns. Expected at least 8 columns.";
                        continue;
                    }

                    $answerA = trim($row[2] ?? '');
                    $answerB = trim($row[3] ?? '');
                    $answerC = trim($row[4] ?? '');
                    $answerD = trim($row[5] ?? '');
                    $answerE = trim($row[6] ?? '');
                    $correctAnswer = strtoupper(trim($row[7] ?? ''));

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

                    // Create question
                    $question = Question::create([
                        'subject_id' => $subject->id,
                        'question_text' => $questionText,
                        'question_type' => 'multiple_choice',
                        'explanation' => $explanation ?: null,
                        'exam_types' => $examTypes,
                        'is_active' => true,
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
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNumber}: Error - " . $e->getMessage();
            }
        }

        fclose($handle);

        if ($imported > 0) {
            return redirect()->route('admin.questions.index')
                ->with('success', "Successfully imported {$imported} question(s).")
                ->with('import_errors', $errors);
        } else {
            return redirect()->route('admin.questions.index')
                ->withErrors(['bulk_upload' => 'No questions were imported. Please check your file format.'])
                ->with('import_errors', $errors);
        }
    }
}
