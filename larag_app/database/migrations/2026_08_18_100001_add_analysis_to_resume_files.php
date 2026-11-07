<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every analyzed file keeps ITS OWN AI result. Previously the result was
 * written only onto candidate_job_matches (one row per candidate+job), so a
 * second/third CV for the same person silently overwrote the first — the user
 * paid N credits but could see 1 score. Now: file = what was analyzed (always
 * kept); match = the candidate's current standing (points at the best file).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resume_files', function (Blueprint $table) {
            $table->foreignId('job_id')->nullable()->after('candidate_id')->constrained('job_postings')->nullOnDelete();
            $table->unsignedSmallInteger('score')->nullable()->after('status');
            $table->string('category', 30)->nullable()->after('score');
            $table->text('reason')->nullable()->after('category');
            $table->json('matched_skills')->nullable()->after('reason');
            $table->json('missing_skills')->nullable()->after('matched_skills');
            $table->json('strengths')->nullable()->after('missing_skills');
            $table->json('concerns')->nullable()->after('strengths');
            $table->json('sub_scores')->nullable()->after('concerns');
            $table->timestamp('analyzed_at')->nullable()->after('sub_scores');

            $table->index(['candidate_id', 'job_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::table('resume_files', function (Blueprint $table) {
            $table->dropIndex(['candidate_id', 'job_id', 'score']);
            $table->dropConstrainedForeignId('job_id');
            $table->dropColumn(['score', 'category', 'reason', 'matched_skills', 'missing_skills', 'strengths', 'concerns', 'sub_scores', 'analyzed_at']);
        });
    }
};
