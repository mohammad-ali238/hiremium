<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One async provider Batch API submission for the overflow CVs of a resume
 * batch. Polled by `hiremium:poll-batches` until the provider finishes, then
 * its results are ingested into candidates + matches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_batch_id')->constrained('resume_batches')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('provider', 20);                       // openai | gemini
            $table->string('status', 20)->default('submitted');   // submitted|processing|completed|failed|cancelled
            $table->string('provider_batch_id')->nullable();      // batch/operation id at the provider
            $table->string('input_file_id')->nullable();          // uploaded JSONL (OpenAI)
            $table->string('output_file_id')->nullable();         // results JSONL (OpenAI)
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('cost_per_item')->default(1);
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['resume_batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_batches');
    }
};
