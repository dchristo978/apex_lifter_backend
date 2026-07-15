<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Social notifications (a new follower) reuse the notification feed but have
     * no machine attached, so machine_id becomes optional. Existing rank and
     * challenge notifications always set it.
     */
    public function up(): void
    {
        Schema::table('rank_notifications', function (Blueprint $table) {
            $table->foreignId('machine_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rank_notifications', function (Blueprint $table) {
            $table->foreignId('machine_id')->nullable(false)->change();
        });
    }
};
