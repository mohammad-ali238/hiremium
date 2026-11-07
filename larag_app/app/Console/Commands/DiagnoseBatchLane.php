<?php

namespace App\Console\Commands;

use App\Models\AiSetting;
use App\Models\ProviderBatch;
use App\Models\ResumeBatch;
use App\Services\Ai\Batch\BatchScoringManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prints every input that decides whether an upload's overflow goes to the
 * provider Batch API or falls back to the live/throttled queue — plus recent
 * batch state and any fallback warnings from the log. Run this whenever "no
 * batch request is generated" to see exactly which condition failed.
 */
class DiagnoseBatchLane extends Command
{
    protected $signature = 'hiremium:batch-diagnose';

    protected $description = 'Explain why uploads do or do not use the provider Batch API lane.';

    public function handle(BatchScoringManager $manager): int
    {
        $settings = AiSetting::current();

        $this->line('=== Config (effective values, after .env overrides) ===');
        $this->line('batch_threshold        : '.config('hiremium.ai.batch_threshold').'  (files beyond this go to the batch lane)');
        $this->line('batch_lane_per_minute  : '.config('hiremium.ai.batch_lane_per_minute'));
        $this->line('queue connection       : '.config('queue.default').' (must be "database", not "sync")');
        $this->line('env HIREMIUM_AI_BATCH_THRESHOLD : '.(env('HIREMIUM_AI_BATCH_THRESHOLD') ?? '(not set — config default applies)'));

        $this->newLine();
        $this->line('=== AI Settings (admin DB) ===');
        $this->line('match provider  (default_provider)    : '.$settings->default_provider.' / '.$settings->default_model);
        $this->line('parse provider  (parsing_ai_provider) : '.$settings->parsing_ai_provider);
        $this->line('openai_api_key set : '.($settings->openai_api_key ? 'yes' : 'NO'));
        $this->line('gemini_api_key set : '.($settings->gemini_api_key ? 'yes' : 'NO'));

        $this->newLine();
        $this->line('=== Batch-lane eligibility ===');
        $sameProvider = $settings->parsing_ai_provider === $settings->default_provider;
        $supported = $manager->supports($settings->default_provider);
        $eligible = $manager->isEligible($settings);

        $this->line('parse == match provider : '.($sameProvider ? 'OK' : 'FAIL — set both to the same provider in Admin → AI Settings'));
        $this->line('provider has a driver   : '.($supported ? 'OK' : 'FAIL — no batch driver for "'.$settings->default_provider.'"'));
        $this->line('provider key configured : '.($this->keyOk($settings) ? 'OK' : 'FAIL — add the API key in Admin → AI Settings'));
        $this->newLine();
        $this->line($eligible
            ? '>>> ELIGIBLE: uploads beyond the threshold WILL submit a provider batch.'
            : '>>> NOT ELIGIBLE: the overflow falls back to the throttled live queue (this is the "all files via queue" symptom).');

        $this->newLine();
        $this->line('=== Infrastructure ===');
        $hasTable = Schema::hasTable('provider_batches');
        $this->line('provider_batches table : '.($hasTable ? 'OK' : 'MISSING — run `php artisan migrate`'));
        $this->line('pending queue jobs     : '.DB::table('jobs')->count());
        $failed = DB::table('failed_jobs')->orderByDesc('id')->limit(3)->get();
        $this->line('failed queue jobs      : '.DB::table('failed_jobs')->count());
        foreach ($failed as $f) {
            $this->warn('  '.$f->failed_at.' '.mb_substr(preg_replace('/\s+/', ' ', $f->exception), 0, 160));
        }

        $this->newLine();
        $this->line('=== Recent batches ===');
        foreach (ResumeBatch::withoutGlobalScopes()->latest('id')->limit(3)->get() as $batch) {
            $statuses = $batch->files()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')->toArray();
            $this->line(sprintf(
                'ResumeBatch #%d  status=%s  total=%d  files={%s}',
                $batch->id, $batch->status, $batch->total_files,
                collect($statuses)->map(fn ($c, $s) => "$s:$c")->implode(', ')
            ));
        }
        $this->line('ProviderBatch rows: '.ProviderBatch::count());
        foreach (ProviderBatch::latest('id')->limit(3)->get() as $pb) {
            $this->line(sprintf(
                '  PB#%d %s %s items=%d done=%d failed=%d id=%s err=%s',
                $pb->id, $pb->provider, $pb->status, $pb->request_count,
                $pb->completed_count, $pb->failed_count, $pb->provider_batch_id, $pb->error ?: '-'
            ));
        }

        $this->newLine();
        $this->line('=== Batch-lane log trail (laravel.log, most recent last) ===');
        $log = storage_path('logs/laravel.log');
        $found = false;
        if (is_file($log)) {
            foreach (array_slice(file($log, FILE_IGNORE_NEW_LINES) ?: [], -3000) as $line) {
                if (str_contains($line, 'Hybrid dispatch:')
                    || str_contains($line, 'Batch lane:')
                    || str_contains($line, 'Provider batch submit')
                    || str_contains($line, 'Provider batch submitted')) {
                    $this->line(mb_substr($line, 0, 220));
                    $found = true;
                }
            }
        }
        if (! $found) {
            $this->line('(no dispatch lines yet — upload a batch AFTER installing this update, then the log shows which lane each upload took)');
        }

        return self::SUCCESS;
    }

    private function keyOk(AiSetting $settings): bool
    {
        return match ($settings->default_provider) {
            AiSetting::PROVIDER_OPENAI => ! empty($settings->openai_api_key),
            AiSetting::PROVIDER_GEMINI => ! empty($settings->gemini_api_key),
            default => false,
        };
    }
}
