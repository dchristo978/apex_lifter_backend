<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rank_notifications', function (Blueprint $table) {
            // Reuse the notification feed for challenge events. 'rank' keeps the
            // existing leaderboard notifications; 'challenge' deep-links to a
            // challenge via challenge_id.
            $table->string('type')->default('rank')->after('user_id');
            $table->foreignId('challenge_id')->nullable()->after('overtaken_by_user_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rank_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('challenge_id');
            $table->dropColumn('type');
        });
    }
};
