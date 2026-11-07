<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rubric scoring: the AI reports findings and code computes the score
 * (App\Services\Matching\MatchRubric).
 *
 *  - ai_settings.enforce_career_gap / career_gap_cap_score — the third hard cap,
 *    next to minimum experience and must-have skills.
 *  - {candidate_job_matches,resume_files}.ai_score — what the model scored the
 *    candidate on its own. Kept for admin review only; `score` holds the rubric
 *    total, which is the one every recruiter surface shows.
 *  - {candidate_job_matches,resume_files}.score_breakdown — the arithmetic
 *    behind that total: each dimension's quota, percentage and points, the
 *    career-gap verdict and any cap that was applied.
 *
 * Rows analysed before this keep their AI score in `score` with no breakdown,
 * and the UI goes on showing it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->boolean('enforce_career_gap')->default(true)->after('must_have_soft_cap_score');
            $table->unsignedTinyInteger('career_gap_cap_score')->default(80)->after('enforce_career_gap');
        });

        foreach (['candidate_job_matches', 'resume_files'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedTinyInteger('ai_score')->nullable()->after('score');
                $table->json('score_breakdown')->nullable()->after('sub_scores');
            });
        }
    }

    public function down(): void
    {
        Schema::table('ai_settings', fn (Blueprint $t) => $t->dropColumn(['enforce_career_gap', 'career_gap_cap_score']));

        foreach (['candidate_job_matches', 'resume_files'] as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->dropColumn(['ai_score', 'score_breakdown']));
        }
    }
};
