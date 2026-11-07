<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Must-have skill enforcement: the AI returns a per-skill verdict for each of
 * the job's must-haves; code counts coverage and (when enabled) hard-caps the
 * score if any must-have is unmet. Coverage is stored per match/file for the UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->boolean('enforce_must_have_skills')->default(true)->after('min_experience_cap_score');
            $table->unsignedTinyInteger('must_have_cap_score')->default(74)->after('enforce_must_have_skills');
        });
        Schema::table('candidate_job_matches', function (Blueprint $table) {
            $table->json('must_have_coverage')->nullable()->after('concerns');
        });
        Schema::table('resume_files', function (Blueprint $table) {
            $table->json('must_have_coverage')->nullable()->after('concerns');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', fn (Blueprint $t) => $t->dropColumn(['enforce_must_have_skills', 'must_have_cap_score']));
        Schema::table('candidate_job_matches', fn (Blueprint $t) => $t->dropColumn('must_have_coverage'));
        Schema::table('resume_files', fn (Blueprint $t) => $t->dropColumn('must_have_coverage'));
    }
};
