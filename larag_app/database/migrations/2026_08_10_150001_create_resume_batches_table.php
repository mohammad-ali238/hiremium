<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A bulk upload of CVs assigned to a job. Holds the aggregate progress counts
 * the dashboard polls during AI processing (Phase 6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('job_postings')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('uploaded'); // uploaded|processing|completed|partial|failed
            $table->unsignedInteger('total_files')->default(0);
            $table->unsignedInteger('parsed_count')->default(0);
            $table->unsignedInteger('analyzed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_batches');
    }
};
