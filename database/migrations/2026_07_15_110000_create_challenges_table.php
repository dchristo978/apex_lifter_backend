<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opponent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            // Gym context: only lifters from this gym may judge in the arena.
            $table->foreignId('gym_id')->nullable()->constrained()->nullOnDelete();

            // The lift both lifters must prove they performed.
            $table->decimal('target_weight_kg', 6, 2);
            $table->unsignedSmallInteger('target_reps');
            $table->unsignedSmallInteger('target_sets');

            // pending → active (both videos in) → completed | declined | cancelled
            $table->string('status')->default('pending');

            $table->string('challenger_video_path')->nullable();
            $table->string('opponent_video_path')->nullable();
            $table->timestamp('challenger_submitted_at')->nullable();
            $table->timestamp('opponent_submitted_at')->nullable();

            // Arena voting window (48h) opens once both proofs are submitted.
            $table->timestamp('voting_ends_at')->nullable();

            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'voting_ends_at']);
            $table->index('winner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
