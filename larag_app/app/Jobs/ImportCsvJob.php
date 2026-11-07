<?php

namespace App\Jobs;

use App\Models\Job;
use App\Models\ResumeBatch;
use App\Models\Workspace;
use App\Services\Activity\ActivityLogger;
use App\Services\CsvImport\CsvRowReader;
use App\Services\CsvImport\RowCvRenderer;
use App\Services\Pipeline\BatchProcessor;
use App\Services\Uploads\ResumeUploadService;
use Generator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued CSV import: the confirm request only stages the CSV and creates one
 * "extracting" batch, then returns instantly. This job streams the rows,
 * renders each valid one into a deterministic TXT CV and pushes them through
 * the SAME intake sink as file uploads (hashing, per-job dedupe, plan-cap
 * auto-split, ceilings), then starts the normal AI pipeline — parse + match —
 * for every batch produced.
 */
class ImportCsvJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    /**
     * Per-row reject entries kept on the batch. rejected_files is one JSON
     * column rendered on the batch page — a junk CSV with 100k bad rows must
     * not become a multi-MB row (which would exceed DB packet limits and kill
     * the import). Beyond this, skipped rows collapse into one summary line.
     */
    public const MAX_ROW_REJECTS = 200;

    /** @param array<string, ?string> $mapping field => column label */
    public function __construct(
        public int $batchId,
        public string $csvPath,
        public array $mapping,
        public int $maxFiles,
        public int $maxFileMb,
        public bool $hasHeaderRow = true,
    ) {}

    public function handle(CsvRowReader $reader, ResumeUploadService $service, BatchProcessor $processor): void
    {
        // Atomic claim — a duplicate queue delivery sees zero rows affected and
        // exits without importing anything twice (same pattern as ExtractUploadJob).
        $claimed = ResumeBatch::whereKey($this->batchId)
            ->where('status', ResumeBatch::STATUS_EXTRACTING)
            ->update(['status' => ResumeBatch::STATUS_EXTRACTING_CLAIMED]);

        $batch = $claimed ? ResumeBatch::find($this->batchId) : null;
        if (! $batch) {
            return; // another worker owns it, or it was deleted / already imported
        }

        $workspace = Workspace::find($batch->workspace_id);
        $job = Job::find($batch->job_id);
        if (! $workspace || ! $job) {
            $batch->update(['status' => ResumeBatch::STATUS_FAILED, 'intake' => ['error' => 'Job or workspace missing']]);
            $this->cleanupRaw();

            return;
        }

        // Headerless CSVs use positional labels (Col #1..N) and their first row
        // is a data row — both must match what the mapping UI showed.
        $firstRow = $reader->headers($this->csvPath);
        $labels = $this->hasHeaderRow
            ? CsvRowReader::uniqueLabels($firstRow)
            : CsvRowReader::positionalLabels(count($firstRow));
        $renderer = new RowCvRenderer($this->mapping, $labels);
        // Clamped to the overall upload ceiling: if it were higher, the sink's
        // own limit would kick in with one reject line PER row instead of the
        // single summary produced here.
        $maxRows = min(
            max(1, (int) config('hiremium.uploads.csv_max_rows', 1000)),
            max(1, (int) config('hiremium.uploads.max_cvs_per_upload', 1000)),
        );

        // Rejections decided while the generator streams (invalid rows, the row
        // ceiling). They can't ride process()'s $preRejected — that is written
        // before iteration — so they are merged into rejected_files afterwards.
        $rowRejects = [];
        $overflow = 0;
        $invalidOverflow = 0;

        try {
            ['batches' => $batches, 'result' => $r] = $service->process(
                $workspace, $job,
                $this->rowEntries($reader, $renderer, $maxRows, $rowRejects, $overflow, $invalidOverflow),
                (int) $batch->created_by, $this->maxFiles, $this->maxFileMb,
                [], $batch, $batch->upload_group_id
            );
        } catch (Throwable $e) {
            $batch->update(['status' => ResumeBatch::STATUS_FAILED, 'intake' => ['error' => 'CSV import failed: '.mb_substr($e->getMessage(), 0, 200)]]);
            $this->cleanupRaw();

            throw $e;
        }

        $this->cleanupRaw();

        if ($invalidOverflow > 0) {
            $rowRejects[] = [
                'name' => 'More skipped rows',
                'reason' => "{$invalidOverflow} more row(s) were skipped for missing name or contact info (only the first ".self::MAX_ROW_REJECTS.' are listed)',
            ];
        }
        if ($overflow > 0) {
            $rowRejects[] = [
                'name' => 'Remaining rows',
                'reason' => "{$overflow} more row(s) over the limit of {$maxRows} per CSV — import them in a separate CSV",
            ];
        }
        if ($rowRejects) {
            $first = $batches[0]->refresh();
            $first->update(['rejected_files' => array_merge((array) ($first->rejected_files ?? []), $rowRejects)]);
        }

        $batch->refresh()->update(['intake' => [
            'done' => true,
            'type' => 'csv',
            'stored' => $r['stored'],
            'batches' => $r['batches'],
            'duplicates' => $r['duplicates'],
            'invalid' => $r['invalid'] + count($rowRejects) + $invalidOverflow,
            'capped' => (bool) ($r['capped'] ?? false) || $overflow > 0,
            'batch_ids' => array_map(fn ($b) => $b->id, $batches),
        ]]);

        ActivityLogger::log('batch.uploaded', $batch, "Imported {$r['stored']} candidate(s) from CSV to \"{$job->title}\"".($r['batches'] > 1 ? " across {$r['batches']} batches" : ''));

        foreach ($batches as $b) {
            if ($b->total_files > 0) {
                $processor->start($b);
            }
        }
    }

    /**
     * Stream the CSV's data rows as in-memory intake entries. Valid rows become
     * deterministic TXT CVs; invalid ones and rows beyond the platform ceiling
     * are recorded by row number instead.
     *
     * @param  array<int, array{name: string, reason: string}>  $rowRejects
     * @return Generator<int, array{name: string, contents: string, ext: string, source: string}>
     */
    private function rowEntries(CsvRowReader $reader, RowCvRenderer $renderer, int $maxRows, array &$rowRejects, int &$overflow, int &$invalidOverflow): Generator
    {
        $accepted = 0;
        foreach ($reader->rows($this->csvPath, skipFirst: $this->hasHeaderRow) as [$n, $cells]) {
            if (! $renderer->isValidRow($cells)) {
                if (count($rowRejects) < self::MAX_ROW_REJECTS) {
                    $name = $renderer->value($cells, 'name');
                    $rowRejects[] = [
                        'name' => "Row {$n}".($name !== '' ? " — {$name}" : ''),
                        'reason' => 'Missing name or contact info (email or phone)',
                    ];
                } else {
                    $invalidOverflow++;
                }

                continue;
            }

            if ($accepted >= $maxRows) {
                $overflow++;

                continue;
            }
            $accepted++;

            yield [
                'name' => $renderer->fileName($cells, $n),
                'contents' => $renderer->render($cells),
                'ext' => 'txt',
                'source' => 'csv',
            ];
        }
    }

    public function failed(?Throwable $e = null): void
    {
        $batch = ResumeBatch::find($this->batchId);
        if ($batch && in_array($batch->status, ResumeBatch::EXTRACTING_STATUSES, true)) {
            $batch->update(['status' => ResumeBatch::STATUS_FAILED, 'intake' => ['error' => 'CSV import was interrupted'.($e ? ': '.mb_substr($e->getMessage(), 0, 160) : '')]]);
        }
        $this->cleanupRaw();
    }

    /** Remove the staged CSV (it lives under the private intake dir). */
    private function cleanupRaw(): void
    {
        try {
            if (is_file($this->csvPath)) {
                @unlink($this->csvPath);
            }
            @rmdir(dirname($this->csvPath));
        } catch (Throwable) {
            // best-effort
        }
    }
}
