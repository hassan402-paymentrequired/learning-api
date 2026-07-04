<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        $relatedExams = $this->relatedExamsFor($exam);

        return Inertia::render('admin/exams/questions/create', [
            'exam' => $exam,
            'subjects' => $subjects,
            'relatedExams' => $relatedExams,
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

        $request->validate([
            'question_type' => 'required|in:multiple_choice,text_input,numeric_input,true_false',
        ]);

        $rules = [
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,text_input,numeric_input,true_false',
            'explanation' => 'nullable|string',
            'exam_ids' => 'nullable|array',
            'exam_ids.*' => 'integer|exists:exams,id',
        ];

        if ($request->question_type === 'multiple_choice') {
            $rules['answers'] = 'required|array|min:2';
            $rules['answers.*.answer_text'] = 'required|string';
            $rules['answers.*.is_correct'] = 'required|boolean';
            $rules['answers.*.order'] = 'required|string|in:A,B,C,D,E';
        } else {
            $rules['expected_answer'] = 'required|string';
        }

        $validated = $request->validate($rules);

        try {
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('questions', 'public');
            }

            $examTypes = [$exam->exam_type];
            $examIds = $this->resolveExamIds($exam, $validated['exam_ids'] ?? []);

            $question = Question::create([
                'subject_id' => $exam->subject ? Subject::where('name', $exam->subject)->first()?->id : null,
                'question_text' => $validated['question_text'],
                'image' => $imagePath,
                'question_type' => $validated['question_type'],
                'explanation' => $validated['explanation'] ?? null,
                'expected_answer' => $validated['expected_answer'] ?? null,
                'exam_types' => $examTypes,
                'is_active' => true,
            ]);

            $question->exams()->attach($examIds);
            $this->refreshExamTotals($examIds);

            if ($validated['question_type'] === 'multiple_choice') {
                foreach ($validated['answers'] as $answerData) {
                    $question->answers()->create([
                        'answer_text' => $answerData['answer_text'],
                        'is_correct' => $answerData['is_correct'],
                        'order' => $answerData['order'],
                    ]);
                }
            }

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
        $this->ensureQuestionLinkedToExam($exam, $question);

        $question->load(['answers', 'exams:id,title,year,subject']);
        $subjects = Subject::where('is_active', true)->orderBy('name')->get();
        $relatedExams = $this->relatedExamsFor($exam);

        return Inertia::render('admin/exams/questions/edit', [
            'exam' => $exam,
            'question' => $question,
            'subjects' => $subjects,
            'relatedExams' => $relatedExams,
            'linkedExamIds' => $question->exams->pluck('id')->all(),
        ]);
    }

    /**
     * Update a question for an exam.
     */
    public function update(Request $request, Exam $exam, Question $question)
    {
        $this->ensureQuestionLinkedToExam($exam, $question);

        $request->validate([
            'question_type' => 'required|in:multiple_choice,text_input,numeric_input,true_false',
        ]);

        $rules = [
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,text_input,numeric_input,true_false',
            'explanation' => 'nullable|string',
            'exam_ids' => 'nullable|array',
            'exam_ids.*' => 'integer|exists:exams,id',
        ];

        if ($request->question_type === 'multiple_choice') {
            $rules['answers'] = 'required|array|min:2';
            $rules['answers.*.id'] = 'nullable|exists:answers,id';
            $rules['answers.*.answer_text'] = 'required|string';
            $rules['answers.*.is_correct'] = 'required|boolean';
            $rules['answers.*.order'] = 'required|string|in:A,B,C,D,E';
        } else {
            $rules['expected_answer'] = 'required|string';
        }

        $validated = $request->validate($rules);

        try {
            if ($request->hasFile('image')) {
                if ($question->image) {
                    Storage::disk('public')->delete($question->image);
                }
                $imagePath = $request->file('image')->store('questions', 'public');
                $validated['image'] = $imagePath;
            } else {
                $validated['image'] = $question->image;
            }

            $question->update([
                'question_text' => $validated['question_text'],
                'image' => $validated['image'],
                'question_type' => $validated['question_type'],
                'explanation' => $validated['explanation'] ?? null,
                'expected_answer' => $validated['expected_answer'] ?? null,
            ]);

            if (array_key_exists('exam_ids', $validated)) {
                $previousExamIds = $question->exams()->pluck('exams.id')->all();
                $examIds = $this->resolveExamIds($exam, $validated['exam_ids'] ?? []);
                $question->exams()->sync($examIds);
                $this->refreshExamTotals(array_unique(array_merge($previousExamIds, $examIds)));
            }

            if ($validated['question_type'] === 'multiple_choice') {
                $existingAnswerIds = $question->answers()->pluck('id')->toArray();
                $submittedAnswerIds = array_filter(array_column($validated['answers'], 'id'));

                $answersToDelete = array_diff($existingAnswerIds, $submittedAnswerIds);
                if (!empty($answersToDelete)) {
                    $question->answers()->whereIn('id', $answersToDelete)->delete();
                }

                foreach ($validated['answers'] as $answerData) {
                    if (isset($answerData['id']) && in_array($answerData['id'], $existingAnswerIds)) {
                        $question->answers()->where('id', $answerData['id'])->update([
                            'answer_text' => $answerData['answer_text'],
                            'is_correct' => $answerData['is_correct'],
                            'order' => $answerData['order'],
                        ]);
                    } else {
                        $question->answers()->create([
                            'answer_text' => $answerData['answer_text'],
                            'is_correct' => $answerData['is_correct'],
                            'order' => $answerData['order'],
                        ]);
                    }
                }
            } else {
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
     * Remove a question from this past question paper (detach, not delete).
     */
    public function destroy(Exam $exam, Question $question)
    {
        $this->ensureQuestionLinkedToExam($exam, $question);

        $question->exams()->detach($exam->id);
        $exam->refreshTotalQuestions();

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'Question removed from this past question paper.');
    }

    /**
     * Link a question to additional past question papers (same subject).
     */
    public function link(Request $request, Exam $exam, Question $question)
    {
        $this->ensureQuestionLinkedToExam($exam, $question);

        $validated = $request->validate([
            'exam_ids' => 'required|array|min:1',
            'exam_ids.*' => 'integer|exists:exams,id',
        ]);

        $examIds = $this->resolveExamIds($exam, $validated['exam_ids']);
        $previousExamIds = $question->exams()->pluck('exams.id')->all();

        $question->exams()->sync($examIds);
        $this->refreshExamTotals(array_unique(array_merge($previousExamIds, $examIds)));

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'Question linked to selected past question papers.');
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

        $callback = function () use ($questionType) {
            $file = fopen('php://output', 'w');

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

        $firstLine = fgets($handle);
        if (substr($firstLine, 0, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        } else {
            fseek($handle, 3);
        }

        fgetcsv($handle);

        $imported = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

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
                        'subject_id' => $exam->subject ? Subject::where('name', $exam->subject)->first()?->id : null,
                        'question_text' => $questionText,
                        'question_type' => 'multiple_choice',
                        'explanation' => $explanation ?: null,
                        'exam_types' => [$exam->exam_type],
                        'is_active' => true,
                    ]);

                    $question->exams()->attach($exam->id);

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
                        'subject_id' => $exam->subject ? Subject::where('name', $exam->subject)->first()?->id : null,
                        'question_text' => $questionText,
                        'question_type' => $questionType,
                        'expected_answer' => $expectedAnswer,
                        'explanation' => $explanation ?: null,
                        'exam_types' => [$exam->exam_type],
                        'is_active' => true,
                    ]);

                    $question->exams()->attach($exam->id);
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

        $exam->refreshTotalQuestions();

        if ($imported > 0) {
            return redirect()->route('admin.exams.show', $exam)
                ->with('success', "Successfully imported {$imported} question(s).")
                ->with('import_errors', $errors);
        }

        return redirect()->route('admin.exams.show', $exam)
            ->withErrors(['bulk_upload' => 'No questions were imported. Please check your file format.'])
            ->with('import_errors', $errors);
    }

    private function ensureQuestionLinkedToExam(Exam $exam, Question $question): void
    {
        if (!$question->exams()->where('exams.id', $exam->id)->exists()) {
            abort(404);
        }
    }

    /**
     * @return array<int>
     */
    private function resolveExamIds(Exam $exam, array $requestedExamIds): array
    {
        $examIds = collect($requestedExamIds)
            ->map(fn ($id) => (int) $id)
            ->push($exam->id)
            ->unique()
            ->values();

        $exams = Exam::whereIn('id', $examIds)->get(['id', 'subject']);

        if ($exams->count() !== $examIds->count()) {
            throw ValidationException::withMessages([
                'exam_ids' => 'One or more past question papers could not be found.',
            ]);
        }

        foreach ($exams as $targetExam) {
            if ($exam->subject && $targetExam->subject && $exam->subject !== $targetExam->subject) {
                throw ValidationException::withMessages([
                    'exam_ids' => 'All past question papers must be for the same subject (' . $exam->subject . ').',
                ]);
            }
        }

        return $examIds->all();
    }

    /**
     * @param array<int> $examIds
     */
    private function refreshExamTotals(array $examIds): void
    {
        Exam::whereIn('id', $examIds)->get()->each->refreshTotalQuestions();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Exam>
     */
    private function relatedExamsFor(Exam $exam)
    {
        return Exam::query()
            ->where('exam_type', $exam->exam_type)
            ->when($exam->subject, fn ($q) => $q->where('subject', $exam->subject))
            ->orderBy('year', 'desc')
            ->get(['id', 'title', 'subject', 'year']);
    }
}
