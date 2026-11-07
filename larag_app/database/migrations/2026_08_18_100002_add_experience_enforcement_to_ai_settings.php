<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimum-experience enforcement for match scoring: when ON, a candidate whose
 * computed total experience is below the job's minimum has their score capped
 * (in code, after the AI responds), regardless of what the model returned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->boolean('enforce_min_experience')->default(true)->after('max_tokens');
            $table->unsignedTinyInteger('min_experience_cap_score')->default(59)->after('enforce_min_experience');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn(['enforce_min_experience', 'min_experience_cap_score']);
        });
    }
};
