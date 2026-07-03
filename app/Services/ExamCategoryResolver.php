<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Question;
use App\Models\Subject;
use App\Support\PublicUuidLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ExamCategoryResolver
{
    /** @var array<string, string> */
    private const LEGACY_TO_SLUG = [
        'JAMB' => 'jamb',
        'DLI' => 'unilag-dli',
        'UNILAG' => 'unilag-dli',
    ];

    /**
     * Alternate slugs that refer to the same exam category.
     * Live admin may create "UNILAG POST UME" (unilag-post-ume) while content uses unilag-post-utme.
     *
     * @var array<string, string>
     */
    private const SLUG_ALIASES = [
        'unilag-post-ume' => 'unilag-post-utme',
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

        if (PublicUuidLookup::isUuid($value)) {
            return ExamCategory::where('uuid', $value)->first();
        }

        $slugCandidates = $this->slugCandidates($value);

        $category = ExamCategory::query()
            ->where(function (Builder $query) use ($value, $slugCandidates) {
                $query->whereIn('slug', $slugCandidates)
                    ->orWhereRaw('LOWER(slug) = ?', [strtolower($value)])
                    ->orWhereRaw('LOWER(name) = ?', [strtolower($value)]);

                foreach ($slugCandidates as $slug) {
                    $query->orWhereRaw('LOWER(name) = ?', [str_replace('-', ' ', strtoupper($slug))]);
                }
            })
            ->first();

        if ($category) {
            return $category;
        }

        $mappedSlug = $this->legacyToSlug($value);

        return $mappedSlug
            ? ExamCategory::where('slug', $mappedSlug)->first()
            : null;
    }

    /**
     * @return array<int, string>
     */
    public function slugCandidates(string $value): array
    {
        $normalized = strtolower(trim($value));
        $canonical = self::SLUG_ALIASES[$normalized] ?? $normalized;

        $candidates = [$normalized, $canonical];

        foreach (self::SLUG_ALIASES as $alias => $target) {
            if ($normalized === $alias || $normalized === $target || $canonical === $target) {
                $candidates[] = $alias;
                $candidates[] = $target;
            }
        }

        return array_values(array_unique(array_filter($candidates)));
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
        $tokens = array_merge($tokens, $this->slugCandidates((string) $examType));
        $category = $this->resolve($examType);

        if ($category) {
            $tokens[] = $category->slug;
            $tokens[] = $category->uuid;
            $tokens[] = (string) $category->id;
            $tokens[] = strtoupper($category->slug);
            $tokens = array_merge($tokens, $this->slugCandidates($category->slug));

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
                $tokens = array_merge($tokens, $this->slugCandidates($mapped));
            }
        }

        return array_values(array_unique(array_filter($tokens)));
    }

    public function applyQuestionExamTypeFilter(Builder $query, string|int $examType): Builder
    {
        $category = $this->resolve($examType);
        $tokens = $this->matchTokens($examType);
        $categoryIds = $this->relatedCategoryIds($category, $examType);

        return $query->where(function (Builder $q) use ($tokens, $categoryIds) {
            if (!empty($tokens)) {
                $q->where(function (Builder $inner) use ($tokens) {
                    foreach ($tokens as $token) {
                        $inner->orWhereJsonContains('exam_types', $token);
                    }
                });
            }

            if (!empty($categoryIds)) {
                $q->orWhereHas('examCategories', function (Builder $cq) use ($categoryIds) {
                    $cq->whereIn('exam_categories.id', $categoryIds);
                });
            }
        });
    }

    public function applySubjectExamTypeFilter(Builder $query, string|int $examType): Builder
    {
        $category = $this->resolve($examType);
        $tokens = $this->matchTokens($examType);
        $categoryIds = $this->relatedCategoryIds($category, $examType);

        return $query->where(function (Builder $q) use ($tokens, $categoryIds) {
            if (!empty($tokens)) {
                $q->where(function (Builder $inner) use ($tokens) {
                    foreach ($tokens as $token) {
                        $inner->orWhereJsonContains('exam_types', $token);
                    }
                });
            }

            $q->orWhereHas('questions', function (Builder $questionQuery) use ($tokens, $categoryIds) {
                $questionQuery->where(function (Builder $inner) use ($tokens, $categoryIds) {
                    if (!empty($tokens)) {
                        $inner->where(function (Builder $tokenQuery) use ($tokens) {
                            foreach ($tokens as $token) {
                                $tokenQuery->orWhereJsonContains('exam_types', $token);
                            }
                        });
                    }

                    if (!empty($categoryIds)) {
                        $inner->orWhereHas('examCategories', function (Builder $cq) use ($categoryIds) {
                            $cq->whereIn('exam_categories.id', $categoryIds);
                        });
                    }
                });
            });
        });
    }

    /**
     * @return array<int, int>
     */
    private function relatedCategoryIds(?ExamCategory $category, string|int $examType): array
    {
        if ($category) {
            return ExamCategory::query()
                ->whereIn('slug', $this->slugCandidates($category->slug))
                ->pluck('id')
                ->all();
        }

        if (is_numeric($examType)) {
            return [(int) $examType];
        }

        return ExamCategory::query()
            ->whereIn('slug', $this->slugCandidates((string) $examType))
            ->pluck('id')
            ->all();
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

    /**
     * When an exam category slug changes, update all content that references the old slug.
     */
    public function propagateSlugChange(string $oldSlug, string $newSlug): array
    {
        $oldSlug = strtolower(trim($oldSlug));
        $newSlug = strtolower(trim($newSlug));

        if ($oldSlug === '' || $newSlug === '' || $oldSlug === $newSlug) {
            return ['subjects' => 0, 'questions' => 0, 'exams' => 0];
        }

        $counts = ['subjects' => 0, 'questions' => 0, 'exams' => 0];

        Subject::query()->each(function (Subject $subject) use ($oldSlug, $newSlug, &$counts) {
            $examTypes = $subject->exam_types ?? [];
            $updated = $this->replaceSlugTokenInList($examTypes, $oldSlug, $newSlug);

            if ($updated !== $examTypes) {
                $subject->update(['exam_types' => $updated]);
                $counts['subjects']++;
            }
        });

        Question::query()->each(function (Question $question) use ($oldSlug, $newSlug, &$counts) {
            $examTypes = $question->exam_types ?? [];
            $updated = $this->replaceSlugTokenInList($examTypes, $oldSlug, $newSlug);

            if ($updated !== $examTypes) {
                $question->update(['exam_types' => $updated]);
                $this->syncQuestionCategories($question, $updated);
                $counts['questions']++;
            }
        });

        if (Schema::hasColumn('exams', 'exam_type')) {
            $counts['exams'] = Exam::query()
                ->whereRaw('LOWER(exam_type) = ?', [$oldSlug])
                ->update(['exam_type' => $newSlug]);
        }

        return $counts;
    }

    /**
     * @param  array<int, string|int>  $examTypes
     * @return array<int, string|int>
     */
    private function replaceSlugTokenInList(array $examTypes, string $oldSlug, string $newSlug): array
    {
        $normalized = [];

        foreach ($examTypes as $type) {
            if (is_string($type) && strtolower($type) === $oldSlug) {
                $normalized[] = $newSlug;
                continue;
            }

            $normalized[] = $type;
        }

        return array_values(array_unique($normalized));
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
