<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One stored CV file. `file_hash` (SHA-256) backs duplicate detection within a
 * workspace. `candidate_id` is linked once parsing creates the candidate
 * (Phase 7); processing status advances through the pipeline (Phase 6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('resume_batches')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->unsignedBigInteger('candidate_id')->nullable(); // FK added in Phase 7
            $table->string('original_name');
            $table->string('storage_key');
            $table->string('disk', 40);
            $table->string('mime')->nullable();
            $table->string('ext', 10)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->char('file_hash', 64);
            $table->string('status', 20)->default('uploaded'); // uploaded|parsed|analyzed|failed
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'file_hash']);
            $table->index(['batch_id', 'status']);
            $table->index('candidate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_files');
    }
};
