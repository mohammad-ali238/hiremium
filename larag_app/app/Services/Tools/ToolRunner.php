<?php

namespace App\Services\Tools;

use App\Exceptions\Api\FileProcessingException;
use App\Exceptions\Api\UnsupportedFileTypeException;
use App\Models\ToolRun;
use App\Models\ToolRunEvent;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\ResumeParsing\Exceptions\LowParseConfidenceException;
use App\Services\Tools\Contracts\StageResult;
use App\Services\Tools\Contracts\ToolStage;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Drives a run through its stages, one at a time.
 *
 * Resumability is the whole point: a stage that has already succeeded is never
 * run again, because "done" is read from the event log rather than a counter.
 * So the visitor's tab, the fallback worker and the sweeper can all call
 * advance() without anyone paying twice for the same AI call, and a retry after
 * a failure costs only the stage that failed.
 */
class ToolRunner
{
    /** Give up on a stage after this many attempts, however it is triggered. */
    public const MAX_ATTEMPTS_PER_STAGE = 3;

    /** Longer than the slowest stage: a crashed holder must not deadlock a run. */
    private const LOCK_SECONDS = 240;

    /** Failures where trying the same thing again cannot possibly help. */
    private const PERMANENT_CODES = ['unreadable_file', 'missing_file', 'low_confidence'];

    public function __construct(private readonly ToolRegistry $registry) {}

    /* ------------------------------------------------------------------ */
    /* The plan of work */
    /* ------------------------------------------------------------------ */

    /** @return list<ToolStage> */
    public function stages(ToolRun $run): array
    {
        // find(), not findEnabled(): switching a tool off stops new submissions,
        // it does not strand the people already holding a result link.
        $tool = $this->registry->find($run->tool);

        if ($tool === null) {
            throw new RuntimeException("Unknown tool: {$run->tool}");
        }

        return array_map(fn (string $class): ToolStage => app($class), $tool->stages());
    }

    /** Stage keys that have already succeeded. @return list<string> */
    public function completed(ToolRun $run): array
    {
        return $run->events()
            ->where('status', ToolRunEvent::STATUS_SUCCESS)
            ->pluck('stage')
            ->unique()
            ->values()
            ->all();
    }

    /** The first stage still owing work, or null when the run is done. */
    public function nextStage(ToolRun $run): ?ToolStage
    {
        $done = $this->completed($run);

        foreach ($this->stages($run) as $stage) {
            if (! in_array($stage->key(), $done, true)) {
                return $stage;
            }
        }

        return null;
    }

    /** Weighted percentage — the long AI stages move the bar further. */
    public function progress(ToolRun $run): int
    {
        $stages = $this->stages($run);
        $total = array_sum(array_map(fn (ToolStage $s): int => $s->weight(), $stages));

        if ($total < 1) {
            return 0;
        }

        $done = $this->completed($run);
        $earned = 0;

        foreach ($stages as $stage) {
            if (in_array($stage->key(), $done, true)) {
                $earned += $stage->weight();
            }
        }

        return (int) round($earned / $total * 100);
    }

    /**
     * The step list the progress screen renders.
     *
     * @return list<array{key: string, label: string, icon: string, state: string}>
     */
    public function steps(ToolRun $run): array
    {
        $done = $this->completed($run);
        $current = $this->nextStage($run)?->key();
        $failed = $run->status === ToolRun::STATUS_FAILED ? $run->failed_stage : null;

        return array_map(fn (ToolStage $stage): array => [
            'key' => $stage->key(),
            'label' => $stage->label(),
            'icon' => $stage->iconPath(),
            'state' => match (true) {
                in_array($stage->key(), $done, true) => 'done',
                $stage->key() === $failed => 'failed',
                $stage->key() === $current => 'running',
                default => 'waiting',
            },
        ], $this->stages($run));
    }

    /* ------------------------------------------------------------------ */
    /* Running */
    /* ------------------------------------------------------------------ */

    /**
     * Run the next stage. Returns true when a stage actually ran, so a caller
     * can loop; false means finished, failed, or someone else holds the lock.
     */
    public function advance(ToolRun $run): bool
    {
        if ($run->status === ToolRun::STATUS_COMPLETED || $run->status === ToolRun::STATUS_EXPIRED) {
            return false;
        }

        $stage = $this->nextStage($run);

        if ($stage === null) {
            $this->finish($run);

            return false;
        }

        // One runner at a time. The visitor's tab, the delayed fallback job and
        // the sweeper can all arrive at once; whoever loses simply steps back.
        $lock = Cache::lock('tool-run:'.$run->id, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return false;
        }

        try {
            // Someone may have finished this stage while we waited for the lock.
            $run->refresh();
            $stage = $this->nextStage($run);

            if ($stage === null) {
                $this->finish($run);

                return false;
            }

            return $this->runStage($run, $stage);
        } finally {
            $lock->release();
        }
    }

