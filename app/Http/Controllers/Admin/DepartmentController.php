<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Department::withCount('subjects');

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active === 'true');
        }

        $departments = $query->orderBy('name')->paginate(15);

        return Inertia::render('admin/departments/index', [
            'departments' => $departments,
            'filters' => $request->only(['search', 'is_active']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('admin/departments/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
            'slug' => 'nullable|string|max:255|unique:departments,slug',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        Department::create($validated);

        return redirect()->route('admin.departments.index')
            ->with('success', 'Department created successfully.');
    }

    /**
     * Display courses (subjects) linked to the department.
     */
    public function show(Request $request, Department $department)
    {
        $query = $department->subjects()
            ->with('departments:id,name,is_active')
            ->withCount('questions');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', $request->is_active === 'true');
        }

        $subjects = $query->orderBy('name')->paginate(15)->withQueryString();

        $subjects->getCollection()->transform(function (Subject $subject) {
            $subject->department_ids = $subject->departments->pluck('id')->values();
            $subject->linked_departments = $subject->departments->map(fn ($dept) => [
                'id' => $dept->id,
                'name' => $dept->name,
                'is_active' => $dept->is_active,
            ])->values();

            return $subject;
        });

        $linkedSubjectIds = $department->subjects()->pluck('subjects.id');

        $linkableSubjects = Subject::where('is_active', true)
            ->when($linkedSubjectIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $linkedSubjectIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        $allDepartments = Department::orderBy('name')->get(['id', 'name', 'is_active']);

        return Inertia::render('admin/departments/show', [
            'department' => $department->loadCount('subjects'),
            'subjects' => $subjects,
            'linkableSubjects' => $linkableSubjects,
            'allDepartments' => $allDepartments,
            'filters' => [
                'search' => $request->input('search', ''),
                'is_active' => $request->input('is_active', 'all'),
            ],
        ]);
    }

    /**
     * Display a single course (subject) within a department.
     */
    public function showCourse(Request $request, Department $department, Subject $subject)
    {
        if (!$department->subjects()->where('subjects.id', $subject->id)->exists()) {
            abort(404);
        }

        $subject->loadCount('questions');

        $questions = Question::where('subject_id', $subject->id)
            ->withCount('answers')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/departments/course', [
            'department' => $department,
            'subject' => $subject,
            'questions' => $questions,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Department $department)
    {
        return Inertia::render('admin/departments/edit', [
            'department' => $department,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name,' . $department->id,
            'slug' => 'nullable|string|max:255|unique:departments,slug,' . $department->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $department->update($validated);

        return redirect()->route('admin.departments.index')
            ->with('success', 'Department updated successfully.');
    }

    /**
     * Toggle active status of a department.
     */
    public function toggleActive(Request $request, Department $department)
    {
        $department->update([
            'is_active' => !$department->is_active,
        ]);

        return redirect()->route('admin.departments.index')
            ->with('success', $department->is_active ? 'Department activated successfully.' : 'Department deactivated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department)
    {
        if ($department->subjects()->count() > 0) {
            return redirect()->route('admin.departments.index')
                ->with('error', 'Cannot delete department with linked courses. Please unlink courses first.');
        }

        $department->delete();

        return redirect()->route('admin.departments.index')
            ->with('success', 'Department deleted successfully.');
    }

    /**
     * Duplicate a department and link the same courses.
     */
    public function duplicate(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
        ]);

        $newDepartment = DB::transaction(function () use ($department, $validated) {
            $newDepartment = $department->replicate();
            $newDepartment->uuid = null;
            $newDepartment->name = $validated['name'];
            $newDepartment->slug = $this->uniqueDepartmentSlug(Str::slug($validated['name']));
            $newDepartment->is_active = false;
            $newDepartment->save();

            $subjectIds = $department->subjects()->pluck('subjects.id');
            $newDepartment->subjects()->attach($subjectIds);

            return $newDepartment;
        });

        return redirect()->route('admin.departments.show', $newDepartment)
            ->with('success', 'Department duplicated successfully.');
    }

    /**
     * Link existing courses to the department.
     */
    public function linkSubjects(Request $request, Department $department)
    {
        $validated = $request->validate([
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'exists:subjects,id',
        ]);

        $department->subjects()->syncWithoutDetaching($validated['subject_ids']);

        return redirect()->route('admin.departments.show', $department)
            ->with('success', 'Courses linked successfully.');
    }

    /**
     * Remove a course from the department without deleting the course.
     */
    public function unlinkSubject(Department $department, Subject $subject)
    {
        if (!$department->subjects()->where('subjects.id', $subject->id)->exists()) {
            abort(404);
        }

        $department->subjects()->detach($subject->id);

        return redirect()->route('admin.departments.show', $department)
            ->with('success', 'Course removed from department.');
    }

    private function uniqueDepartmentSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $suffix = 1;

        while (Department::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
