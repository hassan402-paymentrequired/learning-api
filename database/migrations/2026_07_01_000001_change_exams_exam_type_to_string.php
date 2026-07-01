<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * exams.exam_type was a legacy ENUM (JAMB, UNILAG, DLI, GENERAL).
     * Exam categories now use slugs (e.g. unilag-post-utme, jamb).
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE exams MODIFY COLUMN exam_type VARCHAR(64) NOT NULL DEFAULT 'jamb'");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE exams ALTER COLUMN exam_type TYPE VARCHAR(64)');
            DB::statement("ALTER TABLE exams ALTER COLUMN exam_type SET DEFAULT 'jamb'");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=OFF');

            DB::statement("
                CREATE TABLE exams_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    description TEXT,
                    exam_type VARCHAR(64) NOT NULL DEFAULT 'jamb',
                    subject VARCHAR(255),
                    total_questions INTEGER NOT NULL DEFAULT 0,
                    year INTEGER,
                    is_active BOOLEAN NOT NULL DEFAULT 1,
                    created_at DATETIME,
                    updated_at DATETIME
                )
            ");

            DB::statement('
                INSERT INTO exams_new (id, title, description, exam_type, subject, total_questions, year, is_active, created_at, updated_at)
                SELECT id, title, description, exam_type, subject, total_questions, year, is_active, created_at, updated_at
                FROM exams
            ');

            DB::statement('DROP TABLE exams');
            DB::statement('ALTER TABLE exams_new RENAME TO exams');

            DB::statement('PRAGMA foreign_keys=ON');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE exams MODIFY COLUMN exam_type ENUM('JAMB', 'UNILAG', 'DLI', 'GENERAL') NOT NULL DEFAULT 'JAMB'");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE exams ALTER COLUMN exam_type TYPE VARCHAR(20)");
            DB::statement("ALTER TABLE exams ALTER COLUMN exam_type SET DEFAULT 'JAMB'");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=OFF');

            DB::statement("
                CREATE TABLE exams_old (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    description TEXT,
                    exam_type VARCHAR(20) NOT NULL DEFAULT 'JAMB' CHECK(exam_type IN ('JAMB', 'UNILAG', 'DLI', 'GENERAL')),
                    subject VARCHAR(255),
                    total_questions INTEGER NOT NULL DEFAULT 0,
                    year INTEGER,
                    is_active BOOLEAN NOT NULL DEFAULT 1,
                    created_at DATETIME,
                    updated_at DATETIME
                )
            ");

            DB::statement("
                INSERT INTO exams_old (id, title, description, exam_type, subject, total_questions, year, is_active, created_at, updated_at)
                SELECT id, title, description, exam_type, subject, total_questions, year, is_active, created_at, updated_at
                FROM exams
                WHERE exam_type IN ('JAMB', 'UNILAG', 'DLI', 'GENERAL')
            ");

            DB::statement('DROP TABLE exams');
            DB::statement('ALTER TABLE exams_old RENAME TO exams');

            DB::statement('PRAGMA foreign_keys=ON');
        }
    }
};
