<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiered must-have enforcement: when a job lists MORE than N must-haves and the
 * candidate misses exactly one, apply a softer cap (default 88) instead of the
 * hard cap (74). Missing two or more — or any miss on a short list — stays hard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('must_have_soft_cap_min_skills')->default(4)->after('must_have_cap_score');
            $table->unsignedTinyInteger('must_have_soft_cap_score')->default(88)->after('must_have_soft_cap_min_skills');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', fn (Blueprint $t) => $t->dropColumn(['must_have_soft_cap_min_skills', 'must_have_soft_cap_score']));
    }
};
