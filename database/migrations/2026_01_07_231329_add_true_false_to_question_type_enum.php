<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite doesn't support MODIFY COLUMN or enforce enum constraints
        // It stores enum values as strings, so we can skip the migration for SQLite
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't enforce enum constraints, so 'true_false' values will work
            // No action needed for SQLite
            return;
        }

        // For MySQL/MariaDB, modify the enum to include 'true_false'
        DB::statement("ALTER TABLE questions MODIFY COLUMN question_type ENUM('multiple_choice', 'text_input', 'numeric_input', 'true_false') DEFAULT 'multiple_choice'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // SQLite doesn't support MODIFY COLUMN
        if (DB::getDriverName() === 'sqlite') {
            // No action needed for SQLite
            return;
        }

        // For MySQL/MariaDB, revert to enum without 'true_false'
        DB::statement("ALTER TABLE questions MODIFY COLUMN question_type ENUM('multiple_choice', 'text_input', 'numeric_input') DEFAULT 'multiple_choice'");
    }
};
