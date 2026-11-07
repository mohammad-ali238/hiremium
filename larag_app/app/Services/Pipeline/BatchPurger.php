<?php

namespace App\Services\Pipeline;

use App\Models\Candidate;
use App\Models\CandidateJobMatch;
use App\Models\PipelineUsageLog;
use App\Models\ResumeBatch;
use App\Models\ResumeFile;
use App\Services\Storage\CvStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hard-deletes a resume batch and EVERYTHING it produced: stored CV files on
 * disk, resume_files, matches, provider batch requests, usage logs, and any
 * candidate that exists only because of this batch. Intended for cleaning up
 * after test runs — the admin action that calls it is hidden in production.
 */
class BatchPurger
{
    public function __construct(private CvStorage $storage) {}

    /**
     * @return array{files_deleted:int, candidates_deleted:int, matches_deleted:int}
     */
    public function purge(ResumeBatch $batch): array
    {
        $files = $batch->files()->withTrashed()->get(['id', 'storage_key', 'disk', 'candidate_id']);
        $candidateIds = $files->pluck('candidate_id')->filter()->unique()->values();

        // 1) Physical CV files.
        $filesDeleted = 0;
        foreach ($files as $file) {
            try {
                if ($file->storage_key && $this->storage->exists($file->storage_key)) {
                    $this->storage->delete($file->storage_key);
                    $filesDeleted++;
                }
            } catch (Throwable $e) {
                Log::warning('BatchPurger: could not delete CV file', ['key' => $file->storage_key, 'error' => $e->getMessage()]);
            }
        }

        // Queued-intake staging: raw uploads waiting for / mid extraction live under
        // storage/app/private/intake/{workspace}/{group}. Remove them with the batch.
        if ($batch->upload_group_id) {
            $staging = storage_path("app/private/intake/{$batch->workspace_id}/{$batch->upload_group_id}");
            if (is_dir($staging)) {
                foreach (glob($staging.'/*') ?: [] as $f) {
                    @unlink($f);
                }
                @rmdir($staging);
            }
        }

        return DB::transaction(function () use ($batch, $candidateIds, $filesDeleted) {
            // 2) Matches created by this batch (also cascades from files, but be explicit).
            $matchesDeleted = CandidateJobMatch::where('batch_id', $batch->id)->delete();

            // 3) Usage logs + provider batches + resume_files go via FK (nullOnDelete / cascade);
            //    delete usage logs explicitly so nothing lingers pointing at a gone batch.
            PipelineUsageLog::where('resume_batch_id', $batch->id)->delete();

            // 4) The batch itself — a real delete (cascades resume_files + provider_batches).
            $batch->forceDelete();

            // 5) Candidates that no longer have any resume file or match anywhere.
            $candidatesDeleted = 0;
            foreach ($candidateIds as $cid) {
                $stillReferenced = ResumeFile::where('candidate_id', $cid)->exists()
                    || CandidateJobMatch::where('candidate_id', $cid)->exists();
                if (! $stillReferenced) {
                    Candidate::whereKey($cid)->delete();
                    $candidatesDeleted++;
                }
            }

            return [
                'files_deleted' => $filesDeleted,
                'candidates_deleted' => $candidatesDeleted,
                'matches_deleted' => (int) $matchesDeleted,
            ];
        });
    }
}
