<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a resume file came from: null = uploaded by the user (default),
 * 'csv' = TXT generated from a CSV-import row. Label only — behavior is
 * identical either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resume_files', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('file_hash');
        });
    }

    public function down(): void
    {
        Schema::table('resume_files', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
