<?php

namespace App\Services\Tools;

use App\Mail\ToolResultReady;
use App\Models\ToolRun;
use App\Models\ToolRunFile;
use App\Services\Tools\Contracts\ToolDefinition;
use App\Support\ToolSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns an accepted submission into a stored run: the file on the private disk,
 * the run row, its day's fingerprints, and the emailed link.
 *
 * Nothing is processed here — the run is parked as AWAITING_OPEN and the AI only
 * starts when the emailed link is opened (phase 2), which is what makes opening
 * the link proof that the address is real.
 */
class ToolSubmitter
{
    public function __construct(
        private readonly ToolStorage $storage,
        private readonly ToolQuota $quota,
    ) {}

    /**
     * @param  array<string, mixed>  $normalized  Cleaned field values (JobPayload::normalize).
     */
    public function submit(ToolContext $context, array $normalized, ?UploadedFile $file, bool $marketingOptIn = false): ToolRun
    {
        $tool = $context->tool;
        $storedKey = $file !== null ? $this->storage->put($file, $tool->slug()) : null;

        try {
            return DB::transaction(function () use ($context, $tool, $normalized, $file, $storedKey, $marketingOptIn): ToolRun {
                $run = ToolRun::create(array_merge([
                    'uuid' => (string) Str::uuid(),
                    'tool' => $tool->slug(),
                    'status' => ToolRun::STATUS_AWAITING_OPEN,
                    'stage' => null,
                    'progress' => 0,

                    'email' => $context->email,
                    'email_canonical' => $context->emailCanonical,
                    'quota_email' => $context->quotaEmail,
                    'consent_at' => now(),
                    'marketing_opt_in' => $marketingOptIn,

                    'input_data' => $normalized,
                    'input_hash' => $context->inputHash,
                    'file_hash' => $context->fileHash,
                    'combo_hash' => $context->comboHash,

                    // Unopened links die; opening one clears this for good.
                    'expires_at' => now()->addHours(ToolSettings::expiryHours()),
                    'purge_after' => now()->addDays(ToolSettings::retentionDays()),
                ], $context->signals()));

                if ($file !== null && $storedKey !== null) {
                    ToolRunFile::create([
                        'aitools_run_id' => $run->id,
                        'role' => (string) array_key_first($tool->files()),
                        'disk' => $this->storage->disk(),
                        'path' => $storedKey,
                        'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                        'mime' => $file->getMimeType(),
                        'size_bytes' => (int) $file->getSize(),
                        'sha256' => (string) $context->fileHash,
                    ]);
                }

                $this->quota->recordHashes($run, $context);

                return $run;
            });
        } catch (Throwable $e) {
            // Never leave an orphan file on the disk when the row never landed.
            if ($storedKey !== null) {
                $this->storage->delete($storedKey);
            }

            throw $e;
        }
    }

    /**
     * Send (or re-send) the result link. Returns false when the mailer refused,
     * so the page can say so and offer the button again instead of pretending.
     */
    public function sendResultLink(ToolRun $run, ToolDefinition $tool): bool
    {
        try {
            Mail::to($run->email)->send(new ToolResultReady($run, $tool));
        } catch (Throwable $e) {
            Log::warning('Free tool result email failed', [
                'run' => $run->uuid,
                'tool' => $run->tool,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $run->forceFill(['email_sent_at' => now()])->save();

        return true;
    }
}
