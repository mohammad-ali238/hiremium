<?php

namespace App\Services\Ai\Exceptions;

use App\Exceptions\Api\ApiException;
use Illuminate\Http\Response;

class AiProviderException extends ApiException
{
    public int $status = Response::HTTP_BAD_GATEWAY;

    public string $errorCode = 'ai_provider_error';
}
