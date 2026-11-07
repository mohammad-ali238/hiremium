<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person in the workspace talent pool. Deduped by email/phone within the
 * workspace, so the same candidate re-uploaded for another job is one record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('job_title')->nullable();
            $table->string('location')->nullable();
            $table->text('summary')->nullable();
            $table->json('skills')->nullable();
            $table->unsignedSmallInteger('experience_years')->nullable();
            $table->json('parsed_data')->nullable();
            $table->unsignedBigInteger('primary_resume_file_id')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'email']);
            $table->index(['workspace_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
