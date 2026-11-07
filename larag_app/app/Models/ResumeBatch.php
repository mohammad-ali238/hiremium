<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A bulk CV upload assigned to a job. Aggregate counts drive the live progress
 * shown while the AI pipeline (Phase 6) processes its files.
 */
class ResumeBatch extends Model
{
    use BelongsToWorkspace;
    use SoftDeletes;

    protected static function booted(): void
    {
        // Freeze the job at creation time. Every creation path (bulk upload,
        // CSV import, ZIP auto-split) goes through this event.
        static::creating(function (ResumeBatch $batch) {
            if ($batch->job_snapshot === null && $batch->job_id) {
                $job = Job::withTrashed()->find($batch->job_id);
                if ($job) {
                    $batch->job_snapshot = $job->toSnapshot();
                }
            }
        });

        static::deleting(function (ResumeBatch $batch) {
            if ($batch->isForceDeleting()) {
                return;
            }
            $batch->files()->withTrashed()->whereNull('deleted_at')->update(['deleted_at' => now()]);
        });

        static::restored(function (ResumeBatch $batch) {
            $batch->files()->onlyTrashed()->update(['deleted_at' => null]);
        });
    }

    /**
     * The job to analyse against: the frozen snapshot taken when this batch
     * was created — never the live job, which the user may have edited since.
     * Falls back to the job row only for batches without a snapshot.
     */
    public function jobForAnalysis(): ?Job
    {
        if (is_array($this->job_snapshot) && ! empty($this->job_snapshot['title'])) {
            return Job::fromSnapshot($this->job_snapshot);
        }

        return $this->job()->withTrashed()->first();
    }

    public const STATUS_UPLOADED = 'uploaded';

    /** Queued intake: raw upload stored, a job is expanding/validating/splitting it. */
    public const STATUS_EXTRACTING = 'extracting';

    /** Intake claimed by a worker (atomic hand-off so a duplicate delivery can't extract twice). */
    public const STATUS_EXTRACTING_CLAIMED = 'extracting_claimed';

    /** Statuses during queued intake (before any file exists). */
    public const EXTRACTING_STATUSES = [self::STATUS_EXTRACTING, self::STATUS_EXTRACTING_CLAIMED];

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    /** Everything affordable is done; the rest is waiting on credits (not a failure). */
    public const STATUS_WAITING_CREDITS = 'waiting_credits';

    protected $fillable = [
        'workspace_id', 'job_id', 'created_by', 'status',
        'total_files', 'parsed_count', 'analyzed_count', 'failed_count', 'rejected_files',
        'upload_group_id', 'intake', 'completed_notified_at', 'credits_notified_at', 'job_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'total_files' => 'integer',
            'parsed_count' => 'integer',
            'analyzed_count' => 'integer',
            'failed_count' => 'integer',
            'rejected_files' => 'array',
            'intake' => 'array',
            'job_snapshot' => 'array',
            'completed_notified_at' => 'datetime',
            'credits_notified_at' => 'datetime',
        ];
    }

    /** The job row, including one the user has since deleted (for titles/labels). */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ResumeFile::class, 'batch_id');
    }

    public function providerBatches(): HasMany
    {
        return $this->hasMany(ProviderBatch::class, 'resume_batch_id');
    }

    /** Other batches created by the same upload (ZIP auto-split), excluding this one. */
    public function siblings()
    {
        return static::query()
            ->where('upload_group_id', $this->upload_group_id)
            ->whereKeyNot($this->id)
            ->orderBy('id');
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(PipelineUsageLog::class, 'resume_batch_id');
    }

    /** Files waiting on credits (failed with failure_kind=credits) — settled, not pending. */
    public function creditWaitingCount(): int
    {
        return $this->files()->where('status', ResumeFile::STATUS_FAILED)->where('failure_kind', ResumeFile::FAIL_CREDITS)->count();
    }

    /**
     * Progress = settled / total. "Settled" includes analyzed, really-failed AND
     * credit-waits (nothing more will happen to them until the user resumes), so a
     * waiting_credits batch shows 100% processed rather than a stuck bar.
     */
    public function progressPercent(): int
    {
        if ($this->total_files <= 0) {
            return 0;
        }

        $settled = $this->analyzed_count + $this->failed_count + $this->creditWaitingCount();

        return (int) min(100, round($settled / $this->total_files * 100));
    }
}
