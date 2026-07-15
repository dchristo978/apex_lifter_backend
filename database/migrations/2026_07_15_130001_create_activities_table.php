<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The social activity feed. One row per notable thing a lifter does —
     * a new personal record, a medal won, a gym check-in. Display fields are
     * denormalised into `meta` at write time so the feed can render without
     * joins and survives deletion of the underlying set/check-in.
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // pr | medal | checkin (see Activity::TYPE_* constants).
            $table->string('type', 20);
            // Optional pointer to the domain row that triggered this activity
            // (workout_set / checkin / challenge id). Not a FK — the row may be
            // deleted while the feed entry lives on via its denormalised meta.
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            // The feed pulls activities for a set of user_ids, newest first.
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
