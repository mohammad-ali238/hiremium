<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One candidate scored against one job. Unique per (candidate, job): re-scoring
 * updates in place.
 */
class CandidateJobMatch extends Model
{
    use BelongsToWorkspace;

    public const STATUS_NEW = 'new';

    public const STATUS_REVIEWING = 'reviewing';

    public const STATUS_SHORTLISTED = 'shortlisted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_HIRED = 'hired';

    public const PROC_PENDING = 'pending';

    public const PROC_PROCESSING = 'processing';

    public const PROC_COMPLETED = 'completed';

    public const PROC_FAILED = 'failed';

    protected $fillable = [
        'workspace_id', 'candidate_id', 'job_id', 'batch_id', 'resume_file_id',
        'score', 'ai_score', 'category', 'reason', 'matched_skills', 'missing_skills', 'strengths', 'concerns', 'must_have_coverage', 'sub_scores', 'score_breakdown',
        'status', 'rejection_reason', 'processing_status', 'credits_used',
    ];

    protected function casts(): array
    {
        return [
            'matched_skills' => 'array',
            'strengths' => 'array',
            'concerns' => 'array',
            'must_have_coverage' => 'array',
            'missing_skills' => 'array',
            'sub_scores' => 'array',
            'score_breakdown' => 'array',
            'score' => 'integer',
            'ai_score' => 'integer',
            'credits_used' => 'integer',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /** The job, including one the user has since deleted (admin views keep their labels). */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class)->withTrashed();
    }

    /**
     * Only matches whose job the user has not deleted. Soft-deleted jobs are
     * hidden everywhere in the dashboard except the AI-usage history.
     */
    public function scopeForLiveJobs(Builder $query): Builder
    {
        return $query->whereHas('job', fn (Builder $job) => $job->whereNull('job_postings.deleted_at'));
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ResumeBatch::class, 'batch_id')->withTrashed();
    }

    public function resumeFile(): BelongsTo
    {
        return $this->belongsTo(ResumeFile::class, 'resume_file_id');
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_NEW => 'New',
            self::STATUS_REVIEWING => 'Reviewing',
            self::STATUS_SHORTLISTED => 'Shortlisted',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_HIRED => 'Hired',
        ];
    }

    public static function categoryLabels(): array
    {
        return [
            'recommended' => 'Recommended',
            'consider' => 'Consider',
            'not_recommended' => 'Not recommended',
        ];
    }

    /** Map a score to a recruiter category using the configured bands. */
    public static function categoryForScore(int $score): string
    {
        $bands = config('hiremium.scoring.bands', ['good' => 75, 'fair' => 60]);

        if ($score >= ($bands['good'] ?? 75)) {
            return 'recommended';
        }
        if ($score >= ($bands['fair'] ?? 60)) {
            return 'consider';
        }

        return 'not_recommended';
    }
}
