<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Exam::withCount('questions');

        // Search
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Filter by exam_type
        if ($request->has('exam_type')) {
            $query->where('exam_type', $request->exam_type);
        }

        $exams = $query->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('admin/exams/index', [
            'exams' => $exams,
            'filters' => $request->only(['search', 'exam_type']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $subjects = \App\Models\Subject::where('is_active', true)
            ->whereJsonContains('exam_types', 'JAMB')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('admin/exams/create', [
            'subjects' => $subjects,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'subject_id' => 'required|exists:subjects,id',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 1),
            'is_active' => 'boolean',
        ]);

        // Get subject name from subject_id
        $subject = \App\Models\Subject::findOrFail($validated['subject_id']);
        
        // Check if exam already exists for this subject and year
        $existingExam = Exam::where('exam_type', 'JAMB')
            ->where('subject', $subject->name)
            ->where('year', $validated['year'])
            ->first();

        if ($existingExam) {
            return back()->withErrors([
                'year' => 'A past question exam already exists for ' . $subject->name . ' in ' . $validated['year'] . '.',
            ]);
        }

        // Auto-generate title if empty
        $title = $validated['title'] ?: "JAMB {$subject->name} {$validated['year']}";

        $exam = Exam::create([
            'title' => $title,
            'exam_type' => 'JAMB', 
            'subject' => $subject->name,
            'year' => $validated['year'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('admin.exams.show', $exam)
            ->with('success-toast', 'Exam created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Exam $exam)
    {
        $exam->load(['questions.answers']);

        // Other exams of same subject (different years) for question duplicating
        $targetExams = Exam::where('exam_type', $exam->exam_type)
            ->where('id', '!=', $exam->id)
            ->when($exam->subject, fn ($q) => $q->where('subject', $exam->subject))
            ->orderBy('year', 'desc')
            ->get(['id', 'title', 'subject', 'year']);

        return Inertia::render('admin/exams/show', [
            'exam' => $exam,
            'targetExams' => $targetExams,
            'import_errors' => session('import_errors', []),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Exam $exam)
    {
        $subjects = \App\Models\Subject::where('is_active', true)
            ->whereJsonContains('exam_types', 'JAMB')
            ->orderBy('name')
            ->get(['id', 'name']);

        // Find the subject ID for the current exam's subject
        $currentSubject = null;
        if ($exam->subject) {
            $currentSubject = \App\Models\Subject::where('name', $exam->subject)->first();
        }

        return Inertia::render('admin/exams/edit', [
            'exam' => $exam,
            'subjects' => $subjects,
            'current_subject_id' => $currentSubject?->id,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'subject_id' => 'required|exists:subjects,id',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 1),
            'is_active' => 'boolean',
        ]);

        // Get subject name from subject_id
        $subject = \App\Models\Subject::findOrFail($validated['subject_id']);
        
        // Check if another exam already exists for this subject and year (excluding current exam)
        $existingExam = Exam::where('exam_type', 'JAMB')
            ->where('subject', $subject->name)
            ->where('year', $validated['year'])
            ->where('id', '!=', $exam->id)
            ->first();

        if ($existingExam) {
            return back()->withErrors([
                'year' => 'A past question exam already exists for ' . $subject->name . ' in ' . $validated['year'] . '.',
            ]);
        }

        // Auto-generate title if empty
        $title = $validated['title'] ?: "JAMB {$subject->name} {$validated['year']}";

        $exam->update([
            'title' => $title,
            'exam_type' => 'JAMB', // Always JAMB for past questions
            'subject' => $subject->name,
            'year' => $validated['year'],
            'is_active' => $validated['is_active'] ?? $exam->is_active,
        ]);

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'Exam updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Exam $exam)
    {
        $exam->delete();

        return redirect()->route('admin.exams.index')
            ->with('success', 'Exam deleted successfully.');
    }

    /**
     * Duplicate an exam with all its questions.
     */
    public function duplicate(Exam $exam)
    {
        // Create new exam
        $newExam = $exam->replicate();
        $newExam->title = $exam->title . ' (Copy)';
        $newExam->is_active = false; // Set as inactive by default
        $newExam->total_questions = 0;
        $newExam->save();

        // Duplicate questions and answers
        foreach ($exam->questions as $question) {
            $newQuestion = $question->replicate();
            $newQuestion->exam_id = $newExam->id;
            $newQuestion->save();

            // Duplicate answers
            foreach ($question->answers as $answer) {
                $newAnswer = $answer->replicate();
                $newAnswer->question_id = $newQuestion->id;
                $newAnswer->save();
            }
        }

        // Update total questions count
        $newExam->update([
            'total_questions' => $newExam->questions()->count(),
        ]);

        return redirect()->route('admin.exams.show', $newExam)
            ->with('success', 'Exam duplicated successfully.');
    }

    /**
     * Toggle active status of an exam.
     */
    public function toggleActive(Request $request, Exam $exam)
    {
        $exam->update([
            'is_active' => !$exam->is_active,
        ]);

        return redirect()->route('admin.exams.index')
            ->with('success', $exam->is_active ? 'Exam activated successfully.' : 'Exam deactivated successfully.');
    }

    /**
     * Bulk update exams (activate/deactivate).
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'exam_ids' => 'required|array',
            'exam_ids.*' => 'exists:exams,id',
            'action' => 'required|in:activate,deactivate',
        ]);

        $action = $validated['action'];
        $isActive = $action === 'activate';

        Exam::whereIn('id', $validated['exam_ids'])
            ->update(['is_active' => $isActive]);

        $count = count($validated['exam_ids']);
        $message = $isActive 
            ? "{$count} exam(s) activated successfully."
            : "{$count} exam(s) deactivated successfully.";

        return redirect()->route('admin.exams.index')
            ->with('success', $message);
    }
}
