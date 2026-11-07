<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-workspace cache of confirmed CSV column mappings, keyed by a hash of the
 * CSV's normalized header row. Lets a repeat import of the same export format
 * skip the AI mapping call entirely — the mapping shown is the last one the
 * user actually confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csv_mapping_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('header_hash', 64);
            $table->json('headers');
            $table->json('mapping');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'header_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csv_mapping_presets');
    }
};
