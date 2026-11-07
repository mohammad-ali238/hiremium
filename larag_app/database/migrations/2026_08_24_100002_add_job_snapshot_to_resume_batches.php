<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every batch keeps a frozen copy of the job it was analysed against. The
 * pipeline scores against this snapshot (not the live job), so editing a job
 * later can never change what an in-flight or finished analysis was based on,
 * and anyone can see the exact job the results refer to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resume_batches', function (Blueprint $table) {
            $table->json('job_snapshot')->nullable()->after('job_id');
        });

        // Best-effort backfill for existing batches from the job's CURRENT
        // values (the original values at analysis time are unknowable).
        $jobColumns = [
            'id', 'workspace_id', 'created_by', 'title', 'description', 'employment_type',
            'location', 'location_type', 'min_experience_years', 'education',
            'must_have_skills', 'nice_to_have_skills', 'status', 'created_at', 'updated_at',
        ];

        DB::table('resume_batches')
            ->whereNull('job_snapshot')
            ->orderBy('id')
            ->chunkById(200, function ($batches) use ($jobColumns) {
                $jobs = DB::table('job_postings')
                    ->whereIn('id', $batches->pluck('job_id')->unique()->all())
                    ->get($jobColumns)
                    ->keyBy('id');

                foreach ($batches as $batch) {
                    $job = $jobs->get($batch->job_id);
                    if (! $job) {
                        continue;
                    }

                    $snapshot = (array) $job;
                    foreach (['must_have_skills', 'nice_to_have_skills'] as $jsonCol) {
                        $snapshot[$jsonCol] = is_string($snapshot[$jsonCol] ?? null) ? json_decode($snapshot[$jsonCol], true) : ($snapshot[$jsonCol] ?? null);
                    }
                    $snapshot['snapshot_taken_at'] = $batch->created_at;
                    $snapshot['snapshot_backfilled'] = true;

                    DB::table('resume_batches')->where('id', $batch->id)->update(['job_snapshot' => json_encode($snapshot)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('resume_batches', function (Blueprint $table) {
            $table->dropColumn('job_snapshot');
        });
    }
};
