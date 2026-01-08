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
        Schema::table('questions', function (Blueprint $table) {
            // Make exam_id nullable - questions can exist independently
            $table->foreignId('exam_id')->nullable()->change();
            // Add subject_id to link questions to subjects
            $table->foreignId('subject_id')->nullable()->after('exam_id')->constrained('subjects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropColumn('subject_id');
            // Note: We can't easily revert exam_id to NOT NULL if there are null values
            // This would need manual data cleanup
        });
    }
};
