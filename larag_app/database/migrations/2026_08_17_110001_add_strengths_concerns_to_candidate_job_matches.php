<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured "reasons to hire" / "reasons to reject" from the AI, alongside
 * the free-text reason — so profiles can show them as two labelled lists
 * (weighted by score) instead of one mixed paragraph.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_job_matches', function (Blueprint $table) {
            $table->json('strengths')->nullable()->after('missing_skills');
            $table->json('concerns')->nullable()->after('strengths');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_job_matches', function (Blueprint $table) {
            $table->dropColumn(['strengths', 'concerns']);
        });
    }
};
