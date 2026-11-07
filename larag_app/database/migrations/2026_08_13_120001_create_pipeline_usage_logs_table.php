<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per AI call made by the CV pipeline (parse / match on the live lane,
 * combined parse+score on the batch lane), linked to its resume batch and file
 * so usage can be rolled up per batch and per CV. Kept separate from the
 * API-centric usage_logs table, which stays reserved for WP-API requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resume_batch_id')->nullable()->constrained('resume_batches')->nullOnDelete();
            $table->foreignId('resume_file_id')->nullable()->constrained('resume_files')->nullOnDelete();
            $table->string('operation', 20);                    // parse | match | combined
            $table->string('lane', 10);                         // live | batch
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->unsignedSmallInteger('credits_charged')->default(0);
            $table->unsignedTinyInteger('score')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status', 20)->default('success');   // success | failed
            $table->string('error_message', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index('resume_batch_id');
            $table->index('resume_file_id');
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_usage_logs');
    }
};
