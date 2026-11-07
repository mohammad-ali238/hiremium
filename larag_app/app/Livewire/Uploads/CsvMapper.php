<?php

namespace App\Livewire\Uploads;

use App\Jobs\ImportCsvJob;
use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\CsvMappingPreset;
use App\Models\Job;
use App\Models\ResumeBatch;
use App\Services\Billing\PlanLimits;
use App\Services\Billing\WorkspaceCredits;
use App\Services\CsvImport\CandidateFields;
use App\Services\CsvImport\CsvImportException;
use App\Services\CsvImport\CsvRowReader;
use App\Services\CsvImport\CsvSampler;
use App\Services\CsvImport\MappingAi;
use App\Support\Hiremium;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * CSV Upload tab: accepts ONE candidate CSV, samples it, gets a column→field
 * mapping (saved preset by header-hash, else one AI call, else heuristics) and
 * lets the user review/adjust and confirm the mapping. Confirming saves the
 * workspace's mapping preset; the actual import pipeline arrives in part 2.
 */
class CsvMapper extends Component
{
    use InteractsWithWorkspace;
    use WithFileUploads;

    public const NO_CANDIDATE_DATA_MESSAGE = 'We couldn\'t find candidate details in this file. It must include at least a Name, an Email, and a Job Title or Bio column.';

    public const INCONSISTENT_MESSAGE = 'This file\'s data doesn\'t look right — some columns contain mixed or unrelated values. Please fix the CSV and upload it again.';

    public ?int $jobId = null;

    /** The single CSV being mapped (Livewire temporary upload). */
    public $csvFile = null;

    /** 'pick' (no mapping yet) or 'mapped' (panel visible). */
    public string $stage = 'pick';

    /** Blocking problem with the picked file (shown as an error box). */
    public ?string $error = null;

    /** Non-blocking notice (fallback used / no header row detected). */
    public ?string $notice = null;

    /** Where the pre-filled mapping came from: ai | preset | fallback. */
    public string $source = '';

    public string $fileName = '';

    public int $rowCount = 0;

    /** @var list<string> unique column labels shown in every dropdown */
    public array $headers = [];

    /** @var array<string, ?string> field => column label (null = not mapped) */
    public array $mapping = [];

    public string $headerHash = '';

