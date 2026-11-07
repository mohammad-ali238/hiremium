<?php

namespace App\Services\Uploads;

use App\Models\Job;
use App\Models\ResumeBatch;
use App\Models\ResumeFile;
use App\Models\Workspace;
use App\Services\Storage\CvStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Turns a set of uploaded files (and ZIP archives) into a resume batch:
 * expands ZIPs, validates each CV (type, size, corruption), skips duplicates
 * (SHA-256, per job — the same CV may go to other jobs), stores the rest
 * privately and records them.
 */
class ResumeUploadService
{
    public const ALLOWED_EXT = ['pdf', 'doc', 'docx', 'txt'];

    /**
     * Per-file reject entries kept on a batch. rejected_files is one JSON
     * column rendered on the batch page — a pathological upload (thousands of
     * duplicates, dozens of over-ceiling ZIPs) must not become a multi-MB row
     * or a thousands-long list. Beyond this, entries collapse into ONE summary
     * line. Mirrors ImportCsvJob::MAX_ROW_REJECTS for the CSV lane.
     */
    public const MAX_REJECT_ENTRIES = 200;

    public function __construct(private CvStorage $storage) {}

    /**
     * @param  UploadedFile[]  $uploaded
     * @param  array<int, array{name: string, reason: string}>  $preRejected  files already refused before upload (size/type), recorded for the user
     * @return array{batch: ResumeBatch, result: array{stored:int,duplicates:int,invalid:int,over_limit:int,rejected:array}}
     */
    /**
     * Turn uploaded items into resume batches. Items are {path, name} pairs on
     * local disk (the queued intake job stores the raw upload first), so this
     * can run long after the HTTP request has returned.
     *
     * @param  iterable<int, array{path: string, name: string}|array{name: string, contents: string, ext: string, source?: string}|UploadedFile>  $items  {path,name} = stored file (queued intake); {name,contents,ext} = in-memory entry (CSV-import rows — pass a Generator to stream); UploadedFile = direct/tests
     * @param  array<int, array{name: string, reason: string}>  $preRejected  refused before upload (size/type); recorded for the user
     * @param  ResumeBatch|null  $intake  the batch created at upload time — reused as the first batch
     * @return array{batch: ResumeBatch, batches: ResumeBatch[], result: array{stored:int,duplicates:int,invalid:int,over_limit:int,batches:int,rejected:array,capped:bool}}
     */
    public function process(Workspace $workspace, Job $job, iterable $items, int $userId, int $maxFiles, int $maxFileMb, array $preRejected = [], ?ResumeBatch $intake = null, ?string $uploadGroupId = null): array
    {
        $maxBytes = $maxFileMb * 1024 * 1024;
        $maxFiles = max(1, $maxFiles);
        $uploadGroupId ??= $intake?->upload_group_id ?: (string) Str::uuid();

        // Platform-level intake ceilings (config only — plans cannot raise them).
        $zipMaxFiles = max(1, (int) config('hiremium.uploads.zip_max_files', 200));
        $zipMaxTotalBytes = max(1, (int) config('hiremium.uploads.zip_max_total_mb', 300)) * 1024 * 1024;
        $uploadMaxCvs = max(1, (int) config('hiremium.uploads.max_cvs_per_upload', 1000));
        $capped = false;          // did any ceiling trim this upload?
        $acceptedTotal = 0;       // CVs accepted across ALL files/ZIPs in this upload

        // Every file we refuse, with a human reason — surfaced on the batch page.
        $rejected = array_values($preRejected);
        $invalid = 0;
        $duplicates = 0;
        $stored = 0;
        $seen = []; // intra-upload duplicates, across all ZIPs/files and batches

        // Dedupe is PER JOB: the same CV may be uploaded to other jobs (new match),
        // but not twice to the same one.
        $existsForJob = fn (string $hash): bool => ResumeFile::where('workspace_id', $workspace->id)
            ->where('file_hash', $hash)
            ->where(fn ($q) => $q->where('job_id', $job->id)
                ->orWhereHas('batch', fn ($b) => $b->where('job_id', $job->id)))
            ->exists();

        // STREAMING: each CV is validated, deduped and stored the moment it is
        // extracted — nothing is buffered, so memory stays flat no matter how
        // large the upload. Batches rotate on the fly: the plan cap applies PER
        // BATCH and a new batch opens whenever the current one is full.
        $batches = [];
        $current = null;
        $currentStored = 0;

        $openBatch = function () use (&$batches, &$current, &$currentStored, $workspace, $job, $userId, &$intake, $uploadGroupId): void {
            if ($current) {
                $current->update(['total_files' => $currentStored]);
            }
            if ($intake) {
                // First batch = the one created at upload time (the user is already on its page).
                $current = $intake;
                $intake = null;
                $current->update(['status' => ResumeBatch::STATUS_UPLOADED, 'upload_group_id' => $uploadGroupId]);
            } else {
                $current = ResumeBatch::create([
                    'workspace_id' => $workspace->id,
                    'job_id' => $job->id,
                    'created_by' => $userId,
                    'status' => ResumeBatch::STATUS_UPLOADED,
                    'upload_group_id' => $uploadGroupId,
                ]);
            }
            $batches[] = $current;
            $currentStored = 0;
        };
        $openBatch(); // always at least one batch — rejections need a home

        $sink = function (array $entry) use (&$seen, &$duplicates, &$rejected, &$stored, &$current, &$currentStored, &$openBatch, $existsForJob, $workspace, $job, $maxFiles, &$acceptedTotal, $uploadMaxCvs, &$capped): void {
            // Overall ceiling for one upload (all files + all ZIPs).
            if ($acceptedTotal >= $uploadMaxCvs) {
                $capped = true;
                $rejected[] = ['name' => $entry['name'], 'reason' => "Upload limit reached ({$uploadMaxCvs} CVs per upload) — not added; upload it separately"];

                return;
            }

            $hash = hash('sha256', $entry['contents']);

            if (isset($seen[$hash]) || $existsForJob($hash)) {
                $duplicates++;
                $rejected[] = ['name' => $entry['name'], 'reason' => 'Duplicate — this CV was already uploaded to this job'];

                return;
            }
            $seen[$hash] = true;

            if ($currentStored >= $maxFiles) {
                $openBatch();
            }

            $key = $this->storage->putContents($entry['contents'], $workspace->id, $current->id, $entry['ext']);

            ResumeFile::create([
                'batch_id' => $current->id,
                'workspace_id' => $workspace->id,
                'job_id' => $job->id,
                'original_name' => $entry['name'],
                'storage_key' => $key,
                'disk' => $this->storage->disk(),
                'mime' => $entry['mime'],
                'ext' => $entry['ext'],
                'size' => strlen($entry['contents']),
                'file_hash' => $hash,
                'source' => $entry['source'] ?? null,
                'status' => ResumeFile::STATUS_UPLOADED,
            ]);
            $currentStored++;
            $stored++;
            $acceptedTotal++;
        };

        foreach ($items as $item) {
            // In-memory entry (CSV-import rows): contents are already in hand —
            // validate and sink directly, no disk read.
            if (is_array($item) && array_key_exists('contents', $item)) {
                $this->addEntry(
                    (string) $item['name'],
                    (string) $item['contents'],
                    strtolower((string) ($item['ext'] ?? 'txt')),
                    $maxBytes,
                    $maxFileMb,
                    $sink,
                    $invalid,
                    $rejected,
                    isset($item['source']) ? (string) $item['source'] : null,
                );

                continue;
            }

            // Accept {path,name} arrays (queued intake) or UploadedFile instances (direct/tests).
            if ($item instanceof UploadedFile) {
                $item = ['path' => $item->getRealPath(), 'name' => $item->getClientOriginalName()];
            }
            $name = (string) ($item['name'] ?? basename((string) $item['path']));
            $path = (string) $item['path'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if ($ext === 'zip') {
                $this->expandZip($name, $path, $maxBytes, $maxFileMb, $sink, $invalid, $rejected, $zipMaxFiles, $zipMaxTotalBytes, $capped);
            } else {
                $this->addEntry(
                    $name,
                    @file_get_contents($path),
                    $ext,
                    $maxBytes,
                    $maxFileMb,
                    $sink,
                    $invalid,
                    $rejected
                );
            }
        }

        $current->update(['total_files' => $currentStored]);

        // All rejection reasons live on the FIRST batch — that is where the
        // uploader lands after saving. Bounded: everything past the cap becomes
        // one summary line (counters above already counted every occurrence).
        if (count($rejected) > self::MAX_REJECT_ENTRIES) {
            $trimmed = count($rejected) - self::MAX_REJECT_ENTRIES;
            $rejected = array_slice($rejected, 0, self::MAX_REJECT_ENTRIES);
            $rejected[] = [
                'name' => 'More skipped files',
                'reason' => "{$trimmed} more file(s) were skipped (only the first ".self::MAX_REJECT_ENTRIES.' are listed)',
            ];
        }
        $batches[0]->update(['rejected_files' => $rejected ?: null]);

        return [
            'batch' => $batches[0],
            'batches' => $batches,
            'result' => [
                'stored' => $stored,
                'duplicates' => $duplicates,
                'invalid' => $invalid,
                'over_limit' => 0, // auto-split: nothing is dropped for exceeding the cap
                'batches' => count($batches),
                'rejected' => $rejected,
                'capped' => $capped,
            ],
        ];
    }

    private function expandZip(string $zipName, string $path, int $maxBytes, int $maxFileMb, callable $sink, int &$invalid, array &$rejected, int $zipMaxFiles = 200, int $zipMaxTotalBytes = 314572800, bool &$capped = false): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $invalid++;
            $rejected[] = ['name' => $zipName, 'reason' => 'ZIP archive could not be opened (corrupt or not a ZIP)'];

            return;
        }

