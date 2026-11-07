<?php

namespace App\Services\ResumeParsing;

final class ProcessedFile
{
    public const TYPE_TEXT = 'text';

    public const TYPE_IMAGE = 'image';

    public function __construct(
        public readonly string $type,         // 'text' | 'image'
        public readonly string $content,      // text content OR file path
        public readonly string $mime,
        public readonly string $filename,
        public readonly int $size,
    ) {}

    public function isText(): bool
    {
        return $this->type === self::TYPE_TEXT;
    }

    public function isImage(): bool
    {
        return $this->type === self::TYPE_IMAGE;
    }

    public function metaForLog(): array
    {
        return [
            'filename' => $this->filename,
            'mime' => $this->mime,
            'size' => $this->size,
            'extraction_path' => $this->type,
        ];
    }
}
