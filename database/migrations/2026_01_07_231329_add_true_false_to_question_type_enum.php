<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum to include 'true_false'
        // MySQL/MariaDB requires recreating the enum with all values
        \DB::statement("ALTER TABLE questions MODIFY COLUMN question_type ENUM('multiple_choice', 'text_input', 'numeric_input', 'true_false') DEFAULT 'multiple_choice'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to enum without 'true_false'
        \DB::statement("ALTER TABLE questions MODIFY COLUMN question_type ENUM('multiple_choice', 'text_input', 'numeric_input') DEFAULT 'multiple_choice'");
    }
};
