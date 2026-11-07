<?php

namespace Tests\Feature\ResumeParsing;

use App\Exceptions\Api\FileProcessingException;
use App\Exceptions\Api\UnsupportedFileTypeException;
use App\Services\ResumeParsing\FileProcessor;
use App\Services\ResumeParsing\ProcessedFile;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FileProcessorTest extends TestCase
{
    public function test_text_file_is_extracted_as_text(): void
    {
        $body = str_repeat('Jane Doe — Senior Engineer at ACME. ', 10);
        $file = UploadedFile::fake()->createWithContent('resume.txt', $body);

        $result = (new FileProcessor())->process($file);

        $this->assertSame(ProcessedFile::TYPE_TEXT, $result->type);
        $this->assertStringContainsString('Jane Doe', $result->content);
        $this->assertSame('resume.txt', $result->filename);
    }

    public function test_empty_text_file_throws_processing_exception(): void
    {
        $file = UploadedFile::fake()->createWithContent('empty.txt', '');

        $this->expectException(FileProcessingException::class);

        (new FileProcessor())->process($file);
    }

    public function test_image_file_is_routed_to_vision_path(): void
    {
        $file = UploadedFile::fake()->image('resume.png', 800, 1000);

        $result = (new FileProcessor())->process($file);

        $this->assertSame(ProcessedFile::TYPE_IMAGE, $result->type);
        $this->assertFileExists($result->content);
        $this->assertSame('resume.png', $result->filename);
    }

    public function test_unsupported_extension_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('resume.exe', 'binary');

        $this->expectException(UnsupportedFileTypeException::class);

        (new FileProcessor())->process($file);
    }

    public function test_meta_for_log_only_contains_safe_fields(): void
    {
        $file = UploadedFile::fake()->createWithContent('cv.txt', str_repeat('x', 500));
        $processed = (new FileProcessor())->process($file);

        $meta = $processed->metaForLog();

        $this->assertSame(['filename', 'mime', 'size', 'extraction_path'], array_keys($meta));
        $this->assertArrayNotHasKey('content', $meta);
    }
}
