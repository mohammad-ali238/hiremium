<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why a file failed: 'credits' (workspace ran out — the CV itself is fine and
 * just needs re-running after a top-up) vs 'error' (parse/AI/infra failure).
 * Lets the UI and retry actions treat them differently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resume_files', function (Blueprint $table) {
            $table->string('failure_kind', 20)->nullable()->after('error')->index();
        });
    }

    public function down(): void
    {
        Schema::table('resume_files', function (Blueprint $table) {
            $table->dropColumn('failure_kind');
        });
    }
};
