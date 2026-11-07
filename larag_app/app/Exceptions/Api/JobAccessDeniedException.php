<?php

namespace App\Exceptions\Api;

use Illuminate\Http\Response;

class JobAccessDeniedException extends ApiException
{
    public int $status = Response::HTTP_FORBIDDEN;

    public string $errorCode = 'job_access_denied';

    public function __construct(string $message = 'You do not have permission to view this job.')
    {
        parent::__construct($message);
    }
}