    private function runStage(ToolRun $run, ToolStage $stage): bool
    {
        $attempt = $run->events()->where('stage', $stage->key())->count() + 1;

        if ($attempt > self::MAX_ATTEMPTS_PER_STAGE) {
            $this->fail($run, $stage, 'attempts_exhausted', 'Stage failed too many times.');

            return false;
        }

        $run->forceFill([
            'status' => ToolRun::STATUS_PROCESSING,
            'stage' => $stage->key(),
            'processing_started_at' => $run->processing_started_at ?? now(),
            'attempts' => $attempt,
        ])->save();

        $startedAt = microtime(true);

        try {
            $result = $stage->run($run);
        } catch (Throwable $e) {
            $this->recordEvent($run, $stage, ToolRunEvent::STATUS_FAILED, $attempt, null, $this->elapsed($startedAt), $e);
            $this->fail($run, $stage, $this->failureCode($e), $e->getMessage());

            return false;
        }

        $this->recordEvent($run, $stage, ToolRunEvent::STATUS_SUCCESS, $attempt, $result, $this->elapsed($startedAt));

        $run->forceFill([
            'progress' => $this->progress($run->refresh()),
            'duration_ms' => (int) $run->duration_ms + $this->elapsed($startedAt),
            'ai_calls' => (int) $run->ai_calls + ($result->isAiCall ? 1 : 0),
            'ai_tokens_total' => (int) $run->ai_tokens_total + (int) $result->tokensTotal,
            'failed_stage' => null,
            'failure_code' => null,
            'failure_message' => null,
        ])->save();

        if ($this->nextStage($run) === null) {
            $this->finish($run);
        }

        return true;
    }

    /**
     * First open: the link becomes permanent and the work begins.
     *
     * Called when a person presses the button on the result page, never on a
     * bare page load — see Livewire\Tools\Result::start().
     */
    public function open(ToolRun $run, ?string $ip = null): void
    {
        if ($run->opened_at === null) {
            $run->forceFill([
                'opened_at' => now(),
                'first_opened_ip' => $ip,
                // Opened once = never expires again.
                'expires_at' => null,
            ])->save();
        }

        $this->recordView($run);
    }

    /**
     * Someone looked at an already-opened link. Counts the visit and nothing
     * else: no expiry is cleared and no work is started, so a mail scanner
     * fetching a shared link cannot change what happens to the run.
     */
    public function recordView(ToolRun $run): void
    {
        $run->forceFill([
            'view_count' => (int) $run->view_count + 1,
            'last_viewed_at' => now(),
        ])->save();
    }

    /** Can the visitor usefully press Retry? */
    public function isRetryable(ToolRun $run): bool
    {
        if ($run->status !== ToolRun::STATUS_FAILED) {
            return false;
        }

        if (in_array((string) $run->failure_code, self::PERMANENT_CODES, true)) {
            return false;
        }

        $attempts = $run->events()->where('stage', (string) $run->failed_stage)->count();

        return $attempts < self::MAX_ATTEMPTS_PER_STAGE;
    }

    /** Put a failed run back to work — from the failed stage, not the start. */
    public function retry(ToolRun $run): bool
    {
        if (! $this->isRetryable($run)) {
            return false;
        }

        $run->forceFill([
            'status' => ToolRun::STATUS_PROCESSING,
            // The clock restarts with the attempt. Left at the first start, a
            // run retried an hour or a day later is already past the sweeper's
            // abandon window, so the safety net would mark it "timed out" on
            // its very first pass instead of finishing it in the background.
            'processing_started_at' => now(),
            'failure_code' => null,
            'failure_message' => null,
        ])->save();

        return true;
    }

    public function finish(ToolRun $run): void
    {
        if ($run->status === ToolRun::STATUS_COMPLETED) {
            return;
        }

        $run->forceFill([
            'status' => ToolRun::STATUS_COMPLETED,
            'progress' => 100,
            'completed_at' => now(),
            'failed_stage' => null,
            'failure_code' => null,
            'failure_message' => null,
        ])->save();
    }

    private function fail(ToolRun $run, ToolStage $stage, string $code, string $message): void
    {
        $run->forceFill([
            'status' => ToolRun::STATUS_FAILED,
            'failed_stage' => $stage->key(),
            'failure_code' => $code,
            'failure_message' => mb_substr($message, 0, 500),
        ])->save();
    }

    /** What kind of failure this was — decides whether Retry is even offered. */
    private function failureCode(Throwable $e): string
    {
        return match (true) {
            $e instanceof LowParseConfidenceException => 'low_confidence',
            $e instanceof FileProcessingException, $e instanceof UnsupportedFileTypeException => 'unreadable_file',
            $e instanceof AiProviderException => 'ai_error',
            $e instanceof RuntimeException && str_contains($e->getMessage(), 'no longer available') => 'missing_file',
            $e instanceof RuntimeException && str_contains($e->getMessage(), 'is missing') => 'missing_file',
            default => 'error',
        };
    }

    private function recordEvent(
        ToolRun $run,
        ToolStage $stage,
        string $status,
        int $attempt,
        ?StageResult $result,
        int $durationMs,
        ?Throwable $error = null,
    ): void {
        ToolRunEvent::create([
            'aitools_run_id' => $run->id,
            'tool' => $run->tool,
            'stage' => $stage->key(),
            'attempt' => $attempt,
            'status' => $status,
            'provider' => $result?->provider,
            'model' => $result?->model,
            'provider_inherited' => $result?->providerInherited ?? true,
            'tokens_prompt' => $result?->tokensPrompt,
            'tokens_completion' => $result?->tokensCompletion,
            'tokens_total' => $result?->tokensTotal,
            'duration_ms' => $durationMs,
            'error_code' => $error === null ? null : $this->failureCode($error),
            'error_message' => $error === null ? null : mb_substr($error->getMessage(), 0, 500),
            'payload' => $result?->payload ?: null,
            'created_at' => now(),
        ]);
    }

    private function elapsed(float $startedAt): int
    {
        return (int) ((microtime(true) - $startedAt) * 1000);
    }
}
