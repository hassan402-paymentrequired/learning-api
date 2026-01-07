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

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by exam_type
        if ($request->has('exam_type')) {
            $query->where('exam_type', $request->exam_type);
        }

        $exams = $query->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('admin/exams/index', [
            'exams' => $exams,
            'filters' => $request->only(['search', 'type', 'exam_type']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('admin/exams/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:practice,past_question',
            'exam_type' => 'required|in:JAMB,UNILAG,DLI,GENERAL',
            'subject' => 'nullable|string|max:255',
            'duration' => 'required|integer|min:1|max:120', // Max 2 hours (120 minutes) per student flow
            'year' => 'nullable|integer|min:2000|max:' . (date('Y') + 1),
            'is_active' => 'boolean',
        ]);

        // For practice exams, subject is required (student flow requirement)
        if ($validated['type'] === 'practice' && empty($validated['subject'])) {
            return back()->withErrors(['subject' => 'Subject is required for practice exams. Students must select a subject.']);
        }

        $exam = Exam::create($validated);

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'Exam created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Exam $exam)
    {
        $exam->load(['questions.answers']);

        return Inertia::render('admin/exams/show', [
            'exam' => $exam,
            'import_errors' => session('import_errors', []),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Exam $exam)
    {
        return Inertia::render('admin/exams/edit', [
            'exam' => $exam,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:practice,past_question',
            'exam_type' => 'required|in:JAMB,UNILAG,DLI,GENERAL',
            'subject' => 'nullable|string|max:255',
            'duration' => 'required|integer|min:1|max:120', // Max 2 hours (120 minutes) per student flow
            'year' => 'nullable|integer|min:2000|max:' . (date('Y') + 1),
            'is_active' => 'boolean',
        ]);

        // For practice exams, subject is required (student flow requirement)
        if ($validated['type'] === 'practice' && empty($validated['subject'])) {
            return back()->withErrors(['subject' => 'Subject is required for practice exams. Students must select a subject.']);
        }

        $exam->update($validated);

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
