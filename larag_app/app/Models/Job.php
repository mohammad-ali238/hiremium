<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A recruiter job posting. Stored in `job_postings` (not the framework `jobs`
 * queue table). Its structured requirements build the text the AI scores each
 * candidate against.
 */
class Job extends Model
{
    use BelongsToWorkspace;
    use SoftDeletes;

    /** Columns frozen into a batch's job_snapshot when the batch is created. */
    public const SNAPSHOT_COLUMNS = [
        'id', 'workspace_id', 'created_by', 'title', 'description', 'employment_type',
        'location', 'location_type', 'min_experience_years', 'education',
        'must_have_skills', 'nice_to_have_skills', 'status', 'created_at', 'updated_at',
    ];

    protected static function booted(): void
    {
        // A user "deleting" a job hides it and everything analysed under it.
        // Rows stay (soft delete) so AI-usage history keeps adding up and the
        // admin can still see — and restore — what was removed.
        static::deleting(function (Job $job) {
            if ($job->isForceDeleting()) {
                return; // FK cascades handle a real purge.
            }
            $job->batches()->withTrashed()->whereNull('deleted_at')->chunkById(100, function ($batches) {
                foreach ($batches as $batch) {
                    $batch->delete(); // cascades to the batch's files
                }
            });
        });

        static::restored(function (Job $job) {
            $job->batches()->onlyTrashed()->chunkById(100, function ($batches) {
                foreach ($batches as $batch) {
                    $batch->restore();
                }
            });
        });
    }

    /**
     * The job frozen as plain data — stored on each batch so the analysis, and
     * everyone reading its results later, sees the job exactly as it was.
     */
    public function toSnapshot(): array
    {
        $data = [];
        foreach (self::SNAPSHOT_COLUMNS as $column) {
            $value = $this->getAttribute($column);
            $data[$column] = $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : $value;
        }
        $data['snapshot_taken_at'] = now()->format(DATE_ATOM);

        return $data;
    }

    /** Rebuild a (non-persisted) Job from a snapshot, for scoring and display. */
    public static function fromSnapshot(array $snapshot): self
    {
        $job = new self;
        // forceFill() runs the casts (array columns get encoded properly);
        // setRawAttributes() would hand json_decode() an already-decoded array.
        $job->forceFill(array_intersect_key($snapshot, array_flip(self::SNAPSHOT_COLUMNS)));
        $job->syncOriginal();
        $job->exists = true; // behaves like a loaded model (id available, no inserts)

        return $job;
    }

    protected $table = 'job_postings';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'workspace_id', 'created_by', 'title', 'description', 'employment_type',
        'location', 'location_type', 'min_experience_years', 'education',
        'must_have_skills', 'nice_to_have_skills', 'status',
    ];

    protected function casts(): array
    {
        return [
            'must_have_skills' => 'array',
            'nice_to_have_skills' => 'array',
            'min_experience_years' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function employmentTypes(): array
    {
        return [
            'full_time' => 'Full-time',
            'part_time' => 'Part-time',
            'contract' => 'Contract',
            'internship' => 'Internship',
            'temporary' => 'Temporary',
        ];
    }

    public static function locationTypes(): array
    {
        return ['remote' => 'Remote', 'hybrid' => 'Hybrid', 'onsite' => 'On-site'];
    }

    public static function statuses(): array
    {
        return [self::STATUS_DRAFT => 'Draft', self::STATUS_OPEN => 'Open', self::STATUS_CLOSED => 'Closed'];
    }

    /** Compose the job into the text the AI scores candidates against (Phase 6). */
    public function matches(): HasMany
    {
        return $this->hasMany(CandidateJobMatch::class, 'job_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ResumeBatch::class, 'job_id');
    }

    public function toMatchingText(): string
    {
        $parts = ['Job title: '.$this->title];

        if ($this->employment_type) {
            $parts[] = 'Employment type: '.(self::employmentTypes()[$this->employment_type] ?? $this->employment_type);
        }
        if ($this->location_type) {
            $parts[] = 'Work mode: '.(self::locationTypes()[$this->location_type] ?? $this->location_type);
        }
        if ($this->location) {
            $parts[] = 'Location: '.$this->location;
        }
        if ($this->min_experience_years) {
            $parts[] = 'Minimum experience: '.$this->min_experience_years.' years';
        }
        if ($this->education) {
            $parts[] = 'Education: '.$this->education;
        }
        if (! empty($this->must_have_skills)) {
            $parts[] = 'Must-have skills: '.implode(', ', $this->must_have_skills);
        }
        if (! empty($this->nice_to_have_skills)) {
            $parts[] = 'Nice-to-have skills: '.implode(', ', $this->nice_to_have_skills);
        }
        if ($this->description) {
            // Description is stored as (sanitized) HTML — give the model plain text.
            $parts[] = "\nDescription:\n".RichText::toPlainText($this->description);
        }

        return implode("\n", $parts);
    }
}
