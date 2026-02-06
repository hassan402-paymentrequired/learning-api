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
        Schema::table('exam_attempts', function (Blueprint $table) {
            // Add JSON field to store multiple subjects and their question counts
            // Format: [{"subject": "Mathematics", "question_count": 50}, {"subject": "English", "question_count": 50}]
            $table->json('subjects')->nullable()->after('exam_id');
            
            // Add field to store total duration in minutes
            $table->integer('duration_minutes')->nullable()->after('time_spent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn(['subjects', 'duration_minutes']);
        });
    }
};
