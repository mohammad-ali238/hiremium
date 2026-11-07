<?php

namespace App\Exceptions\Api;

use Illuminate\Http\Response;

class JobNotFoundException extends ApiException
{
    public int $status = Response::HTTP_NOT_FOUND;

    public string $errorCode = 'job_not_found';

    public function __construct(string $message = 'No job found for that ID.')
    {
        parent::__construct($message);
    }
}
