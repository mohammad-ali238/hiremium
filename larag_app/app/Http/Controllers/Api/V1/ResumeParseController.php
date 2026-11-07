<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ParseResumeRequest;
use App\Http\Resources\Api\ParseResumeResource;
use App\Models\AiSetting;
use App\Services\Credits\CreditSource;
use App\Services\ResumeParsing\FileProcessor;
use App\Services\ResumeParsing\ResumeParserManager;
use Illuminate\Support\Facades\Log;

class ResumeParseController extends Controller
{
    public function __construct(
        private readonly FileProcessor $files,
        private readonly ResumeParserManager $parser,
    ) {}

    public function __invoke(ParseResumeRequest $request): ParseResumeResource
    {
        /** @var CreditSource $source */
        $source = $request->attributes->get('credit_source');

        $endpoint = 'resume-parse.v1';
        $settings = AiSetting::current();

        // 1. Process the file (cheap, local)
        $processed = $this->files->process($request->file('file'));

        // 2. Decide cost based on extraction path — trials use their own
        //    (configurable) cost table; paid subscriptions use the standard one.
        if ($source->type() === 'trial') {
            $cost = $processed->isText()
                ? (int) $settings->trial_credit_cost_parse_text
                : (int) $settings->trial_credit_cost_parse_vision;
        } else {
            $cost = $processed->isText()
                ? (int) $settings->parsing_credit_cost_text
                : (int) $settings->parsing_credit_cost_vision;
        }
        $cost = max(1, $cost);

        // 3. Endpoint-specific credit gate (middleware only ensured > 0)
        $source->ensureCanAfford($cost);

        // 4. Charge atomically
        $source->charge($cost);

        // 5. Call AI; refund + log on failure
        try {
            $result = $this->parser->parse($processed);
        } catch (ApiException $e) {
            $source->refund($cost);
            $source->logFailure($endpoint, $processed->metaForLog(), $e->getMessage(), $request->ip());

            Log::warning('Resume parsing failed; credits refunded', [
                'source' => $source->type(),
                'cost' => $cost,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        // 6. Log success (metadata only, no raw bytes / extracted text)
        $source->logSuccess($endpoint, [
            'credits_consumed' => $cost,
            'ai_provider' => $result->provider,
            'ai_model' => $result->model,
            'tokens_used' => $result->tokensUsed,
            'request_payload' => $processed->metaForLog(),
            'response_payload' => $result->rawResponse,
            'ip_address' => $request->ip(),
        ]);

        return new ParseResumeResource(
            resume: $result,
            extractionPath: $processed->type,
            creditsConsumed: $cost,
            creditsRemaining: $source->remaining(),
        );
    }
}
