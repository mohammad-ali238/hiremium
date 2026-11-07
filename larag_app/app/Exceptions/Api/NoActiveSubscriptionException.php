<?php

namespace App\Exceptions\Api;

use Illuminate\Http\Response;

class NoActiveSubscriptionException extends ApiException
{
    public int $status = Response::HTTP_PAYMENT_REQUIRED;

    public string $errorCode = 'no_active_subscription';

    public function __construct(string $message = 'No active subscription found for this API key.')
    {
        parent::__construct($message);
    }
}
