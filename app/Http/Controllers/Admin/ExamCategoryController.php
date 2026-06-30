<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class ExamCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = ExamCategory::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        
        if ($request->filled('status')) {
            $status = $request->status === 'active' ? 1 : 0;
            $query->where('is_active', $status);
        }

        $examCategories = $query->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/exam-categories/index', [
            'examCategories' => $examCategories,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/exam-categories/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:exam_categories',
            'flow_type' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        ExamCategory::create($validated);

        return redirect()->route('admin.exam-categories.index')
            ->with('success', 'Exam Category created successfully.');
    }

    public function edit(ExamCategory $examCategory)
    {
        return Inertia::render('admin/exam-categories/edit', [
            'examCategory' => $examCategory
        ]);
    }

    public function update(Request $request, ExamCategory $examCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:exam_categories,name,' . $examCategory->id,
            'flow_type' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $examCategory->update($validated);

        return redirect()->route('admin.exam-categories.index')
            ->with('success', 'Exam Category updated successfully.');
    }

    public function toggleActive(ExamCategory $examCategory)
    {
        $examCategory->update([
            'is_active' => !$examCategory->is_active
        ]);

        return redirect()->back()
            ->with('success', 'Exam Category status updated successfully.');
    }

    public function destroy(ExamCategory $examCategory)
    {
        $examCategory->delete();

        return redirect()->route('admin.exam-categories.index')
            ->with('success', 'Exam Category deleted successfully.');
    }
}
