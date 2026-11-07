<?php

namespace App\Exceptions\Api;

use Illuminate\Http\Response;

class UnsupportedFileTypeException extends ApiException
{
    public int $status = Response::HTTP_UNSUPPORTED_MEDIA_TYPE;

    public string $errorCode = 'unsupported_file_type';

    public function __construct(string $message = 'The uploaded file type is not supported. Allowed: PDF, DOC, DOCX, TXT, JPG, PNG, WebP, GIF.')
    {
        parent::__construct($message);
    }
}
