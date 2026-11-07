<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person in the workspace talent pool (deduped by email/phone). Holds the
 * parsed resume data; scores live on candidate_job_matches (one per job).
 */
class Candidate extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'name', 'email', 'phone', 'job_title', 'location',
        'summary', 'skills', 'experience_years', 'parsed_data', 'parse_confidence',
        'primary_resume_file_id',
    ];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'parsed_data' => 'array',
            'parse_confidence' => 'float',
            'experience_years' => 'integer',
        ];
    }

    public function matches(): HasMany
    {
        return $this->hasMany(CandidateJobMatch::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CandidateNote::class)->latest('id');
    }
}
