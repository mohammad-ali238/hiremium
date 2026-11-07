<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The parser's own certainty about a CV (0.00-1.00), kept next to the data it
 * produced so every surface can show it as a percentage:
 *  - resume_files.parse_confidence     — this file's parse (set even when the
 *                                        match was skipped for being too low)
 *  - candidates.parse_confidence       — the parse backing the profile
 *  - pipeline_usage_logs.confidence    — what the parse/combined AI call reported
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resume_files', function (Blueprint $table) {
            $table->decimal('parse_confidence', 3, 2)->nullable()->after('status');
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->decimal('parse_confidence', 3, 2)->nullable()->after('parsed_data');
        });

        Schema::table('pipeline_usage_logs', function (Blueprint $table) {
            $table->decimal('confidence', 3, 2)->nullable()->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('resume_files', function (Blueprint $table) {
            $table->dropColumn('parse_confidence');
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn('parse_confidence');
        });

        Schema::table('pipeline_usage_logs', function (Blueprint $table) {
            $table->dropColumn('confidence');
        });
    }
};