    /** Whether the file's first row is a header row (false = it is data too). */
    public bool $hasHeaderRow = true;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('uploads.manage'), 403);
    }

    /** A new CSV was picked: sample it and build the suggested mapping. */
    public function updatedCsvFile(CsvSampler $sampler, MappingAi $ai): void
    {
        $this->resetMappingState();

        $file = $this->csvFile;
        if (! $file) {
            return;
        }

        $maxMb = $this->maxFileMb();
        $this->validate(
            ['csvFile' => 'file|max:'.($maxMb * 1024)],
            ['csvFile.max' => "File too large — limit is {$maxMb} MB."],
        );

        if (strtolower($file->getClientOriginalExtension()) !== 'csv') {
            $this->failFile('Only CSV files are accepted here — save your spreadsheet as .csv and try again.');

            return;
        }

        $this->fileName = $file->getClientOriginalName();

        try {
            $sampled = $sampler->sample($file->getRealPath());
        } catch (CsvImportException $e) {
            $this->failFile($e->getMessage());

            return;
        }

        $firstRow = $sampled['headers'];
        $this->applyMode($sampled['has_header_guess'], $firstRow, $sampled['row_count']);

        // Same header row (or same positional shape) as a previously confirmed
        // import? Reuse that mapping — no AI call needed.
        $preset = CsvMappingPreset::forCurrentWorkspace()
            ->where('header_hash', $this->headerHash)
            ->first();

        if ($preset) {
            $this->mapping = CandidateFields::sanitizeMapping((array) $preset->mapping, $this->headers);
            $this->source = 'preset';
            $this->stage = 'mapped';
            if (! $this->hasHeaderRow) {
                $this->notice = 'No header row detected — columns are listed by position (Col #1, Col #2 …).';
            }

            return;
        }

        // Fresh file → one small AI call (rate-limited; heuristics cover
        // testing mode, AI failures and limit hits, so this never dead-ends).
        // The AI maps columns BY POSITION and decides whether the first row is
        // a header — so headerless CSVs work too.
        $rateKey = 'csv-mapping:'.Auth::id();
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $suggestion = $ai->heuristicSuggestion($firstRow, $sampled['samples'], $this->hasHeaderRow);
        } else {
            RateLimiter::hit($rateKey, 60);
            $suggestion = $ai->suggest($firstRow, $sampled['samples'], $this->hasHeaderRow);
        }

        // The AI's header verdict wins — recompute labels/count/hash when it
        // disagrees with the local guess.
        $this->applyMode((bool) $suggestion['has_header_row'], $firstRow, $sampled['row_count']);

        if (! $suggestion['consistent']) {
            $this->failFile(self::INCONSISTENT_MESSAGE);

            return;
        }

        if (! $suggestion['has_candidate_data'] || ! MappingAi::positionsSatisfyCompulsory($suggestion['positions'])) {
            $this->failFile(self::NO_CANDIDATE_DATA_MESSAGE);

            return;
        }

        // positions (0-based column indexes) → the labels shown in the UI.
        $this->mapping = array_map(
            fn ($i) => $i !== null ? ($this->headers[$i] ?? null) : null,
            $suggestion['positions'],
        );
        $this->source = $suggestion['source'];
        $this->stage = 'mapped';

        $notices = [];
        if (! $this->hasHeaderRow) {
            $notices[] = 'No header row detected — columns are listed by position (Col #1, Col #2 …).';
        }
        if ($this->source === 'fallback') {
            $notices[] = 'Automatic matching used a built-in fallback — please review each field below.';
        }
        $this->notice = $notices ? implode(' ', $notices) : null;
    }

    /**
     * Set header-mode-dependent state: the column labels (real header names or
     * positional Col #N), the data-row count (a headerless file's first row IS
     * data) and the preset hash.
     *
     * @param  list<string>  $firstRow
     */
    private function applyMode(bool $hasHeaderRow, array $firstRow, int $sampledRowCount): void
    {
        $this->hasHeaderRow = $hasHeaderRow;
        $this->headers = $hasHeaderRow
            ? CsvRowReader::uniqueLabels($firstRow)
            : CsvRowReader::positionalLabels(count($firstRow));
        $this->rowCount = $sampledRowCount + ($hasHeaderRow ? 0 : 1);
        $this->headerHash = CsvMappingPreset::hashHeaders($this->headers);
    }

    /**
     * Confirm the (possibly adjusted) mapping: save it as the workspace preset,
     * stage the CSV, and hand the import to the queued job — each valid row
     * becomes a generated TXT CV that runs the normal parse + match pipeline.
     */
    public function confirm(WorkspaceCredits $credits)
    {
        abort_unless(Auth::user()->can('uploads.manage'), 403);

        // Multi-account restriction: AI analysis is paused pending admin review.
        // Deliberately vague — reveals neither the signal nor how to get around it.
        if (Auth::user()->isRestricted()) {
            $this->dispatch('toast', type: 'error', message: 'Your account is under review. Please contact support.');

            return null;
        }

        if ($this->stage !== 'mapped' || $this->headers === []) {
            return null;
        }

        $rateKey = 'upload:'.Auth::id();
        if (RateLimiter::tooManyAttempts($rateKey, 20)) {
            $this->dispatch('toast', type: 'error', message: 'Too many uploads right now — please wait a moment.');

            return null;
        }

        $this->validate(['jobId' => 'required|integer'], ['jobId.required' => 'Select the job these candidates are for.']);
        $job = Job::forCurrentWorkspace()->findOrFail($this->jobId);

        $mapping = CandidateFields::sanitizeMapping($this->mapping, $this->headers);

        // The user may have cleared a required field by hand — re-check.
        if (! CandidateFields::satisfiesCompulsory($mapping)) {
            $this->dispatch('toast', type: 'error', message: 'Name, Email, and a Job Title or Bio column must be mapped before you can continue.');

            return null;
        }

        $workspace = $this->currentWorkspace();

        // Each imported row costs credits to analyze — same up-front gate as
        // the bulk file upload.
        if ($credits->balance($workspace)['total'] < Hiremium::creditCostPerMatch()) {
            $this->dispatch('toast', type: 'error', message: 'You have no credits left to analyze candidates. Please top up your credits and try again.');

            return null;
        }

        // The Livewire temp upload must still exist (mapping → confirm gap).
        $tempPath = $this->csvFile?->getRealPath();
        if (! $tempPath || ! is_file($tempPath)) {
            $this->failFile('The uploaded CSV is no longer available — please upload it again.');

            return null;
        }

        RateLimiter::hit($rateKey, 60);

        CsvMappingPreset::updateOrCreate(
            ['workspace_id' => $workspace->id, 'header_hash' => $this->headerHash],
            ['headers' => $this->headers, 'mapping' => $mapping, 'created_by' => Auth::id()],
        );

        // QUEUED INTAKE (CSV flavour): stage the file, create one "extracting"
        // batch and return instantly — the job streams rows, renders TXT CVs,
        // dedupes, auto-splits and starts AI processing.
        $groupId = (string) Str::uuid();
        $stagingDir = storage_path("app/private/intake/{$workspace->id}/{$groupId}");
        if (! is_dir($stagingDir)) {
            mkdir($stagingDir, 0750, true);
        }
        $staged = $stagingDir.'/import.csv';
        copy($tempPath, $staged);

        $batch = ResumeBatch::create([
            'workspace_id' => $workspace->id,
            'job_id' => $job->id,
            'created_by' => Auth::id(),
            'status' => ResumeBatch::STATUS_EXTRACTING,
            'upload_group_id' => $groupId,
            'intake' => ['done' => false, 'type' => 'csv', 'rows' => $this->rowCount, 'file' => $this->fileName],
        ]);

        ImportCsvJob::dispatch(
            $batch->id, $staged, $mapping,
            app(PlanLimits::class)->maxFilesPerBatch($workspace), $this->maxFileMb(),
            $this->hasHeaderRow,
        )->onQueue(config('hiremium.queue.name', 'cv'));

        try {
            if (method_exists($this->csvFile, 'delete')) {
                $this->csvFile->delete();
            }
        } catch (\Throwable) {
        }

        session()->flash('flash_toast', [
            'type' => 'info',
            'message' => number_format($this->rowCount).' candidate row(s) received — preparing your import. This page updates automatically.',
        ]);

        return $this->redirectRoute('app.batches.show', $batch, navigate: false);
    }

    /** Start over with a different file. */
    public function removeCsv(): void
    {
        $this->csvFile = null;
        $this->resetMappingState();
    }

    /** Non-blocking quality warning, recomputed live as the user edits. */
    public function getQualityWarningProperty(): ?string
    {
        return $this->stage === 'mapped'
            ? CandidateFields::qualityWarning(CandidateFields::sanitizeMapping($this->mapping, $this->headers))
            : null;
    }

    public function maxFileMb(): int
    {
        return app(PlanLimits::class)->maxFileMb($this->currentWorkspace());
    }

    private function failFile(string $message): void
    {
        $this->resetMappingState();
        $this->error = $message;
        $this->csvFile = null;
    }

    private function resetMappingState(): void
    {
        $this->stage = 'pick';
        $this->error = null;
        $this->notice = null;
        $this->source = '';
        $this->fileName = '';
        $this->rowCount = 0;
        $this->headers = [];
        $this->mapping = [];
        $this->headerHash = '';
        $this->hasHeaderRow = true;
    }

    public function render()
    {
        return view('livewire.uploads.csv-mapper', [
            'jobs' => Job::forCurrentWorkspace()->orderBy('title')->get(['id', 'title']),
            'fields' => CandidateFields::FIELDS,
            'maxFileMb' => $this->maxFileMb(),
        ]);
    }
}
