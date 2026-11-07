<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One candidate scored against one job: the AI score, reason, matched/missing
 * skills, rubric sub-scores and recruiter status. Unique per candidate+job so
 * re-scoring updates in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_job_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('job_postings')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('resume_batches')->nullOnDelete();
            $table->foreignId('resume_file_id')->nullable()->constrained('resume_files')->nullOnDelete();
            $table->unsignedSmallInteger('score')->nullable();
            $table->string('category', 20)->nullable(); // recommended|consider|not_recommended
            $table->text('reason')->nullable();
            $table->json('matched_skills')->nullable();
            $table->json('missing_skills')->nullable();
            $table->json('sub_scores')->nullable();
            $table->string('status', 20)->default('new'); // new|reviewing|shortlisted|rejected|hired
            $table->string('rejection_reason')->nullable();
            $table->string('processing_status', 20)->default('pending'); // pending|processing|completed|failed
            $table->unsignedInteger('credits_used')->default(0);
            $table->timestamps();

            $table->unique(['candidate_id', 'job_id']);
            $table->index(['workspace_id', 'job_id', 'score']);
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_job_matches');
    }
};
