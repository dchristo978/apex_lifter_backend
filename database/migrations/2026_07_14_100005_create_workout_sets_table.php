<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gym_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('weight_kg', 6, 2);
            $table->unsignedSmallInteger('reps');
            // Estimated 1RM (Epley), precomputed so the leaderboard can sort on it directly.
            $table->decimal('estimated_1rm', 7, 2);
            $table->timestamp('performed_at');
            $table->timestamps();

            $table->index(['machine_id', 'performed_at']);
            $table->index(['user_id', 'machine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_sets');
    }
};
