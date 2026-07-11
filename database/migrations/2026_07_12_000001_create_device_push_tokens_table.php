<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token');
            $table->char('token_hash', 64);
            $table->string('platform', 20)->nullable(); // ios | android
            $table->string('timezone')->nullable();
            $table->timestamps();

            $table->unique('token_hash');
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_push_tokens');
    }
};
