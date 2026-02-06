<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we need to recreate the table
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN for enum, so we'll use a raw query
            DB::statement("
                CREATE TABLE exams_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title VARCHAR(255) NOT NULL,
                    description TEXT,
                    type VARCHAR(20) DEFAULT 'practice' CHECK(type IN ('practice', 'past_question')),
                    exam_type VARCHAR(20) DEFAULT 'JAMB' CHECK(exam_type IN ('JAMB', 'UNILAG', 'DLI', 'GENERAL')),
                    subject VARCHAR(255),
                    duration INTEGER DEFAULT 60,
                    total_questions INTEGER DEFAULT 0,
                    year INTEGER,
                    is_active BOOLEAN DEFAULT 1,
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP
                )
            ");
            
            DB::statement("INSERT INTO exams_new SELECT * FROM exams");
            DB::statement("DROP TABLE exams");
            DB::statement("ALTER TABLE exams_new RENAME TO exams");
        } else {
            // For MySQL/PostgreSQL, alter the enum
            DB::statement("ALTER TABLE exams MODIFY COLUMN exam_type ENUM('JAMB', 'UNILAG', 'DLI', 'GENERAL') DEFAULT 'JAMB'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                CREATE TABLE exams_old (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title VARCHAR(255) NOT NULL,
                    description TEXT,
                    type VARCHAR(20) DEFAULT 'practice' CHECK(type IN ('practice', 'past_question')),
                    exam_type VARCHAR(20) DEFAULT 'JAMB' CHECK(exam_type IN ('JAMB', 'UNILAG', 'GENERAL')),
                    subject VARCHAR(255),
                    duration INTEGER DEFAULT 60,
                    total_questions INTEGER DEFAULT 0,
                    year INTEGER,
                    is_active BOOLEAN DEFAULT 1,
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP
                )
            ");
            
            DB::statement("INSERT INTO exams_old SELECT * FROM exams WHERE exam_type != 'DLI'");
            DB::statement("DROP TABLE exams");
            DB::statement("ALTER TABLE exams_old RENAME TO exams");
        } else {
            DB::statement("ALTER TABLE exams MODIFY COLUMN exam_type ENUM('JAMB', 'UNILAG', 'GENERAL') DEFAULT 'JAMB'");
        }
    }
};
