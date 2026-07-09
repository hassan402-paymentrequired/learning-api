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
        Schema::create('department_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['department_id', 'subject_id']);
        });

        if (Schema::hasColumn('subjects', 'department_id')) {
            DB::table('subjects')
                ->whereNotNull('department_id')
                ->orderBy('id')
                ->get()
                ->each(function ($subject) {
                    DB::table('department_subject')->insertOrIgnore([
                        'department_id' => $subject->department_id,
                        'subject_id' => $subject->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

            Schema::table('subjects', function (Blueprint $table) {
                $table->dropForeign(['department_id']);
                $table->dropIndex(['department_id']);
                $table->dropColumn('department_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('order')
                ->constrained('departments')->onDelete('set null');
            $table->index('department_id');
        });

        $links = DB::table('department_subject')
            ->select('subject_id', DB::raw('MIN(department_id) as department_id'))
            ->groupBy('subject_id')
            ->get();

        foreach ($links as $link) {
            DB::table('subjects')
                ->where('id', $link->subject_id)
                ->update(['department_id' => $link->department_id]);
        }

        Schema::dropIfExists('department_subject');
    }
};
