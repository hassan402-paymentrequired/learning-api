<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\SubjectTest;
use App\Models\Question;
use App\Services\ExamCategoryResolver;
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
        $query = Question::with(['subject', 'exams', 'answers']);

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

        // Filter by past question paper
        if ($request->filled('exam_id') && $request->exam_id !== 'all') {
            $query->whereHas('exams', function ($q) use ($request) {
                $q->where('exams.id', $request->exam_id);
            });
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $questions = $query->orderBy('created_at', 'desc')->paginate(20);

        // Get all subjects for filter
        $subjects = Subject::select('id', 'name')->where('is_active', true)->orderBy('name')->get();

        // Get all exam categories for filter
        $examCategories = \App\Models\ExamCategory::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']);

        return Inertia::render('admin/questions/index', [
            'questions' => $questions,
            'subjects' => $subjects,
            'examCategories' => $examCategories,
            'filters' => $request->only(['search', 'subject_id', 'exam_type', 'question_type']),
        ]);
    }

    /**
     * Show the form for creating a new question (standalone).
     */
    public function create(Request $request, ExamCategoryResolver $resolver)
    {
        $subjects = Subject::where('is_active', true)
            ->with('departments:id')
            ->orderBy('name')
            ->get(['id', 'name', 'exam_types'])
            ->map(fn (Subject $subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'department_ids' => $subject->departments->pluck('id')->values()->all(),
                'exam_types' => $resolver->normalizeToSlugs($subject->exam_types ?? []),
            ]);
        $departments = \App\Models\Department::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $subjectTests = SubjectTest::with('subject:id,name')
            ->orderBy('subject_id')
            ->orderBy('order')
            ->get(['id', 'subject_id', 'name', 'order']);

        $examCategories = \App\Models\ExamCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'flow_type']);

        $defaults = [
            'subject_id' => '',
            'department_id' => null,
            'exam_types' => [],
        ];

        if ($request->filled('subject_id')) {
            $subject = Subject::where('is_active', true)->with('departments:id')->find($request->subject_id);
            if ($subject) {
                $defaults['subject_id'] = (string) $subject->id;
                $defaults['department_id'] = $subject->departments->first()?->id;
                $defaults['exam_types'] = $resolver->normalizeToSlugs($subject->exam_types ?? []);
            }
        } elseif ($request->filled('department_id')) {
            $defaults['department_id'] = (int) $request->department_id;
        }

        return Inertia::render('admin/questions/create', [
            'subjects' => $subjects,
            'departments' => $departments,
            'subjectTests' => $subjectTests,
            'examCategories' => $examCategories,
            'pastQuestionExams' => $this->pastQuestionExams(),
            'defaults' => $defaults,
        ]);
    }

    /**
     * Store a newly created question (standalone).
     */
    public function store(Request $request, ExamCategoryResolver $resolver)
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
            'exam_types' => 'sometimes|array',
            'exam_types.*' => 'required',
            'exam_ids' => 'nullable|array',
            'exam_ids.*' => 'integer|exists:exams,id',
            'test_ids' => 'nullable|array',
            'test_ids.*' => 'integer|exists:subject_tests,id',
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

        $subject = Subject::findOrFail($validated['subject_id']);
        $examTypes = $resolver->normalizeToSlugs($subject->exam_types ?? []);

        if (empty($examTypes)) {
            return back()
                ->withErrors(['subject_id' => 'This subject has no exam types configured. Update the subject first.'])
                ->withInput();
        }

        $validated['exam_types'] = $examTypes;

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

            $resolver->syncQuestionCategories($question, $validated['exam_types']);

            $this->syncPastQuestionExams($question, $validated['exam_ids'] ?? []);

            // Sync subject tests for departmental categories (only tests that belong to this subject)
            if ($resolver->requiresDepartment($validated['exam_types']) && !empty($validated['test_ids'] ?? [])) {
                $validTestIds = SubjectTest::where('subject_id', $validated['subject_id'])
                    ->whereIn('id', $validated['test_ids'])
                    ->pluck('id')
                    ->toArray();
                $question->subjectTests()->sync($validTestIds);
            } else {
                $question->subjectTests()->sync([]);
            }

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
        $question->load('answers', 'subject', 'exams');

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
    public function edit(Question $question, ExamCategoryResolver $resolver)
    {
        $question->load('answers', 'subject.departments', 'subjectTests', 'exams:id,title,year,subject');
        $subjects = Subject::where('is_active', true)
            ->with('departments:id')
            ->orderBy('name')
            ->get(['id', 'name', 'exam_types'])
            ->map(fn (Subject $subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'department_ids' => $subject->departments->pluck('id')->values()->all(),
                'exam_types' => $resolver->normalizeToSlugs($subject->exam_types ?? []),
            ]);
        $departments = \App\Models\Department::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $subjectTests = SubjectTest::with('subject:id,name')
            ->orderBy('subject_id')
            ->orderBy('order')
            ->get(['id', 'subject_id', 'name', 'order']);

        $examCategories = \App\Models\ExamCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'flow_type']);

        $question->exam_types = $resolver->normalizeToSlugs($question->exam_types ?? []);

        return Inertia::render('admin/questions/edit', [
            'question' => $question,
            'subjects' => $subjects,
            'departments' => $departments,
            'subjectTests' => $subjectTests,
            'examCategories' => $examCategories,
            'pastQuestionExams' => $this->pastQuestionExams(),
        ]);
    }

    /**
     * Update the specified question (standalone).
     */
    public function update(Request $request, Question $question, ExamCategoryResolver $resolver)
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
        'exam_types.*' => 'required',
        'exam_ids' => 'nullable|array',
        'exam_ids.*' => 'integer|exists:exams,id',
    ];

        if ($resolver->requiresDepartment($request->input('exam_types', []))) {
            $rules['test_ids'] = 'nullable|array';
            $rules['test_ids.*'] = 'integer|exists:subject_tests,id';
        }

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
        $validated['exam_types'] = $resolver->normalizeToSlugs($validated['exam_types']);

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

        $resolver->syncQuestionCategories($question, $validated['exam_types']);

        $this->syncPastQuestionExams($question, $validated['exam_ids'] ?? []);

        // Sync subject tests for departmental categories
        if ($resolver->requiresDepartment($validated['exam_types']) && array_key_exists('test_ids', $validated)) {
            $validTestIds = SubjectTest::where('subject_id', $validated['subject_id'])
                ->whereIn('id', $validated['test_ids'] ?? [])
                ->pluck('id')
                ->toArray();
            $question->subjectTests()->sync($validTestIds);
        } else {
            $question->subjectTests()->sync([]);
        }

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
                    'Exam Types (comma-separated slugs)'
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
                    'Exam Types (comma-separated slugs)'
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
                    'Exam Types (comma-separated slugs)'
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
    public function bulkUpload(Request $request, ExamCategoryResolver $resolver)
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
            }
            $examTypes = $resolver->normalizeToSlugs($examTypes);
            if (empty($examTypes)) {
                $errors[] = "Row {$rowNumber}: At least one valid exam type is required.";
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

                $resolver->syncQuestionCategories($question, $examTypes);

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

    private function pastQuestionExams()
    {
        return Exam::query()
            ->whereNotNull('year')
            ->orderBy('subject')
            ->orderBy('year', 'desc')
            ->get(['id', 'title', 'subject', 'year', 'exam_type']);
    }

    /**
     * @param array<int|string> $examIds
     */
    private function syncPastQuestionExams(Question $question, array $examIds): void
    {
        $subjectName = $question->subject?->name;
        if (!$subjectName) {
            return;
        }

        $previousExamIds = $question->exams()->pluck('exams.id')->all();
        $validExamIds = Exam::query()
            ->whereIn('id', $examIds)
            ->where('subject', $subjectName)
            ->pluck('id')
            ->all();

        $question->exams()->sync($validExamIds);

        $affectedExamIds = array_unique(array_merge($previousExamIds, $validExamIds));
        Exam::whereIn('id', $affectedExamIds)->get()->each->refreshTotalQuestions();
    }
}
