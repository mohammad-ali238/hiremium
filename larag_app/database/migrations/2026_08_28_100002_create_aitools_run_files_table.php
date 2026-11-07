<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Files uploaded with a run. One row per file so tools that take more
        // than one document later (compare two CVs, CV + portfolio) just work.
        Schema::create('aitools_run_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aitools_run_id')->constrained('aitools_runs')->cascadeOnDelete();
            $table->string('role', 32)->default('resume');   // which file slot of the tool
            $table->string('disk', 40);
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->char('sha256', 64)->index();
            $table->unsignedInteger('extracted_chars')->nullable();
            $table->unsignedSmallInteger('pages')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aitools_run_files');
    }
};
