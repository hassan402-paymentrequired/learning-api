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
        Schema::table('exams', function (Blueprint $table) {
            // Remove type - exams are only for past questions now
            $table->dropColumn('type');
            // Remove duration - users select their own time
            $table->dropColumn('duration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->enum('type', ['practice', 'past_question'])->default('past_question')->after('description');
            $table->integer('duration')->default(60)->after('subject');
        });
    }
};
