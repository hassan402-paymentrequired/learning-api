<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Local dev / wiped DB only. Safe to re-run (seeders use firstOrCreate / insertOrIgnore).
 *
 *   php artisan db:seed --class=DevMinimumSeeder
 */
class DevMinimumSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SubscriptionPlanSeeder::class,
            UserSeeder::class,
            ExamCategorySeeder::class,
            SubjectSeeder::class,
            SampleQuestionsSeeder::class,
            LinkExamsToCategoriesSeeder::class,
            LinkQuestionsToExamCategoriesSeeder::class,
        ]);

        if ($this->command) {
            $this->command->newLine();
            $this->command->info('Dev minimum seed complete.');
            $this->command->line('  User:  hassan@stepra.com / hassan@stepra');
            $this->command->line('  Admin: admin@stepra.com / admin@stepra');
        }
    }
}
