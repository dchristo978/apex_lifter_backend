<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            // The winner's free-text story shown on their medal (max 100 words,
            // enforced at the API layer).
            $table->text('medal_note')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn('medal_note');
        });
    }
};
