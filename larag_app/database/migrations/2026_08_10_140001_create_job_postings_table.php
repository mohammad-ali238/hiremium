<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recruiter job postings (the `Job` model). Named `job_postings` to avoid the
 * framework's `jobs` queue table. Structured requirements (must/nice skills,
 * min experience, education) feed consistent AI scoring in Phase 6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('employment_type', 40)->nullable();
            $table->string('location')->nullable();
            $table->string('location_type', 20)->nullable();
            $table->unsignedSmallInteger('min_experience_years')->nullable();
            $table->string('education')->nullable();
            $table->json('must_have_skills')->nullable();
            $table->json('nice_to_have_skills')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
