<?php

namespace App\Services\ResumeParsing\Contracts;

use App\Models\AiSetting;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\ResumeParsing\ParsedResume;
use App\Services\ResumeParsing\ProcessedFile;

interface ResumeParsingProvider
{
    /**
     * Parse a processed resume file into structured data.
     *
     * @throws AiProviderException
     */
    public function parse(ProcessedFile $file, AiSetting $settings): ParsedResume;

    public function name(): string;
}
