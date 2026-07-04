<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Subject;
use App\Services\ExamCategoryResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExamController extends Controller
{
    public function __construct(private ExamCategoryResolver $resolver)
    {
    }

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
        return Inertia::render('admin/exams/create', [
            'subjects' => $this->pastQuestionSubjects(),
            'examCategories' => $this->pastQuestionCategories(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'exam_category_id' => 'required|exists:exam_categories,id',
            'subject_id' => 'required|exists:subjects,id',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 1),
            'is_active' => 'boolean',
        ]);

        $category = ExamCategory::findOrFail($validated['exam_category_id']);
        $subject = Subject::findOrFail($validated['subject_id']);

        if ($this->pastQuestionExists($category, $subject->name, $validated['year'])) {
            return back()->withErrors([
                'year' => 'A past question already exists for ' . $subject->name . ' in ' . $validated['year'] . ' under ' . $category->name . '.',
            ]);
        }

        $title = $validated['title'] ?: "{$category->name} {$subject->name} {$validated['year']}";

        $exam = Exam::create([
            'title' => $title,
            'exam_type' => $category->slug,
            'subject' => $subject->name,
            'year' => $validated['year'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->resolver->syncExamCategories($exam);

        return redirect()->route('admin.exams.show', $exam)
            ->with('success-toast', 'Past question created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Exam $exam)
    {
        $exam->load(['questions.answers', 'questions.exams:id,title,year']);

        // Other exams of same subject (different years) for linking questions
        $relatedExams = Exam::where('exam_type', $exam->exam_type)
            ->where('id', '!=', $exam->id)
            ->when($exam->subject, fn ($q) => $q->where('subject', $exam->subject))
            ->orderBy('year', 'desc')
            ->get(['id', 'title', 'subject', 'year']);

        return Inertia::render('admin/exams/show', [
            'exam' => $exam,
            'relatedExams' => $relatedExams,
            'import_errors' => session('import_errors', []),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Exam $exam)
    {
        $currentSubject = $exam->subject
            ? Subject::where('name', $exam->subject)->first()
            : null;

        $currentCategory = $exam->examCategories()->first()
            ?? $this->resolver->resolve($exam->exam_type);

        return Inertia::render('admin/exams/edit', [
            'exam' => $exam,
            'subjects' => $this->pastQuestionSubjects($currentSubject?->id),
            'examCategories' => $this->pastQuestionCategories(),
            'current_subject_id' => $currentSubject?->id,
            'current_exam_category_id' => $currentCategory?->id,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'exam_category_id' => 'required|exists:exam_categories,id',
            'subject_id' => 'required|exists:subjects,id',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 1),
            'is_active' => 'boolean',
        ]);

        $category = ExamCategory::findOrFail($validated['exam_category_id']);
        $subject = Subject::findOrFail($validated['subject_id']);

        if ($this->pastQuestionExists($category, $subject->name, $validated['year'], $exam->id)) {
            return back()->withErrors([
                'year' => 'A past question already exists for ' . $subject->name . ' in ' . $validated['year'] . ' under ' . $category->name . '.',
            ]);
        }

        $title = $validated['title'] ?: "{$category->name} {$subject->name} {$validated['year']}";

        $exam->update([
            'title' => $title,
            'exam_type' => $category->slug,
            'subject' => $subject->name,
            'year' => $validated['year'],
            'is_active' => $validated['is_active'] ?? $exam->is_active,
        ]);

        $exam->examCategories()->sync([$category->id]);

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'Past question updated successfully.');
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

        // Duplicate questions and answers — link same questions to the new paper
        $newExam->questions()->attach($exam->questions()->pluck('questions.id')->all());

        // Update total questions count
        $newExam->refreshTotalQuestions();

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

    private function pastQuestionSubjects(?int $includeSubjectId = null)
    {
        $tokens = [];
        foreach ($this->pastQuestionCategories() as $category) {
            $tokens = array_merge($tokens, $this->resolver->matchTokens($category->slug));
        }
        $tokens = array_values(array_unique(array_filter($tokens)));

        return Subject::where('is_active', true)
            ->where(function ($query) use ($tokens, $includeSubjectId) {
                if (!empty($tokens)) {
                    $query->where(function ($inner) use ($tokens) {
                        foreach ($tokens as $token) {
                            $inner->orWhereJsonContains('exam_types', $token);
                        }
                    });
                } else {
                    $query->whereRaw('0 = 1');
                }

                if ($includeSubjectId) {
                    $query->orWhere('id', $includeSubjectId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function pastQuestionCategories()
    {
        return ExamCategory::where('is_active', true)
            ->where('flow_type', 'standard')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    private function pastQuestionExists(
        ExamCategory $category,
        string $subjectName,
        int $year,
        ?int $excludeExamId = null
    ): bool {
        $searchValues = array_unique(array_filter([
            strtolower($category->slug),
            strtoupper($category->slug),
            strtoupper(str_replace('-', ' ', $category->slug)),
        ]));

        $query = Exam::where('subject', $subjectName)
            ->where('year', $year)
            ->where(function ($q) use ($searchValues, $category) {
                foreach ($searchValues as $value) {
                    $q->orWhereRaw('LOWER(exam_type) = ?', [strtolower($value)]);
                }
                $q->orWhereHas('examCategories', function ($cq) use ($category) {
                    $cq->where('exam_categories.id', $category->id);
                });
            });

        if ($excludeExamId) {
            $query->where('id', '!=', $excludeExamId);
        }

        return $query->exists();
    }
}
