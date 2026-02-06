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
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['practice', 'past_question'])->default('practice');
            $table->enum('exam_type', ['JAMB', 'UNILAG', 'GENERAL'])->default('JAMB');
            $table->string('subject')->nullable(); // Mathematics, English, etc.
            $table->integer('duration')->default(60); // Duration in minutes
            $table->integer('total_questions')->default(0);
            $table->integer('year')->nullable(); // For past questions
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
