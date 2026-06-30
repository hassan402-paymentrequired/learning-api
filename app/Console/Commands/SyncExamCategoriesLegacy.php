<?php

namespace App\Console\Commands;

use App\Models\ExamCategory;
use App\Models\Question;
use App\Services\ExamCategoryResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncExamCategoriesLegacy extends Command
{
    protected $signature = 'exam-categories:sync-legacy
                            {--dry-run : Preview changes without writing to the database}';

    protected $description = 'Migrate legacy JAMB/DLI exam_types to dynamic exam categories and link pivot tables';

    public function handle(ExamCategoryResolver $resolver): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run mode — no changes will be saved.');
        }

        $categories = ExamCategory::all(['id', 'name', 'slug', 'flow_type']);
        if ($categories->isEmpty()) {
            $this->error('No exam categories found. Create JAMB and DLI categories in admin first.');

            return self::FAILURE;
        }

        $this->info('Exam categories in database:');
        foreach ($categories as $category) {
            $this->line("  [{$category->id}] {$category->slug} — {$category->name} ({$category->flow_type})");
        }

        $this->newLine();
        $this->info('Step 1/3: Normalizing subject exam_types to category slugs...');

        $subjectsUpdated = 0;
        if (!$dryRun) {
            $subjectsUpdated = $resolver->syncAllSubjects();
        } else {
            foreach (\App\Models\Subject::all() as $subject) {
                $normalized = $resolver->normalizeToSlugs($subject->exam_types ?? []);
                if ($normalized !== ($subject->exam_types ?? [])) {
                    $subjectsUpdated++;
                    $this->line("  Subject #{$subject->id} {$subject->name}: " . json_encode($subject->exam_types) . ' → ' . json_encode($normalized));
                }
            }
        }
        $this->line("  Subjects updated: {$subjectsUpdated}");

        $this->newLine();
        $this->info('Step 2/3: Normalizing question exam_types and linking exam_category_question...');

        $questionsUpdated = 0;
        if (!$dryRun) {
            $questionsUpdated = $resolver->syncAllQuestions();
        } else {
            foreach (Question::cursor() as $question) {
                $normalized = $resolver->normalizeToSlugs($question->exam_types ?? []);
                if ($normalized !== ($question->exam_types ?? [])) {
                    $questionsUpdated++;
                }
            }
        }
        $this->line("  Questions updated: {$questionsUpdated}");

        $this->newLine();
        $this->info('Step 3/3: Linking past exams to exam categories...');

        $examsLinked = 0;
        if (!$dryRun) {
            $examsLinked = $resolver->syncAllExams();
        }
        $this->line("  Exams linked: {$examsLinked}");

        if (!$dryRun) {
            $pivotCount = DB::table('exam_category_question')->count();
            $examPivotCount = DB::table('exam_category_exam')->count();

            $this->newLine();
            $this->info('Sync complete.');
            $this->line("  exam_category_question rows: {$pivotCount}");
            $this->line("  exam_category_exam rows: {$examPivotCount}");

            $unlinked = Question::whereDoesntHave('examCategories')->count();
            if ($unlinked > 0) {
                $this->warn("  {$unlinked} question(s) still have no category link — check their exam_types values.");
            }
        } else {
            $this->newLine();
            $this->comment('Dry run finished. Re-run without --dry-run to apply changes.');
        }

        return self::SUCCESS;
    }
}
