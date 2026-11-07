<?php

namespace App\Livewire\Uploads;

use App\Jobs\ExtractUploadJob;
use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Job;
use App\Models\ResumeBatch;
use App\Services\Billing\PlanLimits;
use App\Services\Billing\WorkspaceCredits;
use App\Services\Pipeline\BatchProcessor;
use App\Services\Uploads\ResumeUploadService;
use App\Support\Hiremium;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.dashboard')]
#[Title('Resume Upload')]
class Create extends Component
{
    use InteractsWithWorkspace;
    use WithFileUploads;
    use WithPagination;

    public ?int $jobId = null;

    /** Committed selection shown in the list and submitted on save. */
    public array $files = [];

    /** Transient input binding — each selection is appended to $files, then cleared. */
    public $incoming = [];

    /**
     * Files refused at selection time (too large / wrong type / over cap) —
     * never uploaded to storage; only their names + reasons are kept so the
     * batch page can list exactly what was skipped and why.
     *
     * @var array<int, array{name: string, reason: string}>
     */
    public array $rejected = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can('uploads.manage'), 403);

        if ($toast = session('flash_toast')) {
            $this->dispatch('toast', type: $toast['type'] ?? 'info', message: $toast['message'] ?? '');
        }

        // Housekeeping: batches where NOTHING was stored (every file was a
        // duplicate / wrong type / other upload issue) are dead weight — no
        // processing will ever happen. Delete them for this workspace. The
        // 1-minute grace avoids racing an upload that is being created right now;
        // anyone still viewing such a batch is redirected by the page's own
        // liveness check.
        ResumeBatch::forCurrentWorkspace()
            ->where('total_files', 0)
            ->whereDoesntHave('files')
            // Never sweep a queued-intake batch that is still waiting for / running
            // its extraction job — it legitimately has no files yet.
            ->whereNotIn('status', ResumeBatch::EXTRACTING_STATUSES)
            // Keep batches that carry "Skipped files" reasons: a zero-file upload where
            // every CV was a duplicate/invalid is informational — the user needs to
            // read WHY. Only truly empty batches (no files AND nothing to explain) go.
            ->whereNull('rejected_files')
            ->where('created_at', '<', now()->subMinute())
            // Junk, never processed: remove for real rather than soft-delete
            // (nothing to keep for usage history or admin review).
            ->forceDelete();

        $preselect = (int) request()->integer('job');
        if ($preselect && Job::forCurrentWorkspace()->whereKey($preselect)->exists()) {
            $this->jobId = $preselect;
        }
    }

    /** Job changed → recent-uploads list refilters; jump back to page 1. */
    public function updatedJobId(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        // Generous per-upload ceiling (cushion for ZIPs); the real per-CV limit
        // is enforced server-side by ResumeUploadService.
        $maxKb = $this->maxFileMb() * 1024 * 3;

        return [
            'jobId' => ['required', 'integer'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:'.$maxKb, 'mimes:pdf,doc,docx,txt,zip'],
        ];
    }

    /**
     * Each time the file input changes (one file or many, in one dialog or
     * several), append the newly uploaded files to the committed list so a
     * later selection never replaces an earlier one.
     */
    public function updatedIncoming(): void
    {
        $incoming = is_array($this->incoming) ? $this->incoming : [$this->incoming];
        $this->incoming = [];

        $maxMb = $this->maxFileMb();
        $maxBytes = $maxMb * 1024 * 1024;
        $allowed = ['pdf', 'doc', 'docx', 'txt', 'zip'];

        // Per-file checks at intake. Anything refused here is deleted from temp
        // storage right away and remembered by name — never stored, never charged.
        $accepted = [];
        foreach ($incoming as $file) {
            $name = $file->getClientOriginalName();
            $ext = strtolower($file->getClientOriginalExtension());
            $size = (int) $file->getSize();

            if (! in_array($ext, $allowed, true)) {
                $this->reject($file, $name, 'File type not allowed — use PDF, DOCX, DOC or TXT');

                continue;
            }
            // ZIPs get a looser container ceiling (3x); each CV inside is still
            // held to the plan size when extracted. Plain CVs are checked strictly.
            $ceiling = $ext === 'zip' ? $maxBytes * 3 : $maxBytes;
            if ($size > $ceiling) {
                $this->reject($file, $name, sprintf('File too large (%.1f MB) — limit is %d MB per file', $size / 1048576, $ext === 'zip' ? $maxMb * 3 : $maxMb));

                continue;
            }
            $accepted[] = $file;
        }

        // Enforce the plan's per-batch cap at intake: keep what fits, drop the
        // rest by name, and tell the user immediately.
        $max = $this->maxFilesPerBatch();
        $room = max(0, $max - count($this->files));
        $overflow = array_slice($accepted, $room);
        foreach ($overflow as $file) {
            $this->reject($file, $file->getClientOriginalName(), "Over the plan limit of {$max} CVs per upload");
        }
        foreach (array_slice($accepted, 0, $room) as $file) {
            $this->files[] = $file;
        }

        $refused = count($incoming) - count($accepted) + count($overflow);
        if ($refused > 0) {
            $this->dispatch('toast', type: 'warning', message: sprintf(
                '%d file%s not added (see "Skipped files" after upload for the reasons).%s',
                $refused, $refused === 1 ? ' was' : 's were',
                $overflow ? " Your plan allows {$max} CVs per upload — upgrade to upload more at once." : ''
            ));
        }
    }

    /**
     * Files the browser refused before uploading (size/type/cap) — only
     * names + reasons arrive here, never file contents.
     *
     * @param  array<int, array{name?: string, reason?: string}>  $items
     */
    public function rejectClientSide(array $items): void
    {
        foreach (array_slice($items, 0, 500) as $item) {
            $name = mb_substr(trim((string) ($item['name'] ?? '')), 0, 200);
            $reason = mb_substr(trim((string) ($item['reason'] ?? 'Not accepted')), 0, 200);
            if ($name !== '') {
                $this->rejected[] = ['name' => $name, 'reason' => $reason];
            }
        }
    }

    /** Refuse a selected file: drop its temp upload and remember why. */
    private function reject(object $file, string $name, string $reason): void
    {
        $this->rejected[] = ['name' => $name, 'reason' => $reason];

        try {
            if (method_exists($file, 'delete')) {
                $file->delete(); // Livewire TemporaryUploadedFile — remove from temp storage now
            }
        } catch (\Throwable) {
            // best-effort cleanup; temp files are garbage-collected anyway
        }
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    public function save(ResumeUploadService $service, BatchProcessor $processor, WorkspaceCredits $credits)
    {
        abort_unless(Auth::user()->can('uploads.manage'), 403);

        // Multi-account restriction: AI analysis is paused pending admin review.
        // Deliberately vague — reveals neither the signal nor how to get around it.
        if (Auth::user()->isRestricted()) {
            $this->dispatch('toast', type: 'error', message: 'Your account is under review. Please contact support.');

            return null;
        }

        $rateKey = 'upload:'.Auth::id();
        if (RateLimiter::tooManyAttempts($rateKey, 20)) {
            $this->dispatch('toast', type: 'error', message: 'Too many uploads right now — please wait a moment.');

            return null;
        }
        RateLimiter::hit($rateKey, 60);

        $this->validate();

        $workspace = $this->currentWorkspace();
        $job = Job::forCurrentWorkspace()->findOrFail($this->jobId);

        // Each CV costs credits to analyze (charged per file in the pipeline).
        // Block the upload up-front when the balance can't cover even one match,
        // so a zero-credit batch never gets accepted only to fail file-by-file.
        $cost = Hiremium::creditCostPerMatch();
        $balance = $credits->balance($workspace)['total'];

        if ($balance < $cost) {
            $this->dispatch('toast', type: 'error', message: 'You have no credits left to analyze CVs. Please top up your credits and try again.');

            return null;
        }

        // QUEUED INTAKE: move the raw uploads to private staging and hand the heavy
        // work (ZIP expansion, validation, dedupe, split) to a queued job — the
        // request returns instantly, so huge ZIPs can't hit the PHP time limit.
        $groupId = (string) Str::uuid();
        $stagingDir = storage_path("app/private/intake/{$workspace->id}/{$groupId}");
        if (! is_dir($stagingDir)) {
            mkdir($stagingDir, 0750, true);
        }

        $items = [];
        foreach ($this->files as $i => $file) {
            $name = $file->getClientOriginalName();
            $dest = $stagingDir.'/'.$i.'-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
            // TemporaryUploadedFile (Livewire) → copy bytes to staging; then drop the temp.
            $bytes = @file_get_contents($file->getRealPath());
            if ($bytes === false) {
                $bytes = method_exists($file, 'get') ? $file->get() : '';
            }
            file_put_contents($dest, $bytes);
            $items[] = ['path' => $dest, 'name' => $name];
            try {
                if (method_exists($file, 'delete')) {
                    $file->delete();
                }
            } catch (\Throwable) {
            }
        }

        $batch = ResumeBatch::create([
            'workspace_id' => $workspace->id,
            'job_id' => $job->id,
            'created_by' => Auth::id(),
            'status' => ResumeBatch::STATUS_EXTRACTING,
            'upload_group_id' => $groupId,
            'intake' => ['done' => false, 'files' => count($items)],
        ]);

        ExtractUploadJob::dispatch(
            $batch->id, $items, $this->rejected, $this->maxFilesPerBatch(), $this->maxFileMb()
        )->onQueue(config('hiremium.queue.name', 'cv'));

        session()->flash('flash_toast', [
            'type' => 'info',
            'message' => count($items).' file(s) received — preparing your upload. This page updates automatically.',
        ]);

        return $this->redirectRoute('app.batches.show', $batch, navigate: false);
    }

    public function maxFileMb(): int
    {
        return app(PlanLimits::class)->maxFileMb($this->currentWorkspace());
    }

    public function maxFilesPerBatch(): int
    {
        return app(PlanLimits::class)->maxFilesPerBatch($this->currentWorkspace());
    }

    public function render()
    {
        return view('livewire.uploads.create', [
            'jobs' => Job::forCurrentWorkspace()->orderBy('title')->get(['id', 'title']),
            // Recent uploads: for the selected job only (all jobs when none is
            // selected), newest first, 10 per page. Re-queries on job change.
            'recent' => ResumeBatch::forCurrentWorkspace()
                ->when($this->jobId, fn ($q) => $q->where('job_id', $this->jobId))
                ->with('job')
                ->latest('id')
                ->paginate(10),
            'maxFiles' => $this->maxFilesPerBatch(),
            'maxFileMb' => $this->maxFileMb(),
            'credits' => app(WorkspaceCredits::class)->balance($this->currentWorkspace())['total'],
            'creditCost' => Hiremium::creditCostPerMatch(),
        ]);
    }
}
