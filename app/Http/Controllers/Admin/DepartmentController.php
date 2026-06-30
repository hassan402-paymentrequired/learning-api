<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Department::withCount('subjects');

        // Search
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Filter by active status
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

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $department = Department::create($validated);

        return redirect()->route('admin.departments.index')
            ->with('success', 'Department created successfully.');
    }

    /**
     * Display courses (subjects) belonging to the department.
     */
    public function show(Request $request, Department $department)
    {
        $query = $department->subjects()->withCount('questions');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', $request->is_active === 'true');
        }

        $subjects = $query->orderBy('name')->paginate(15)->withQueryString();

        return Inertia::render('admin/departments/show', [
            'department' => $department->loadCount('subjects'),
            'subjects' => $subjects,
            'filters' => [
                'search' => $request->input('search', ''),
                'is_active' => $request->input('is_active', 'all'),
            ],
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

        // Generate slug if not provided
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
        // Check if department has subjects
        if ($department->subjects()->count() > 0) {
            return redirect()->route('admin.departments.index')
                ->with('error', 'Cannot delete department with associated subjects. Please reassign or delete subjects first.');
        }

        $department->delete();

        return redirect()->route('admin.departments.index')
            ->with('success', 'Department deleted successfully.');
    }
}
