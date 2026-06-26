<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_signups', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->enum('platform', ['ios', 'android']);
            $table->timestamps();

            $table->unique(['email', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_signups');
    }
};
