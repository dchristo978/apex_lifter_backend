<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voter_id')->constrained('users')->cascadeOnDelete();

            // Who the judge deems the valid winner: challenger | opponent | invalid
            $table->string('choice');

            // Per-criterion assessment: { load, form, machine, reps_sets } booleans.
            $table->json('criteria')->nullable();

            // Reason when the judge rejects a criterion or votes "invalid".
            $table->string('reason_code')->nullable();
            $table->string('reason_text')->nullable();

            $table->timestamps();

            $table->unique(['challenge_id', 'voter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_votes');
    }
};
