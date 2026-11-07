<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jobs, resume batches and resume files are soft-deleted from the user
 * dashboard: the rows stay so AI-usage history keeps adding up and the admin
 * can still see (and restore) what a user removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['job_postings', 'resume_batches', 'resume_files'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->softDeletes()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['job_postings', 'resume_batches', 'resume_files'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropSoftDeletes();
            });
        }
    }
};
