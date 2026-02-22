<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\SubjectTest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubjectTestController extends Controller
{
    /**
     * List tests for a subject.
     */
    public function index(Subject $subject)
    {
        $subject->load('tests');
        return Inertia::render('admin/subjects/tests/index', [
            'subject' => $subject,
            'tests' => $subject->tests()->orderBy('order')->orderBy('name')->get(),
        ]);
    }

    /**
     * Show create form for a test.
     */
    public function create(Subject $subject)
    {
        return Inertia::render('admin/subjects/tests/create', [
            'subject' => $subject,
        ]);
    }

    /**
     * Store a new test.
     */
    public function store(Request $request, Subject $subject)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);
        $validated['subject_id'] = $subject->id;
        $validated['order'] = $subject->tests()->max('order') + 1;
        SubjectTest::create($validated);
        return redirect()->route('admin.subjects.tests.index', ['subject' => $subject->id])
            ->with('success', 'Test created successfully.');
    }

    /**
     * Show edit form.
     */
    public function edit(Subject $subject, SubjectTest $subject_test)
    {
        if ($subject_test->subject_id !== $subject->id) {
            abort(404);
        }
        return Inertia::render('admin/subjects/tests/edit', [
            'subject' => $subject,
            'test' => $subject_test,
        ]);
    }

    /**
     * Update the test.
     */
    public function update(Request $request, Subject $subject, SubjectTest $subject_test)
    {
        if ($subject_test->subject_id !== $subject->id) {
            abort(404);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);
        $subject_test->update($validated);
        return redirect()->route('admin.subjects.tests.index', ['subject' => $subject->id])
            ->with('success', 'Test updated successfully.');
    }

    /**
     * Delete the test.
     */
    public function destroy(Subject $subject, SubjectTest $subject_test)
    {
        if ($subject_test->subject_id !== $subject->id) {
            abort(404);
        }
        $subject_test->delete();
        return redirect()->route('admin.subjects.tests.index', ['subject' => $subject->id])
            ->with('success', 'Test deleted successfully.');
    }
}
