<?php

use App\Models\ExamCategory;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalize UNILAG POST UME / UTME slug variants so category, subjects, and questions stay in sync.
     */
    public function up(): void
    {
        $canonicalSlug = 'unilag-post-utme';
        $aliasSlug = 'unilag-post-ume';

        $canonical = ExamCategory::where('slug', $canonicalSlug)->first();
        $alias = ExamCategory::where('slug', $aliasSlug)->first();

        if ($alias && !$canonical) {
            $alias->update([
                'slug' => $canonicalSlug,
                'name' => 'UNILAG POST UTME',
            ]);
        } elseif ($alias && $canonical) {
            DB::table('exam_category_exam')
                ->where('exam_category_id', $alias->id)
                ->update(['exam_category_id' => $canonical->id]);

            DB::table('exam_category_question')
                ->where('exam_category_id', $alias->id)
                ->update(['exam_category_id' => $canonical->id]);

            $alias->delete();
        }

        $this->replaceExamTypeToken($aliasSlug, $canonicalSlug);

        Subject::query()->each(function (Subject $subject) use ($aliasSlug, $canonicalSlug) {
            $examTypes = $subject->exam_types ?? [];
            $updated = $this->replaceTokenInList($examTypes, $aliasSlug, $canonicalSlug);

            if ($updated !== $examTypes) {
                $subject->update(['exam_types' => $updated]);
            }
        });

        Question::query()->each(function (Question $question) use ($aliasSlug, $canonicalSlug) {
            $examTypes = $question->exam_types ?? [];
            $updated = $this->replaceTokenInList($examTypes, $aliasSlug, $canonicalSlug);

            if ($updated !== $examTypes) {
                $question->update(['exam_types' => $updated]);
            }
        });
    }

    public function down(): void
    {
        // Non-destructive data normalization; no rollback.
    }

    private function replaceExamTypeToken(string $from, string $to): void
    {
        if (!Schema::hasColumn('exams', 'exam_type')) {
            return;
        }

        DB::table('exams')
            ->whereRaw('LOWER(exam_type) = ?', [strtolower($from)])
            ->update(['exam_type' => $to]);
    }

    /**
     * @param  array<int, string|int>  $examTypes
     * @return array<int, string|int>
     */
    private function replaceTokenInList(array $examTypes, string $from, string $to): array
    {
        $normalized = [];

        foreach ($examTypes as $type) {
            if (is_string($type) && strtolower($type) === strtolower($from)) {
                $normalized[] = $to;
                continue;
            }

            $normalized[] = $type;
        }

        return array_values(array_unique($normalized));
    }
};
