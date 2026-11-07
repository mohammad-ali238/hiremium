<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One stored CV file within a batch. `file_hash` backs workspace-wide duplicate
 * detection; `status` advances as the pipeline parses/analyzes it (Phase 6).
 */
class ResumeFile extends Model
{
    use BelongsToWorkspace;
    use SoftDeletes;

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_SUBMITTED = 'submitted'; // handed to a provider batch, awaiting results

    public const STATUS_PARSED = 'parsed';

    public const STATUS_ANALYZED = 'analyzed';

    public const STATUS_FAILED = 'failed';

    /** failure_kind values */
    public const FAIL_CREDITS = 'credits'; // out of credits — CV is fine, re-run after top-up

    public const FAIL_ERROR = 'error';     // parse / AI / infrastructure failure

    protected $fillable = [
        'batch_id', 'workspace_id', 'candidate_id', 'job_id', 'original_name', 'storage_key',
        'disk', 'mime', 'ext', 'size', 'file_hash', 'source', 'status', 'parse_confidence', 'error', 'failure_kind',
        // Per-file AI result (always kept, even when another CV of the same
        // candidate holds the candidate's current match).
        'score', 'ai_score', 'category', 'reason', 'matched_skills', 'missing_skills',
        'strengths', 'concerns', 'must_have_coverage', 'sub_scores', 'score_breakdown', 'analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'parse_confidence' => 'float',
            'score' => 'integer',
            'ai_score' => 'integer',
            'matched_skills' => 'array',
            'missing_skills' => 'array',
            'strengths' => 'array',
            'concerns' => 'array',
            'must_have_coverage' => 'array',
            'sub_scores' => 'array',
            'score_breakdown' => 'array',
            'analyzed_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /** The batch, including one the user has since deleted (admin views, usage history). */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ResumeBatch::class, 'batch_id')->withTrashed();
    }
}
