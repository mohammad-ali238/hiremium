<?php

namespace App\Jobs;

use App\Models\AiSetting;
use App\Models\ProviderBatch;
use App\Models\ResumeBatch;
use App\Models\ResumeFile;
use App\Services\Ai\Batch\BatchItem;
use App\Services\Ai\Batch\BatchScoringManager;
use App\Services\Ai\Batch\CombinedMatchPrompt;
use App\Services\ResumeParsing\FileProcessor;
use App\Support\Hiremium;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Submits the overflow CVs of a resume batch to the provider's async Batch API
 * as one combined parse+score request per CV. Any CV that can't go through the
 * batch (non-text file, extraction failure) falls back to the live lane, and if
 * the whole submission fails the entire set falls back — so no CV is dropped.
 */
class SubmitProviderBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    /** @param  int[]  $fileIds */
    public function __construct(public int $resumeBatchId, public array $fileIds) {}

    public function handle(FileProcessor $fileProcessor, BatchScoringManager $manager): void
    {
        $batch = ResumeBatch::find($this->resumeBatchId);
        if (! $batch) {
            return;
        }

        $settings = AiSetting::current();
        $job = $batch->jobForAnalysis(); // frozen snapshot, not the live job

        $files = ResumeFile::whereIn('id', $this->fileIds)
            ->where('batch_id', $batch->id)
            ->where('status', ResumeFile::STATUS_UPLOADED)
            ->get();

        // If the config is no longer batch-eligible or the job is gone, run
        // everything on the live lane instead.
        if (! $job || ! $manager->isEligible($settings)) {
            $files->each(fn (ResumeFile $f) => $this->live($f));

            return;
        }

        $items = [];
        $batchFileIds = [];
        $system = CombinedMatchPrompt::system($settings);
        $jobText = $job->toMatchingText();

        foreach ($files as $file) {
            $text = $this->extractText($fileProcessor, $file);
            if ($text === null) {
                // Non-text (needs vision) or unreadable → let the live lane handle it.
                Log::info('Batch lane: file has no extractable text (scanned/image CV) — processing on live lane instead', [
                    'resume_batch_id' => $batch->id, 'resume_file_id' => $file->id, 'name' => $file->original_name,
                ]);
                $this->live($file);

                continue;
            }

            $items[] = new BatchItem(
                customId: 'rf-'.$file->id,
                systemPrompt: $system,
                userMessage: CombinedMatchPrompt::user($jobText, $text),
                model: $settings->default_model,
                temperature: (float) $settings->temperature,
                maxTokens: (int) $settings->max_tokens,
            );
            $batchFileIds[] = $file->id;
        }

        if (empty($items)) {
            return;
        }

        try {
            $submission = $manager->driver($settings->default_provider)->submit($items, $settings);
        } catch (Throwable $e) {
            Log::warning('Provider batch submit failed; falling back to live lane', [
                'resume_batch_id' => $batch->id, 'error' => $e->getMessage(),
            ]);
            ResumeFile::whereIn('id', $batchFileIds)->get()->each(fn (ResumeFile $f) => $this->live($f));

            return;
        }

        try {
            ProviderBatch::create([
                'resume_batch_id' => $batch->id,
                'workspace_id' => $batch->workspace_id,
                'provider' => $settings->default_provider,
                'status' => ProviderBatch::STATUS_SUBMITTED,
                'provider_batch_id' => $submission['provider_batch_id'],
                'input_file_id' => $submission['input_file_id'] ?? null,
                'request_count' => count($batchFileIds),
                'cost_per_item' => Hiremium::creditCostPerMatch(),
                'meta' => ['file_ids' => $batchFileIds],
            ]);
        } catch (Throwable $e) {
            // Typically a missing provider_batches table (migration not run).
            // Without the record the poller can never ingest the results, so
            // process the files on the live lane instead of losing them.
            Log::error('Provider batch submitted but could not be recorded — falling back to live lane. Did you run `php artisan migrate`?', [
                'resume_batch_id' => $batch->id, 'provider_batch_id' => $submission['provider_batch_id'], 'error' => $e->getMessage(),
            ]);
            ResumeFile::whereIn('id', $batchFileIds)->get()->each(fn (ResumeFile $f) => $this->live($f));

            return;
        }

        ResumeFile::whereIn('id', $batchFileIds)->update(['status' => ResumeFile::STATUS_SUBMITTED]);

        Log::info('Provider batch submitted', [
            'resume_batch_id' => $batch->id,
            'provider' => $settings->default_provider,
            'provider_batch_id' => $submission['provider_batch_id'],
            'items' => count($batchFileIds),
        ]);
    }

    /** Extract plain text for the batch prompt; null if the CV needs vision or can't be read. */
    private function extractText(FileProcessor $fileProcessor, ResumeFile $file): ?string
    {
        try {
            $tmp = $this->toTempUploadedFile($file);
            $processed = $fileProcessor->process($tmp);
            @unlink($tmp->getRealPath());

            if (! $processed->isText()) {
                return null;
            }
            $text = trim((string) $processed->content);

            return $text !== '' ? $text : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function toTempUploadedFile(ResumeFile $file): UploadedFile
    {
        $contents = Storage::disk($file->disk)->get($file->storage_key);
        $tmp = tempnam(sys_get_temp_dir(), 'cv_');
        file_put_contents($tmp, (string) $contents);

        return new UploadedFile($tmp, $file->original_name, $file->mime ?: null, null, true);
    }

    /** Fall a single file back to the immediate live pipeline. */
    private function live(ResumeFile $file): void
    {
        ProcessResumeFileJob::dispatch($file->id)->onQueue(config('hiremium.queue.name', 'cv'));
    }
}
