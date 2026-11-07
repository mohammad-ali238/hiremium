<?php

namespace App\Exceptions\Api;

use Illuminate\Http\Response;

class FileProcessingException extends ApiException
{
    public int $status = Response::HTTP_UNPROCESSABLE_ENTITY;

    public string $errorCode = 'file_processing_failed';

    public function __construct(string $message = 'The file could not be read. It may be corrupt or empty.')
    {
        parent::__construct($message);
    }
}
