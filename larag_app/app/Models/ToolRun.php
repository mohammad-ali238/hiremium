<?php

namespace App\Models;

use App\Services\ResumeParsing\ParseConfidence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One public Free Tool submission (table `aitools_runs`).
 *
 * Lifecycle: created on submit as AWAITING_OPEN with a 72h expiry → the email
 * link is opened (expiry cleared, permanent from then on) → stages run →
 * COMPLETED or FAILED. Nothing here touches workspace data, credits or usage
 * logs; free-tool records live in their own `aitools_*` tables.
 */
class ToolRun extends Model
{
    protected $table = 'aitools_runs';

    /** Submitted; the emailed link has never been opened, so nothing has run yet. */
    public const STATUS_AWAITING_OPEN = 'awaiting_open';

    /** A stage is running (in the visitor's tab, or in a worker after the tab closed). */
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** Never opened within the expiry window — the link is dead. */
    public const STATUS_EXPIRED = 'expired';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'input_data' => 'array',
            'output' => 'array',
            'marketing_opt_in' => 'boolean',
            'score' => 'integer',
            'ai_calls' => 'integer',
            'ai_tokens_total' => 'integer',
            'duration_ms' => 'integer',
            'attempts' => 'integer',
            'view_count' => 'integer',
            'email_resent_count' => 'integer',
            'progress' => 'integer',
            'consent_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'purge_after' => 'datetime',
        ];
    }

    public function files(): HasMany
    {
        return $this->hasMany(ToolRunFile::class, 'aitools_run_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ToolRunEvent::class, 'aitools_run_id');
    }

    public function hashes(): HasMany
    {
        return $this->hasMany(ToolDataHash::class, 'aitools_run_id');
    }

    /** The primary uploaded file (the CV, for tool #1). */
    public function primaryFile(): ?ToolRunFile
    {
        return $this->files()->orderBy('id')->first();
    }

    /**
     * How certain the parse behind this run was, 0-1 — written by the report
     * stage, or still on the parsed candidate itself. null for a tool that
     * never parses a resume (and for a run that failed before the parse).
     */
    public function parseConfidence(): ?float
    {
        $output = (array) $this->output;

        return ParseConfidence::normalize(
            $output['parse_confidence'] ?? ((array) ($output['candidate'] ?? []))['confidence'] ?? null
        );
    }

    /**
     * Score band, from the same thresholds the recruiter dashboard uses, so a
     * free result is labelled exactly like a paid one.
     */
    public static function bandFor(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        $bands = (array) config('hiremium.scoring.bands', ['excellent' => 90, 'good' => 75, 'fair' => 60]);

        return match (true) {
            $score >= (int) ($bands['excellent'] ?? 90) => 'excellent',
            $score >= (int) ($bands['good'] ?? 75) => 'good',
            $score >= (int) ($bands['fair'] ?? 60) => 'fair',
            default => 'weak',
        };
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function publicUrl(): string
    {
        return route('tools.result', ['id' => $this->id, 'uuid' => $this->uuid]);
    }

    /** Opened once = permanent. Unopened links die at expires_at. */
    public function isExpired(): bool
    {
        return $this->opened_at === null
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    /** Hours left before an unopened link dies (null once opened). */
    public function hoursUntilExpiry(): ?int
    {
        if ($this->opened_at !== null || $this->expires_at === null) {
            return null;
        }

        return max(0, (int) ceil(Carbon::now()->diffInMinutes($this->expires_at, false) / 60));
    }

    /** Never opened and past its window — the "expired links" cleanup target. */
    public function scopeExpiredUnopened(Builder $query): Builder
    {
        return $query->whereNull('opened_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    /** Past the hard retention date — the "older than N days" cleanup target. */
    public function scopePurgeable(Builder $query): Builder
    {
        return $query->whereNotNull('purge_after')->where('purge_after', '<', now());
    }

    public function scopeForTool(Builder $query, string $tool): Builder
    {
        return $query->where('tool', $tool);
    }
}