        $taken = 0;
        $inflated = 0;
        $skippedForCount = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || str_ends_with($name, '/') || str_starts_with($name, '__MACOSX')) {
                continue;
            }

            $base = basename($name);
            if ($base === '' || str_starts_with($base, '.')) {
                continue;
            }

            // Per-ZIP file-count ceiling: stop walking the archive entirely.
            if ($taken >= $zipMaxFiles) {
                $skippedForCount++;

                continue;
            }

            // Per-ZIP uncompressed-size ceiling (zip-bomb guard): use the declared
            // size from the central directory so we never inflate past the cap.
            $stat = $zip->statIndex($i);
            $declared = (int) ($stat['size'] ?? 0);
            if ($declared > $maxBytes) {
                $invalid++;
                $rejected[] = ['name' => $base, 'reason' => sprintf('File too large (%.1f MB) — limit is %d MB per file', $declared / 1048576, $maxFileMb)];

                continue;
            }
            if ($inflated + $declared > $zipMaxTotalBytes) {
                $capped = true;
                $rejected[] = ['name' => $base, 'reason' => "ZIP \"{$zipName}\" exceeds the total size allowed per archive — remaining files were not added"];
                // Everything after this would also exceed; stop.
                for ($j = $i + 1; $j < $zip->numFiles; $j++) {
                    $n = $zip->getNameIndex($j);
                    if ($n !== false && ! str_ends_with($n, '/') && ! str_starts_with($n, '__MACOSX') && basename($n) !== '' && ! str_starts_with(basename($n), '.')) {
                        $skippedForCount++;
                    }
                }
                break;
            }
            $inflated += $declared;
            $taken++;

            $this->addEntry(
                $base,
                $zip->getFromIndex($i),
                strtolower(pathinfo($base, PATHINFO_EXTENSION)),
                $maxBytes,
                $maxFileMb,
                $sink,
                $invalid,
                $rejected
            );
        }

        if ($skippedForCount > 0) {
            $capped = true;
            $rejected[] = ['name' => $zipName, 'reason' => "ZIP contains more files than allowed per archive — {$skippedForCount} file(s) were not added"];
        }

        $zip->close();
    }

    private function addEntry(string $name, string|false|null $contents, string $ext, int $maxBytes, int $maxFileMb, callable $sink, int &$invalid, array &$rejected, ?string $source = null): void
    {
        if ($contents === false || $contents === null || $contents === '') {
            $invalid++;
            $rejected[] = ['name' => $name, 'reason' => 'File is empty or could not be read'];

            return;
        }
        if (! in_array($ext, self::ALLOWED_EXT, true)) {
            $invalid++;
            $rejected[] = ['name' => $name, 'reason' => 'File type not allowed — use PDF, DOCX, DOC or TXT'];

            return;
        }
        if (strlen($contents) > $maxBytes) {
            $invalid++;
            $rejected[] = ['name' => $name, 'reason' => sprintf('File too large (%.1f MB) — limit is %d MB per file', strlen($contents) / 1048576, $maxFileMb)];

            return;
        }

        $sink(['name' => $name, 'contents' => $contents, 'ext' => $ext, 'mime' => null, 'source' => $source]);
    }
}
