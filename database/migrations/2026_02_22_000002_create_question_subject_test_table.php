<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Pivot: a question can belong to multiple subject tests (many-to-many).
     */
    public function up(): void
    {
        Schema::create('question_subject_test', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_test_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['question_id', 'subject_test_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_subject_test');
    }
};
