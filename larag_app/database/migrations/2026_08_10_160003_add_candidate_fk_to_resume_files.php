<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Link a stored CV file to the candidate it produced (set during parsing).
 * SQLite can't add a foreign key via ALTER TABLE, so it's skipped there (the
 * column already exists; the FK is a MySQL-side integrity nicety).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('resume_files', function (Blueprint $table) {
            $table->foreign('candidate_id')->references('id')->on('candidates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('resume_files', function (Blueprint $table) {
            $table->dropForeign(['candidate_id']);
        });
    }
};
