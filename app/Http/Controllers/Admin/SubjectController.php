<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Subject::query();

        // Search
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active === 'true');
        }

        $subjects = $query->orderBy('order')->orderBy('name')->paginate(15);

        return Inertia::render('admin/subjects/index', [
            'subjects' => $subjects,
            'filters' => $request->only(['search', 'is_active']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('admin/subjects/create');
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
            'exam_types.*' => 'required|in:JAMB,DLI,UNILAG,GENERAL',
            'is_active' => 'boolean',
            'order' => 'nullable|integer|min:0',
        ]);

        $subject = Subject::create($validated);

        return redirect()->route('admin.subjects.index')
            ->with('success', 'Subject created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Subject $subject)
    {
        return Inertia::render('admin/subjects/edit', [
            'subject' => $subject,
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
            'exam_types.*' => 'required|in:JAMB,DLI,UNILAG,GENERAL',
            'is_active' => 'boolean',
            'order' => 'nullable|integer|min:0',
        ]);

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
}
