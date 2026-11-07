<?php

namespace App\Services\Pipeline;

use App\Models\Candidate;
use App\Models\CandidateJobMatch;
use App\Models\Job;
use App\Models\ResumeBatch;
use App\Models\ResumeFile;
use App\Services\Ai\AiScoreResult;
use App\Services\ResumeParsing\ParseConfidence;

/**
 * Turns a parsed CV + its score into persisted Candidate and CandidateJobMatch
 * rows. Shared by the live pipeline (ProcessResumeFileJob) and the async batch
 * ingestor so both lanes produce identical records.
 */
class CandidateMatchWriter
{
    /**
     * The candidate (if any) who already owns a CV with these exact bytes in
     * this workspace — the strongest identity signal we have (works even for
     * scanned CVs where email/phone parsing fails). Excludes the file itself.
     */
    public function findCandidateByFileHash(int $workspaceId, string $hash, ?int $excludeFileId = null): ?Candidate
    {
        $candidateId = ResumeFile::where('workspace_id', $workspaceId)
            ->where('file_hash', $hash)
            ->whereNotNull('candidate_id')
            ->when($excludeFileId, fn ($q) => $q->where('id', '!=', $excludeFileId))
            ->orderByDesc('id')
            ->value('candidate_id');

        return $candidateId ? Candidate::find($candidateId) : null;
    }

    /**
     * Find-or-create the workspace candidate and refresh its profile.
     * Identity order: exact file hash → email → phone → create new.
     */
    public function resolveCandidate(int $workspaceId, array $data, ResumeFile $file): Candidate
    {
        $email = trim((string) ($data['email'] ?? '')) ?: null;
        $phone = trim((string) ($data['phone'] ?? '')) ?: null;

        // 1) Same bytes = same person, regardless of parse quality.
        $candidate = $file->file_hash
            ? $this->findCandidateByFileHash($workspaceId, $file->file_hash, $file->id)
            : null;
        // 2) Then contact details.
        if (! $candidate && $email) {
            $candidate = Candidate::where('workspace_id', $workspaceId)->where('email', $email)->first();
        }
        if (! $candidate && $phone) {
            $candidate = Candidate::where('workspace_id', $workspaceId)->where('phone', $phone)->first();
        }

        $attributes = $this->profileAttributes($data, $file);

        if ($candidate) {
            // Existing person: do NOT overwrite the profile from every new CV — the
            // profile follows the file that holds the candidate's current match
            // (see writeMatch). Only fill fields that are still empty.
            $fill = array_filter($attributes, fn ($v, $k) => $k !== 'parsed_data' && $k !== 'primary_resume_file_id'
                && ($candidate->{$k} === null || $candidate->{$k} === '' || $candidate->{$k} === []), ARRAY_FILTER_USE_BOTH);
            if ($fill) {
                $candidate->update($fill);
            }

            return $candidate;
        }

        return Candidate::create(array_merge(['workspace_id' => $workspaceId], $attributes));
    }

    /** Profile fields derived from a parse. */
    private function profileAttributes(array $data, ResumeFile $file): array
    {
        return [
            'name' => ($data['fullName'] ?? '') ?: null,
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'job_title' => ($data['jobTitle'] ?? '') ?: null,
            'location' => null,
            'summary' => ($data['summary'] ?? '') ?: null,
            'skills' => array_values(array_filter((array) ($data['skills'] ?? []))),
            'parsed_data' => $data,
            'parse_confidence' => ParseConfidence::fromData($data),
            'primary_resume_file_id' => $file->id,
        ];
    }

    /**
     * Persist a completed analysis.
     *
     * 1) The FILE always keeps its own result (score/reasons/skills) — that is
     *    what the credit paid for and what the batch page shows per row.
     * 2) The candidate's match for this job (one row per candidate+job) is
     *    created if missing, or REPLACED only when this file scores strictly
     *    higher than the file currently backing it ("best score wins"; ties keep
     *    the older/current one). When promoted, the candidate's profile fields
     *    are refreshed from this file's parse so identity == displayed score.
     *
     * @return bool true when this file became (or stays) the candidate's current match
     */
    public function writeMatch(ResumeFile $file, ResumeBatch $batch, Job $job, Candidate $candidate, AiScoreResult $result, int $cost, array $parsedData = []): bool
    {
        $file->update([
            'job_id' => $job->id,
            'score' => $result->score,
            // What the model scored on its own — admin-only, never shown to the
            // recruiter, who sees the rubric total in `score`.
            'ai_score' => $result->aiScore,
            'score_breakdown' => $result->breakdown,
            'category' => CandidateJobMatch::categoryForScore($result->score),
            'reason' => $result->reason,
            'matched_skills' => $result->matchedSkills,
            'missing_skills' => $result->missingSkills,
            'strengths' => $result->strengths,
            'concerns' => $result->concerns,
            'must_have_coverage' => $result->mustHaveCoverage,
            'sub_scores' => $result->subScores,
            'analyzed_at' => now(),
        ]);

        $existing = CandidateJobMatch::where('candidate_id', $candidate->id)->where('job_id', $job->id)->first();

        $isCurrent = $existing === null
            || $existing->score === null
            || $existing->resume_file_id === $file->id
            || $result->score > (int) $existing->score;

        if (! $isCurrent) {
            // Lower (or equal) than the current best: keep the existing match
            // untouched; this file simply appears in the candidate's CV history.
            return false;
        }

        CandidateJobMatch::updateOrCreate(
            ['candidate_id' => $candidate->id, 'job_id' => $job->id],
            [
                'workspace_id' => $batch->workspace_id,
                'batch_id' => $batch->id,
                'resume_file_id' => $file->id,
                'score' => $result->score,
                'ai_score' => $result->aiScore,
                'score_breakdown' => $result->breakdown,
                'category' => CandidateJobMatch::categoryForScore($result->score),
                'reason' => $result->reason,
                'matched_skills' => $result->matchedSkills,
                'missing_skills' => $result->missingSkills,
                'strengths' => $result->strengths,
                'concerns' => $result->concerns,
                'must_have_coverage' => $result->mustHaveCoverage,
                'sub_scores' => $result->subScores,
                'processing_status' => CandidateJobMatch::PROC_COMPLETED,
                'credits_used' => $cost,
            ]
        );

        // The profile follows the current-best file.
        if ($parsedData) {
            $candidate->update($this->profileAttributes($parsedData, $file));
        } elseif ($candidate->primary_resume_file_id !== $file->id) {
            $candidate->update(['primary_resume_file_id' => $file->id]);
        }

        return true;
    }

    /** Persist a failed match (candidate resolved but scoring/parsing failed). */
    public function writeFailedMatch(ResumeFile $file, ResumeBatch $batch, Job $job, Candidate $candidate): void
    {
        // Never downgrade an existing scored match because a LATER CV of the same
        // person failed — the failure lives on that file (status/error). Only
        // create a failed placeholder when the candidate has no match yet.
        $existing = CandidateJobMatch::where('candidate_id', $candidate->id)->where('job_id', $job->id)->first();
        if ($existing && $existing->score !== null) {
            return;
        }

        CandidateJobMatch::updateOrCreate(
            ['candidate_id' => $candidate->id, 'job_id' => $job->id],
            [
                'workspace_id' => $batch->workspace_id,
                'batch_id' => $batch->id,
                'resume_file_id' => $file->id,
                'processing_status' => CandidateJobMatch::PROC_FAILED,
            ]
        );
    }
}
