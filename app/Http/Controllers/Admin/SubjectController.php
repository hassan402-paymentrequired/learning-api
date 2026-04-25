<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Question;
use App\Models\Answer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Subject::withCount('questions');

        // Search
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active === 'true');
        }

        // Filter by exam type (using exam_types JSON column)
        if ($request->has('exam_type') && $request->exam_type !== 'all') {
            $query->whereJsonContains('exam_types', $request->exam_type);
        }

        $subjects = $query->orderBy('name')->paginate(15);

        // Get all exam categories for filter
        $examCategories = \App\Models\ExamCategory::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']);

        return Inertia::render('admin/subjects/index', [
            'subjects' => $subjects,
            'examCategories' => $examCategories,
            'filters' => $request->only(['search', 'is_active', 'exam_type']),
        ]);
    }

    /**
     * Show questions for a specific subject.
     */
    public function show(Subject $subject)
    {
        $subject->loadCount('questions');
        
        $questions = \App\Models\Question::where('subject_id', $subject->id)
            ->with(['subject', 'exam', 'answers'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('admin/subjects/show', [
            'subject' => $subject,
            'questions' => $questions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $departments = \App\Models\Department::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $examCategories = \App\Models\ExamCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'flow_type']);

        return Inertia::render('admin/subjects/create', [
            'departments' => $departments,
            'examCategories' => $examCategories,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:subjects,name',
            'description' => 'nullable|string',
            'exam_types' => 'required|array|min:1',
            'exam_types.*' => 'required',
            'department_id' => 'nullable|exists:departments,id',
            'is_active' => 'boolean',
        ]);

        // Require department_id if exam_types includes DLI or UNILAG
        if (in_array('DLI', $validated['exam_types']) || in_array('UNILAG', $validated['exam_types'])) {
            if (empty($validated['department_id'])) {
                return redirect()->back()
                    ->withErrors(['department_id' => 'Department is required for DLI/Unilag subjects.'])
                    ->withInput();
            }
        }

        $subject = Subject::create($validated);

        return redirect()->route('admin.subjects.index')
            ->with('success', 'Subject created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Subject $subject)
    {
        $departments = \App\Models\Department::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $examCategories = \App\Models\ExamCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('admin/subjects/edit', [
            'subject' => $subject,
            'departments' => $departments,
            'examCategories' => $examCategories,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Subject $subject)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:subjects,name,' . $subject->id,
            'description' => 'nullable|string',
            'exam_types' => 'required|array|min:1',
            'exam_types.*' => 'required',
            'department_id' => 'nullable|exists:departments,id',
            'is_active' => 'boolean',
        ]);

        // Require department_id if exam_types includes DLI or UNILAG
        if (in_array('DLI', $validated['exam_types']) || in_array('UNILAG', $validated['exam_types'])) {
            if (empty($validated['department_id'])) {
                return redirect()->back()
                    ->withErrors(['department_id' => 'Department is required for DLI/Unilag subjects.'])
                    ->withInput();
            }
        }

        $subject->update($validated);

        return redirect()->route('admin.subjects.index')
            ->with('success', 'Subject updated successfully.');
    }

    /**
     * Toggle active status of a subject.
     */
    public function toggleActive(Request $request, Subject $subject)
    {
        $subject->update([
            'is_active' => !$subject->is_active,
        ]);

        return redirect()->route('admin.subjects.index')
            ->with('success', $subject->is_active ? 'Subject activated successfully.' : 'Subject deactivated successfully.');
    }

    /**
     * Bulk update subjects (activate/deactivate).
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'subject_ids' => 'required|array',
            'subject_ids.*' => 'exists:subjects,id',
            'action' => 'required|in:activate,deactivate',
        ]);

        $action = $validated['action'];
        $isActive = $action === 'activate';

        Subject::whereIn('id', $validated['subject_ids'])
            ->update(['is_active' => $isActive]);

        $count = count($validated['subject_ids']);
        $message = $isActive 
            ? "{$count} subject(s) activated successfully."
            : "{$count} subject(s) deactivated successfully.";

        return redirect()->route('admin.subjects.index')
            ->with('success', $message);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subject $subject)
    {
        // Check if subject is used in any exams
        $examCount = \App\Models\Exam::where('subject', $subject->name)->count();
        
        if ($examCount > 0) {
            return back()->withErrors([
                'subject' => "Cannot delete subject. It is used in {$examCount} exam(s)."
            ]);
        }

        $subject->delete();

        return redirect()->route('admin.subjects.index')
            ->with('success', 'Subject deleted successfully.');
    }

    /**
     * Download sample CSV template for bulk upload.
     */
    public function downloadSample(Request $request, Subject $subject)
    {
        $questionType = $request->get('question_type', 'multiple_choice');
        $examType = $request->get('exam_type', 'JAMB');

        if ($questionType === 'text_input') {
            $filename = "questions_text_input_template_{$subject->slug}.csv";
        } else if ($questionType === 'numeric_input') {
            $filename = "questions_numeric_input_template_{$subject->slug}.csv";
        } else if ($questionType === 'true_false') {
            $filename = "questions_true_false_template_{$subject->slug}.csv";
        } else {
            $filename = "questions_multiple_choice_template_{$subject->slug}.csv";
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($questionType, $examType, $subject) {
            $file = fopen('php://output', 'w');

            // Add BOM for Excel compatibility
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if ($questionType === 'text_input' || $questionType === 'numeric_input') {
                // Headers for text/numeric input questions
                fputcsv($file, [
                    'Question Text',
                    'Expected Answer',
                    'Alternative Answers (comma-separated, optional)',
                    'Explanation (Optional)',
                    'Exam Types (comma-separated slugs)'
                ]);

                // Sample rows
                fputcsv($file, [
                    'What is the capital city of Nigeria?',
                    'Abuja',
                    'abuja,ABUJA',
                    'Abuja became the capital of Nigeria in 1991, replacing Lagos.',
                    $examType
                ]);
            } else if ($questionType === 'true_false') {
                // Headers for true/false questions
                fputcsv($file, [
                    'Question Text',
                    'Expected Answer (true/false)',
                    'Explanation (Optional)',
                    'Exam Types (comma-separated slugs)'
                ]);

                // Sample rows
                fputcsv($file, [
                    'The sum of 2 and 2 equals 4.',
                    'true',
                    'This is a basic arithmetic fact: 2 + 2 = 4.',
                    $examType
                ]);
            } else {
                // Headers for multiple choice questions
                fputcsv($file, [
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
                    'What is 2 + 2?',
                    '3',
                    '4',
                    '5',
                    '6',
                    '',
                    'B',
                    'Basic addition: 2 + 2 = 4',
                    $examType
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Handle bulk upload of questions from CSV, XLSX, or DOCX.
     */
    public function bulkUpload(Request $request, Subject $subject)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls,docx|max:10240', // 10MB max
            'question_type' => 'required|in:multiple_choice,text_input,numeric_input,true_false',
            'exam_type' => 'required',
        ]);

        $questionType = $request->input('question_type');
        $selectedExamType = $request->input('exam_type');
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $rows = [];

        // Parse file based on extension
        if (in_array($extension, ['csv', 'txt'])) {
            $rows = $this->parseCsv($file);
        } elseif (in_array($extension, ['xlsx', 'xls'])) {
            $rows = $this->parseExcel($file);
        } elseif ($extension === 'docx') {
            $rows = $this->parseDocx($file, $selectedExamType);
        } else {
            return back()->withErrors(['file' => 'Unsupported file format.']);
        }

        if (empty($rows)) {
            \Log::error('Bulk upload: No rows parsed from file', [
                'extension' => $extension,
                'file_size' => $file->getSize(),
            ]);
            return back()->withErrors(['file' => 'No data found in file. Please check the file format.']);
        }

        \Log::info('Bulk upload: Parsed rows', [
            'row_count' => count($rows),
            'extension' => $extension,
            'question_type' => $questionType,
            'exam_type' => $selectedExamType,
        ]);

        $imported = 0;
        $errors = [];
        $rowNumber = 0;
        $skipHeader = true;

        // For DOCX narrative format, we might not have a header row
        // Check if first row looks like a header (contains "Question Text" or similar)
        if ($extension === 'docx' && !empty($rows)) {
            $firstRow = is_array($rows[0]) ? $rows[0] : [];
            $firstCell = is_array($firstRow) ? ($firstRow[0] ?? '') : '';
            // If first row doesn't look like a header, don't skip it
            if (!preg_match('/question.*text|subject.*name/i', $firstCell)) {
                $skipHeader = false;
            }
        }

        foreach ($rows as $row) {
            $rowNumber++;

            // Skip header row if it exists
            if ($skipHeader && $rowNumber === 1) {
                continue;
            }

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Normalize row to array if needed
            if (!is_array($row)) {
                $row = is_string($row) ? str_getcsv($row) : (array) $row;
            }

            // Ensure row has enough elements
            while (count($row) < 10) {
                $row[] = '';
            }

            $questionText = trim($row[0] ?? '');

            // Validation
            if (empty($questionText)) {
                $errors[] = "Row {$rowNumber}: Question text is required.";
                continue;
            }

            // Parse exam types (column index depends on question type)
            if ($questionType === 'multiple_choice') {
                $examTypesColumn = 8;
            } else if ($questionType === 'true_false') {
                $examTypesColumn = 3;
            } else {
                $examTypesColumn = 4; // text_input, numeric_input
            }
            $examTypesString = trim($row[$examTypesColumn] ?? '');
            $examTypes = [];
            
            // Check if exam types are specified in the file
            if (!empty($examTypesString)) {
                $examTypes = array_map('trim', explode(',', $examTypesString));
                $validSlugs = \App\Models\ExamCategory::pluck('slug')->toArray();
                $examTypes = array_filter($examTypes, function ($type) use ($validSlugs) {
                    return in_array(strtoupper($type), array_map('strtoupper', $validSlugs));
                });
                $examTypes = array_map('strtoupper', $examTypes);
            }
            
            // Always use the selected exam type from the form
            // This ensures consistency - when user selects JAMB only, all questions get JAMB only
            $examTypes = [$selectedExamType];

            // Get explanation column (depends on question type)
            if ($questionType === 'multiple_choice') {
                $explanationColumn = 7;
            } else if ($questionType === 'true_false') {
                $explanationColumn = 2;
            } else {
                $explanationColumn = 3; // text_input, numeric_input
            }

            $explanation = trim($row[$explanationColumn] ?? '');

            try {
                if ($questionType === 'text_input' || $questionType === 'numeric_input') {
                    // Handle text/numeric input questions
                    $expectedAnswer = trim($row[1] ?? '');
                    $alternativeAnswers = trim($row[2] ?? '');

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
                    $expectedAnswer = strtolower(trim($row[1] ?? ''));

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
                    if (count($row) < 7) {
                        $errors[] = "Row {$rowNumber}: Insufficient columns. Expected at least 7 columns.";
                        continue;
                    }

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
                \Log::info("Bulk upload: Successfully imported question", [
                    'row_number' => $rowNumber,
                    'question_text' => substr($questionText, 0, 50),
                ]);
            } catch (\Exception $e) {
                $errorMessage = "Row {$rowNumber}: Error - " . $e->getMessage();
                $errors[] = $errorMessage;
                \Log::error('Bulk upload: Failed to import question', [
                    'row_number' => $rowNumber,
                    'question_text' => substr($questionText, 0, 50),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        if ($imported > 0) {
            $message = "Successfully imported {$imported} question(s).";
            if (!empty($errors)) {
                $message .= " " . count($errors) . " error(s) occurred during import.";
            }
            return redirect()->route('admin.subjects.show', $subject)
                ->with('success-toast', $message)
                ->with('import_errors', $errors);
        } else {
            $errorMessage = 'No questions were imported. ';
            if (!empty($errors)) {
                $errorMessage .= 'Errors: ' . implode('; ', array_slice($errors, 0, 5));
                if (count($errors) > 5) {
                    $errorMessage .= ' (and ' . (count($errors) - 5) . ' more)';
                }
            } else {
                $errorMessage .= 'Please check your file format. The file may be empty or in an unsupported format.';
            }
            return redirect()->route('admin.subjects.show', $subject)
                ->withErrors(['bulk_upload' => $errorMessage])
                ->with('import_errors', $errors);
        }
    }

    /**
     * Parse CSV file.
     */
    private function parseCsv($file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');

        // Skip BOM if present
        $firstLine = fgets($handle);
        if (substr($firstLine, 0, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        } else {
            fseek($handle, 3);
        }

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Parse Excel file (XLSX/XLS).
     */
    private function parseExcel($file): array
    {
        $rows = [];
        $spreadsheet = IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();

        for ($row = 1; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $cellValue = $worksheet->getCell($col . $row)->getValue();
                $rowData[] = $cellValue !== null ? (string) $cellValue : '';
            }
            $rows[] = $rowData;
        }

        return $rows;
    }

    /**
     * Parse DOCX file.
     * Supports both table format and narrative text format.
     */
    private function parseDocx($file, $examType = 'JAMB'): array
    {
        $rows = [];
        
        try {
            $phpWord = WordIOFactory::load($file->getRealPath());
            $sections = $phpWord->getSections();
            
            $fullText = '';
            $hasTables = false;

            // First, check if document has tables and collect all text
            foreach ($sections as $section) {
                $elements = $section->getElements();
                foreach ($elements as $element) {
                    if (method_exists($element, 'getRows')) {
                        $hasTables = true;
                        // Handle tables
                        $tableRows = $element->getRows();
                        foreach ($tableRows as $tableRow) {
                            $rowData = [];
                            $cells = $tableRow->getCells();
                            foreach ($cells as $cell) {
                                $text = $this->extractTextFromElement($cell);
                                $rowData[] = trim($text);
                            }
                            if (!empty(array_filter($rowData))) {
                                $rows[] = $rowData;
                            }
                        }
                    } else {
                        // Collect all text
                        $text = $this->extractTextFromElement($element);
                        if (!empty(trim($text))) {
                            $fullText .= trim($text) . "\n";
                        }
                    }
                }
            }

            // If no tables found or tables are empty, parse narrative text format  
            if ((!$hasTables || empty($rows)) && !empty($fullText)) {
                $parsedRows = $this->parseNarrativeFormat($fullText, $examType);
                if (!empty($parsedRows)) {
                    $rows = $parsedRows;
                }
            }
        } catch (\Exception $e) {
            throw new \Exception('Error parsing DOCX file: ' . $e->getMessage());
        }

        return $rows;
    }

    /**
     * Extract text from a PhpWord element recursively.
     */
    private function extractTextFromElement($element): string
    {
        $text = '';
        
        if ($element === null) {
            return $text;
        }
        
        // Try to get text directly
        if (method_exists($element, 'getText')) {
            try {
                $text .= $element->getText();
            } catch (\Exception $e) {
                // Ignore errors and continue
            }
        }
        
        // Try to get text from nested elements
        if (method_exists($element, 'getElements')) {
            try {
                $elements = $element->getElements();
                if (is_array($elements) || $elements instanceof \Traversable) {
                    foreach ($elements as $subElement) {
                        $text .= $this->extractTextFromElement($subElement);
                    }
                }
            } catch (\Exception $e) {
                // Ignore errors and continue
            }
        }
        
        return $text;
    }

    /**
     * Parse narrative text format (Question 1: ... a) ... b) ... Answer: c).
     */
    private function parseNarrativeFormat($text, $examType = 'JAMB'): array
    {
        $rows = [];
        
        // Normalize line endings and split
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Remove excessive whitespace but preserve structure
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $lines = explode("\n", $text);
        
        $currentQuestion = null;
        $currentOptions = [];
        $currentAnswer = null;
        $inQuestion = false;
        $foundOptions = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }
            
            // Skip section headers (e.g., "2. Components of a Marketing Information System (MIS)")
            if (preg_match('/^\d+\.\s+[A-Z]/', $line)) {
                continue;
            }
            
            // Check if this is a question line (Question 1:, Question 2:, etc.)
            if (preg_match('/^Question\s+\d+[:\-]?\s*(.*)$/i', $line, $matches)) {
                // Save previous question if exists
                if ($currentQuestion !== null && !empty(trim($currentQuestion)) && !empty($currentOptions) && $currentAnswer !== null) {
                    $rows[] = $this->buildQuestionRow(trim($currentQuestion), $currentOptions, $currentAnswer, $examType);
                }
                
                // Start new question
                $questionText = trim($matches[1]);
                // If question text is on the same line, use it; otherwise it will be on the next line
                $currentQuestion = !empty($questionText) ? $questionText : null; // Use null to indicate we're waiting for question text
                $currentOptions = [];
                $currentAnswer = null;
                $inQuestion = true;
                $foundOptions = false;
            }
            // Check if this is an option line (a), b), c), d), e)) - handle both formats
            elseif (preg_match('/^([a-eA-E])[\)\.]\s*(.+)$/i', $line, $matches)) {
                $optionLetter = strtoupper(trim($matches[1]));
                $optionText = trim($matches[2]);
                if (!empty($optionText)) {
                    $currentOptions[$optionLetter] = $optionText;
                    $foundOptions = true;
                }
            }
            // Check if this is an answer line (Answer: a, Answer: b, Answer: c, etc.) - more flexible
            elseif (preg_match('/^Answer[s]?[:\-]?\s*([a-eA-E])/i', $line, $matches)) {
                $currentAnswer = strtoupper(trim($matches[1]));
                $inQuestion = false;
            }
            // If we're in a question block and it's not an option or answer, it might be question text or continuation
            elseif ($inQuestion && !preg_match('/^Answer[s]?:/i', $line) && !preg_match('/^[a-eA-E][\)\.]/i', $line) && !preg_match('/^\d+\.\s+[A-Z]/', $line)) {
                // If we haven't found options yet, this is likely question text
                if (!$foundOptions) {
                    // If question text is null or empty, this is the question text
                    if ($currentQuestion === null || empty($currentQuestion)) {
                        $currentQuestion = $line;
                    } else {
                        // Otherwise, append to question text if it seems like continuation
                        // Don't append if it looks like a new question or section
                        if (!preg_match('/^Question\s+\d+/i', $line) && !preg_match('/^[A-Z][a-z]+.*:$/', $line)) {
                            $currentQuestion .= ' ' . $line;
                        }
                    }
                }
                // If we've found options, ignore continuation text (might be explanation or other content)
            }
        }
        
        // Don't forget the last question
        if ($currentQuestion !== null && !empty(trim($currentQuestion)) && !empty($currentOptions) && $currentAnswer !== null) {
            $rows[] = $this->buildQuestionRow(trim($currentQuestion), $currentOptions, $currentAnswer, $examType);
        }
        
        // Log parsing results for debugging
        \Log::info('DOCX narrative parsing completed', [
            'total_questions_parsed' => count($rows),
            'exam_type' => $examType,
        ]);
        
        return $rows;
    }

    /**
     * Build a question row in the expected format for multiple choice.
     */
    private function buildQuestionRow($questionText, $options, $correctAnswer, $examType = 'JAMB'): array
    {
        // Format: [Question Text, Answer A, Answer B, Answer C, Answer D, Answer E (optional), Correct Answer, Explanation, Exam Types]
        $row = [
            $questionText,
            $options['A'] ?? '',
            $options['B'] ?? '',
            $options['C'] ?? '',
            $options['D'] ?? '',
            $options['E'] ?? '',
            $correctAnswer,
            '', // Explanation (empty by default)
            $examType, // Use the selected exam type
        ];
        
        return $row;
    }
}
