<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gender')->nullable(); // male | female
            $table->date('birth_date')->nullable();
            $table->decimal('body_weight_kg', 5, 2)->nullable();
            $table->timestamp('body_weight_updated_at')->nullable();
            $table->string('fcm_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'gender',
                'birth_date',
                'body_weight_kg',
                'body_weight_updated_at',
                'fcm_token',
            ]);
        });
    }
};
