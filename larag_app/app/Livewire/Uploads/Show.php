<?php

namespace App\Livewire\Uploads;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\CandidateJobMatch;
use App\Models\ResumeBatch;
use App\Models\ResumeFile;
use App\Services\Billing\WorkspaceCredits;
use App\Services\Pipeline\BatchProcessor;
use App\Support\Hiremium;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.dashboard')]
#[Title('Batch')]
class Show extends Component
{
    use InteractsWithWorkspace;
    use WithPagination;

    /**
     * Only the ID is Livewire state: a typed model property would be re-hydrated
     * with findOrFail on EVERY poll request and 404 the moment the batch is
     * deleted — before any liveness check could run. With the id, we resolve the
     * model ourselves and redirect gracefully when it is gone.
     */
    public int $batchId;

    private ?ResumeBatch $resolvedBatch = null;

    public function mount(ResumeBatch $batch): void
    {
        abort_unless(
            $batch->workspace_id === Auth::user()->workspace_id && Auth::user()->can('uploads.manage'),
            403
        );

        if ($toast = session('flash_toast')) {
            $this->dispatch('toast', type: $toast['type'] ?? 'info', message: $toast['message'] ?? '');
        }

        $this->batchId = $batch->id;

        // Soft-deleted (the user removed the job/batch): show a clear page rather than a 404.
        if ($batch->trashed()) {
            $this->deleted = [
                'id' => $batch->id,
                'job_title' => $batch->job_snapshot['title'] ?? $batch->job?->title,
                'deleted_at' => $batch->deleted_at?->toIso8601String(),
            ];
        }
    }

    /** Set when the batch was deleted by the user: { id, job_title, deleted_at }. */
    public ?array $deleted = null;

    /** Current batch or null when it has been deleted. Cached per request. */
    private function batch(): ?ResumeBatch
    {
        return $this->resolvedBatch ??= ResumeBatch::with('job')->find($this->batchId);
    }

    /** Match currently shown in the candidate popup (null = closed). */
    public ?int $viewingFileId = null;

    /** Open the candidate detail popup for a file in this batch. */
    public function showCandidate(int $fileId)
    {
        $batch = $this->batch();
        if (! $batch) {
            return $this->leaveDeletedBatch();
        }
        abort_unless(
            $batch->workspace_id === Auth::user()->workspace_id && Auth::user()->can('uploads.manage'),
            403
        );

        // Only files that belong to this batch can be opened.
        if (! $batch->files()->whereKey($fileId)->exists()) {
            return null;
        }

        $this->viewingFileId = $fileId;
        $this->dispatch('open-modal', 'candidate-detail');
    }

    public function closeCandidate(): void
    {
        $this->viewingFileId = null;
    }

    /**
     * Poll target: if this batch has been deleted (empty-batch cleanup on the
     * upload page, or an admin purge), send the viewer back to /upload with a
     * notice. When the batch is alive this is a cheap no-op (no re-render).
     */
    public function checkAlive()
    {
        if (ResumeBatch::whereKey($this->batchId)->exists()) {
            $this->skipRender();

            return null;
        }

        return $this->leaveDeletedBatch();
    }

    private function leaveDeletedBatch()
    {
        session()->flash('flash_toast', [
            'type' => 'info',
            'message' => 'That batch no longer exists — it was removed (empty or deleted). You are back at Resume Upload.',
        ]);

        return $this->redirectRoute('app.upload', navigate: false);
    }

    /**
     * Re-run only the files that stopped because credits ran out. Checks the
     * balance first so a click with an empty wallet doesn't just re-fail them.
     */
    public function resumeCreditFailures(BatchProcessor $processor, WorkspaceCredits $credits)
    {
        $batch = $this->batch();
        if (! $batch) {
            return $this->leaveDeletedBatch();
        }
        abort_unless(
            $batch->workspace_id === Auth::user()->workspace_id && Auth::user()->can('uploads.manage'),
            403
        );

        // Multi-account restriction: AI analysis is paused pending admin review.
        // Deliberately vague — reveals neither the signal nor how to get around it.
        if (Auth::user()->isRestricted()) {
            $this->dispatch('toast', type: 'error', message: 'Your account is under review. Please contact support.');

            return null;
        }

        $waiting = $batch->files()->where('status', ResumeFile::STATUS_FAILED)->where('failure_kind', ResumeFile::FAIL_CREDITS)->count();
        if ($waiting === 0) {
            $this->dispatch('toast', type: 'success', message: 'No CVs are waiting on credits.');

            return null;
        }

        $balance = $credits->balance($this->currentWorkspace())['total'];
        $cost = Hiremium::creditCostPerMatch();
        if ($balance < $cost) {
            $this->dispatch('toast', type: 'error', message: 'You still have no credits. Top up first, then resume.');

            return null;
        }

        $count = $processor->retryCreditFailures($batch);
        $affordable = $cost > 0 ? intdiv($balance, $cost) : $count;

        $this->dispatch('toast',
            type: $count > $affordable ? 'warning' : 'info',
            message: $count > $affordable
                ? "Resuming {$count} CV(s) — your {$balance} credit(s) cover {$affordable}; the rest will wait again until you top up."
                : "Resuming {$count} CV(s) that were waiting on credits…"
        );
    }

