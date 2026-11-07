<?php

namespace App\Http\Resources\Api;

use App\Services\Ai\AiScoreResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchingScoreResource extends JsonResource
{
    public function __construct(
        AiScoreResult $result,
        public readonly int $creditsRemaining,
    ) {
        parent::__construct($result);
    }

    public function toArray(Request $request): array
    {
        /** @var AiScoreResult $result */
        $result = $this->resource;

        // Internal AI details (provider, model, tokens used) are logged in
        // usage_logs but deliberately never exposed to API consumers — the
        // score, the reasoning, the skill breakdown and the remaining credits
        // are all they need.
        //
        // matched/missing skills are judged against the job's stated
        // requirements, so they come back empty when the caller sends no
        // job.skills and the description names none.
        return [
            'score' => $result->score,
            'reason' => $result->reason,
            'matched_skills' => $result->matchedSkills,
            'missing_skills' => $result->missingSkills,
            'credits_remaining' => $this->creditsRemaining,
        ];
    }
}
