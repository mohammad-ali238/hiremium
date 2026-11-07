<?php

namespace App\Services\Pipeline;

use App\Models\ResumeBatch;
use App\Models\ResumeFile;
use App\Models\User;
use App\Notifications\BatchCompleted;
use App\Notifications\BatchWaitingCredits;

/**
 * Recomputes a batch's aggregate counts and terminal status from its files,
 * notifying the creator once when it first reaches a terminal state. Shared by
 * the live pipeline and the async batch ingestor.
 */
class BatchStatusRecomputer
{
    public function recompute(ResumeBatch $batch): void
    {
        $batch->refresh();

        $analyzed = $batch->files()->where('status', ResumeFile::STATUS_ANALYZED)->count();
        $parsed = $batch->files()->whereIn('status', [ResumeFile::STATUS_PARSED, ResumeFile::STATUS_ANALYZED])->count();
        // Split the failures: credit-waits are NOT failures — the CV is fine and
        // will run once the user tops up and resumes.
        $creditWaits = $batch->files()->where('status', ResumeFile::STATUS_FAILED)->where('failure_kind', ResumeFile::FAIL_CREDITS)->count();
        $realFailed = $batch->files()->where('status', ResumeFile::STATUS_FAILED)
            ->where(fn ($q) => $q->whereNull('failure_kind')->orWhere('failure_kind', '!=', ResumeFile::FAIL_CREDITS))
            ->count();
        $settled = $analyzed + $realFailed + $creditWaits;

        $status = $batch->status;
        if ($batch->total_files > 0 && $settled >= $batch->total_files) {
            if ($creditWaits > 0) {
                // Everything affordable is done; the rest waits for credits.
                $status = ResumeBatch::STATUS_WAITING_CREDITS;
            } else {
                $status = $realFailed === 0
                    ? ResumeBatch::STATUS_COMPLETED
                    : ($analyzed > 0 ? ResumeBatch::STATUS_PARTIAL : ResumeBatch::STATUS_FAILED);
            }
        }

        $batch->update([
            'analyzed_count' => $analyzed,
            'failed_count' => $realFailed,      // real failures only — credit-waits are shown separately
            'parsed_count' => $parsed,
            'status' => $status,
        ]);

        if (! $batch->created_by) {
            return;
        }
        $user = User::find($batch->created_by);
        if (! $user) {
            return;
        }

        // "Finished" is sent at most ONCE per batch (timestamp, not a status
        // transition — so a resume → terminal-again never re-emails).
        $terminal = [ResumeBatch::STATUS_COMPLETED, ResumeBatch::STATUS_PARTIAL, ResumeBatch::STATUS_FAILED];
        if (in_array($status, $terminal, true) && ! $batch->completed_notified_at) {
            $user->notify(new BatchCompleted($batch->id, $batch->job?->title ?? 'your job', $analyzed, $realFailed, $status));
            $batch->forceFill(['completed_notified_at' => now()])->save();
        }

        // "Waiting on credits" — its own clearly-worded notice, also at most once.
        if ($status === ResumeBatch::STATUS_WAITING_CREDITS && ! $batch->credits_notified_at) {
            $user->notify(new BatchWaitingCredits($batch->id, $batch->job?->title ?? 'your job', $analyzed, $creditWaits));
            $batch->forceFill(['credits_notified_at' => now()])->save();
        }
    }
}