    public function retryFailed(BatchProcessor $processor, WorkspaceCredits $credits)
    {
        $batch = $this->batch();
        if (! $batch) {
            return $this->leaveDeletedBatch();
        }
        abort_unless(
            $batch->workspace_id === Auth::user()->workspace_id && Auth::user()->can('uploads.manage'),
            403
        );

        // Multi-account restriction: AI analysis is paused pending admin review.
        // Deliberately vague — reveals neither the signal nor how to get around it.
        if (Auth::user()->isRestricted()) {
            $this->dispatch('toast', type: 'error', message: 'Your account is under review. Please contact support.');

            return null;
        }

        // Every re-run costs a credit per CV. Refuse up front when the wallet can't
        // cover even one, instead of dispatching jobs that each fail per-file.
        $cost = Hiremium::creditCostPerMatch();
        $balance = $credits->balance($this->currentWorkspace())['total'];
        if ($balance < $cost) {
            $this->dispatch('toast', type: 'error', message: 'You have no credits left to re-run these CVs. Top up first, then retry.');

            return null;
        }

        $count = $processor->retryFailed($batch);

        $this->dispatch('toast',
            type: $count ? 'info' : 'success',
            message: $count ? "Retrying {$count} failed file(s)…" : 'No failed files to retry.'
        );
    }

    public function render()
    {
        if ($this->deleted) {
            return view('livewire.uploads.show-deleted', ['deleted' => $this->deleted]);
        }

        // Deleted while viewing (cleanup / purge)? Redirect instead of 404ing the poll.
        $batch = $this->batch();
        if (! $batch) {
            $this->leaveDeletedBatch();

            // Redirect skips rendering; the return value is unused but must be a view.
            return view('livewire.uploads.show-gone');
        }

        // Each FILE carries its own AI result. The candidate's match (one per
        // candidate+job) is only used to know which file is the candidate's
        // current best, and to link the popup to the full profile.
        //
        // Rank in SQL so the list can be paginated (100/page) without loading
        // every file: scored first (highest score), then still-analyzing in
        // upload order, then failed. The page polls while processing, so every
        // new result re-sorts the table.
        $pendingStatuses = "'".ResumeFile::STATUS_UPLOADED."','".ResumeFile::STATUS_SUBMITTED."','".ResumeFile::STATUS_PARSED."'";
        $files = $batch->files()
            ->with('candidate')
            ->orderByRaw("CASE WHEN score IS NOT NULL THEN 0 WHEN status IN ({$pendingStatuses}) THEN 1 ELSE 2 END")
            ->orderByRaw('score IS NULL, score DESC')
            ->orderBy('id')
            ->paginate(100);

        $candidateIds = $files->getCollection()->pluck('candidate_id')->filter()->unique()->values();
        $matchesByCandidate = CandidateJobMatch::where('job_id', $batch->job_id)
            ->whereIn('candidate_id', $candidateIds)
            ->get()
            ->keyBy('candidate_id');

        // "Same person" note: count sibling files per candidate across the WHOLE
        // batch (they may sit on other pages), without loading them all.
        $siblingCounts = $batch->files()
            ->whereNotNull('candidate_id')
            ->selectRaw('candidate_id, count(*) as c')
            ->groupBy('candidate_id')
            ->pluck('c', 'candidate_id');

        // Popup: fetch the selected file directly (it may be on another page).
        $viewing = null;
        if ($this->viewingFileId) {
            $viewing = $batch->files()->with('candidate')->whereKey($this->viewingFileId)->first();
            if ($viewing?->candidate_id && ! isset($matchesByCandidate[$viewing->candidate_id])) {
                $m = CandidateJobMatch::where('job_id', $batch->job_id)->where('candidate_id', $viewing->candidate_id)->first();
                if ($m) {
                    $matchesByCandidate[$viewing->candidate_id] = $m;
                }
            }
        }

        return view('livewire.uploads.show', [
            'batch' => $batch,
            'files' => $files,
            'matchesByCandidate' => $matchesByCandidate,
            'siblingCounts' => $siblingCounts,
            'viewing' => $viewing,
            'viewingMatch' => $viewing?->candidate_id ? ($matchesByCandidate[$viewing->candidate_id] ?? null) : null,
            'creditsUsed' => (int) $batch->usageLogs()->sum('credits_charged'),
            'creditFailed' => $batch->files()->where('status', ResumeFile::STATUS_FAILED)->where('failure_kind', ResumeFile::FAIL_CREDITS)->count(),
            'errorFailed' => $batch->files()->where('status', ResumeFile::STATUS_FAILED)->where(fn ($q) => $q->whereNull('failure_kind')->orWhere('failure_kind', '!=', ResumeFile::FAIL_CREDITS))->count(),
            'rejected' => array_values((array) ($batch->rejected_files ?? [])),
            // Queued intake state + the other batches this same upload produced.
            'intake' => (array) ($batch->intake ?? []),
            'siblingBatches' => $batch->upload_group_id
                ? $batch->siblings()->get(['id', 'status', 'total_files', 'analyzed_count', 'failed_count', 'created_at'])
                : collect(),
        ]);
    }
}
