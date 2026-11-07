<?php

namespace App\Http\Resources\Api;

use App\Services\ResumeParsing\ParseConfidence;
use App\Services\ResumeParsing\ParsedResume;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParseResumeResource extends JsonResource
{
    public function __construct(
        ParsedResume $resume,
        public readonly string $extractionPath,
        public readonly int $creditsConsumed,
        public readonly int $creditsRemaining,
    ) {
        parent::__construct($resume);
    }

    public function toArray(Request $request): array
    {
        /** @var ParsedResume $resume */
        $resume = $this->resource;

        // Internal AI details (provider, model, tokens used) are logged in
        // usage_logs but deliberately never exposed to API consumers — only
        // the parsed data and the credits information are returned.
        return [
            'data' => $resume->data,
            'meta' => [
                'extraction_path' => $this->extractionPath,
                // The same certainty as data.confidence, as a whole percentage
                // (null when the model reported none).
                'confidence_percent' => ParseConfidence::percent($resume->confidence()),
                'credits_consumed' => $this->creditsConsumed,
                'credits_remaining' => $this->creditsRemaining,
            ],
        ];
    }
}
