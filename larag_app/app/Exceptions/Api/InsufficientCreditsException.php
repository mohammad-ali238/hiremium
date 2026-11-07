<?php

namespace App\Exceptions\Api;

use Illuminate\Http\Response;

class InsufficientCreditsException extends ApiException
{
    public int $status = Response::HTTP_PAYMENT_REQUIRED;

    public string $errorCode = 'insufficient_credits';

    public function __construct(string $message = 'Insufficient credits remaining on this subscription.')
    {
        parent::__construct($message);
    }
}
