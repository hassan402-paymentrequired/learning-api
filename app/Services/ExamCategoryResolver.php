<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ExamCategoryResolver
{
    /** @var array<string, string> */
    private const LEGACY_TO_SLUG = [
        'JAMB' => 'jamb',
        'DLI' => 'unilag-dli',
        'UNILAG' => 'unilag-dli',
    ];

    public function resolve(string|int|null $examType): ?ExamCategory
    {
        if ($examType === null || $examType === '') {
            return null;
        }

        if (is_numeric($examType)) {
            return ExamCategory::find((int) $examType);
        }

        $value = (string) $examType;

        $category = ExamCategory::where('slug', $value)
            ->orWhereRaw('LOWER(slug) = ?', [strtolower($value)])
            ->orWhereRaw('LOWER(name) = ?', [strtolower($value)])
            ->first();

        if ($category) {
            return $category;
        }

        $mappedSlug = $this->legacyToSlug($value);

        return $mappedSlug
            ? ExamCategory::where('slug', $mappedSlug)->first()
            : null;
    }

    public function legacyToSlug(string $value): ?string
    {
        $upper = strtoupper($value);

        if (isset(self::LEGACY_TO_SLUG[$upper])) {
            return self::LEGACY_TO_SLUG[$upper];
        }

        $category = ExamCategory::where('slug', $value)
            ->orWhereRaw('LOWER(slug) = ?', [strtolower($value)])
            ->first();

        return $category?->slug;
    }

    /**
     * Normalize exam_types JSON values to canonical category slugs.
     *
     * @param  array<int, string|int>  $examTypes
     * @return array<int, string>
     */
    public function normalizeToSlugs(array $examTypes): array
    {
        $slugs = [];

        foreach ($examTypes as $type) {
            $category = $this->resolve($type);
            if ($category) {
                $slugs[] = $category->slug;
                continue;
            }

            $mapped = $this->legacyToSlug((string) $type);
            if ($mapped) {
                $slugs[] = $mapped;
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * All tokens that may appear in exam_types JSON or category pivots for a request value.
     *
     * @return array<int, string>
     */
    public function matchTokens(string|int|null $examType): array
    {
        if ($examType === null || $examType === '') {
            return [];
        }

        $tokens = [(string) $examType];
        $category = $this->resolve($examType);

        if ($category) {
            $tokens[] = $category->slug;
            $tokens[] = (string) $category->id;
            $tokens[] = strtoupper($category->slug);

            foreach (self::LEGACY_TO_SLUG as $legacy => $slug) {
                if ($slug === $category->slug) {
                    $tokens[] = $legacy;
                }
            }
        } else {
            $mapped = $this->legacyToSlug((string) $examType);
            if ($mapped) {
                $tokens[] = $mapped;
                $tokens[] = strtoupper($mapped);
            }
        }

        return array_values(array_unique(array_filter($tokens)));
    }

    public function applyQuestionExamTypeFilter(Builder $query, string|int $examType): Builder
    {
        $category = $this->resolve($examType);
        $tokens = $this->matchTokens($examType);

        return $query->where(function (Builder $q) use ($tokens, $category, $examType) {
            if (!empty($tokens)) {
                $q->where(function (Builder $inner) use ($tokens) {
                    foreach ($tokens as $token) {
                        $inner->orWhereJsonContains('exam_types', $token);
                    }
                });
            }

            $q->orWhereHas('examCategories', function (Builder $cq) use ($category, $examType) {
                if ($category) {
                    $cq->where('exam_categories.id', $category->id);
                } elseif (is_numeric($examType)) {
                    $cq->where('exam_categories.id', (int) $examType);
                } else {
                    $cq->where('exam_categories.slug', $examType);
                }
            });
        });
    }

    public function applySubjectExamTypeFilter(Builder $query, string|int $examType): Builder
    {
        $category = $this->resolve($examType);
        $tokens = $this->matchTokens($examType);

        return $query->where(function (Builder $q) use ($tokens, $category, $examType) {
            if (!empty($tokens)) {
                $q->where(function (Builder $inner) use ($tokens) {
                    foreach ($tokens as $token) {
                        $inner->orWhereJsonContains('exam_types', $token);
                    }
                });
            }

            $q->orWhereHas('questions', function (Builder $questionQuery) use ($tokens, $category, $examType) {
                $questionQuery->where(function (Builder $inner) use ($tokens, $category, $examType) {
                    if (!empty($tokens)) {
                        $inner->where(function (Builder $tokenQuery) use ($tokens) {
                            foreach ($tokens as $token) {
                                $tokenQuery->orWhereJsonContains('exam_types', $token);
                            }
                        });
                    }

                    $inner->orWhereHas('examCategories', function (Builder $cq) use ($category, $examType) {
                        if ($category) {
                            $cq->where('exam_categories.id', $category->id);
                        } elseif (is_numeric($examType)) {
                            $cq->where('exam_categories.id', (int) $examType);
                        } else {
                            $cq->where('exam_categories.slug', $examType);
                        }
                    });
                });
            });
        });
    }

    public function syncQuestionCategories(Question $question, ?array $examTypeSlugs = null): void
    {
        $slugs = $examTypeSlugs ?? $this->normalizeToSlugs($question->exam_types ?? []);
        $categoryIds = ExamCategory::whereIn('slug', $slugs)->pluck('id')->all();
        $question->examCategories()->sync($categoryIds);
    }

    public function syncExamCategories(Exam $exam): void
    {
        if (!$exam->exam_type) {
            return;
        }

        $slug = $this->legacyToSlug($exam->exam_type);
        if (!$slug) {
            return;
        }

        $category = ExamCategory::where('slug', $slug)->first();
        if ($category) {
            $exam->examCategories()->syncWithoutDetaching([$category->id]);
        }
    }

    public function requiresDepartment(array $examTypeSlugs): bool
    {
        $slugs = $this->normalizeToSlugs($examTypeSlugs);

        return ExamCategory::whereIn('slug', $slugs)
            ->where('flow_type', 'departmental')
            ->exists();
    }

    public function syncAllSubjects(): int
    {
        $updated = 0;

        Subject::query()->each(function (Subject $subject) use (&$updated) {
            $normalized = $this->normalizeToSlugs($subject->exam_types ?? []);
            if ($normalized !== ($subject->exam_types ?? [])) {
                $subject->update(['exam_types' => $normalized]);
                $updated++;
            }
        });

        return $updated;
    }

    public function syncAllQuestions(): int
    {
        $updated = 0;

        Question::query()->each(function (Question $question) use (&$updated) {
            $normalized = $this->normalizeToSlugs($question->exam_types ?? []);
            $changed = $normalized !== ($question->exam_types ?? []);

            if ($changed) {
                $question->update(['exam_types' => $normalized]);
                $updated++;
            }

            $this->syncQuestionCategories($question, $normalized);
        });

        return $updated;
    }

    public function syncAllExams(): int
    {
        $linked = 0;

        Exam::query()->whereNotNull('exam_type')->each(function (Exam $exam) use (&$linked) {
            $before = DB::table('exam_category_exam')->where('exam_id', $exam->id)->count();
            $this->syncExamCategories($exam);
            $after = DB::table('exam_category_exam')->where('exam_id', $exam->id)->count();
            if ($after > $before) {
                $linked++;
            }
        });

        return $linked;
    }
}
