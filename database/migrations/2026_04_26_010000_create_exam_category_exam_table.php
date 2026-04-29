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
        if (!Schema::hasTable('exam_category_exam')) {
            Schema::create('exam_category_exam', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_category_id')->constrained()->onDelete('cascade');
                $table->foreignId('exam_id')->constrained()->onDelete('cascade');
                $table->timestamps();

                // Unique constraint to prevent duplicate links
                $table->unique(['exam_category_id', 'exam_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_category_exam');
    }
};
