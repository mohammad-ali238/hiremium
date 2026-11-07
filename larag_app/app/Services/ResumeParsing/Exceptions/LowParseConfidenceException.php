<?php

namespace App\Services\ResumeParsing\Exceptions;

use App\Exceptions\Api\ApiException;
use Illuminate\Http\Response;

/**
 * The CV was parsed, but the parser's own confidence came back below the floor
 * (see ParseConfidence), so nothing was scored against the job.
 */
class LowParseConfidenceException extends ApiException
{
    public int $status = Response::HTTP_UNPROCESSABLE_ENTITY;

    public string $errorCode = 'low_parse_confidence';
}
