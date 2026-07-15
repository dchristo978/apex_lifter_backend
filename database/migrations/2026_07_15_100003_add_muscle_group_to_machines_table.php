<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            // Exact muscle group as shown in the gym exercise library
            // (e.g. Chest, Quadriceps, Upper Back, Lats, Triceps, ...).
            $table->string('muscle_group')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->dropColumn('muscle_group');
        });
    }
};
