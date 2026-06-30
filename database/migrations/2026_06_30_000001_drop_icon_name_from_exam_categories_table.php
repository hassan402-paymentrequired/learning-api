<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_categories', function (Blueprint $table) {
            $table->dropColumn('icon_name');
        });
    }

    public function down(): void
    {
        Schema::table('exam_categories', function (Blueprint $table) {
            $table->string('icon_name')->default('school')->after('slug');
        });
    }
};
