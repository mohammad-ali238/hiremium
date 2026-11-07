<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sync and async API calls both land in `usage_logs` with the same endpoint
 * string, so the admin stats had no way to split them. Record the lane on the
 * row itself instead of inferring it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->string('lane', 16)->default('sync')->after('endpoint')->index();
        });

        // Backfill: every finished api_job wrote exactly one usage_logs row, so
        // historical async rows are the ones matching a job on user + endpoint
        // inside that job's processing window. Each row is claimed once.
        $claimed = [];

        $jobs = DB::table('api_jobs')
            ->whereNotNull('finished_at')
            ->orderBy('finished_at')
            ->get(['user_id', 'endpoint', 'queued_at', 'started_at', 'finished_at']);

        foreach ($jobs as $job) {
            $id = DB::table('usage_logs')
                ->where('user_id', $job->user_id)
                ->where('endpoint', $job->endpoint)
                ->where('created_at', '>=', $job->started_at ?? $job->queued_at)
                ->where('created_at', '<=', Carbon::parse($job->finished_at)->addSeconds(10))
                ->when($claimed !== [], fn ($query) => $query->whereNotIn('id', $claimed))
                ->orderBy('created_at')
                ->value('id');

            if ($id !== null) {
                $claimed[] = $id;
            }
        }

        if ($claimed !== []) {
            DB::table('usage_logs')->whereIn('id', $claimed)->update(['lane' => 'async']);
        }
    }

    public function down(): void
    {
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->dropIndex(['lane']);
            $table->dropColumn('lane');
        });
    }
};
