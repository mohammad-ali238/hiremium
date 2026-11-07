<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files the user selected but that were NOT stored (too large, wrong type,
 * corrupt, duplicate, over the plan cap…), kept as [{name, reason}] so the
 * batch page can tell the user exactly which files were skipped and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resume_batches', function (Blueprint $table) {
            $table->json('rejected_files')->nullable()->after('failed_count');
        });
    }

    public function down(): void
    {
        Schema::table('resume_batches', function (Blueprint $table) {
            $table->dropColumn('rejected_files');
        });
    }
};
