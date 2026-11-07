<?php

namespace App\Services\Tools\Stages;

use App\Exceptions\Api\FileProcessingException;
use App\Models\ToolRun;
use App\Services\ResumeParsing\FileProcessor;
use App\Services\ResumeParsing\ProcessedFile;
use App\Services\Tools\Contracts\StageResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Stage 1 — read the uploaded file. No AI: PDF/DOC/DOCX/TXT go through the same
 * FileProcessor the recruiter pipeline uses.
 *
 * A PDF with too little extractable text (a scan) has no text to store; it is
 * flagged so the parse stage hands the file itself to a vision-capable model,
 * exactly as the pipeline does.
 */
class ExtractTextStage extends Stage
{
    public function key(): string
    {
        return 'extract';
    }

    public function label(): string
    {
        return 'Preparing your resume';
    }

    public function iconPath(): string
    {
        return 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z';
    }

    public function run(ToolRun $run): StageResult
    {
        $file = $run->primaryFile();

        if ($file === null) {
            throw new RuntimeException('The uploaded file is missing.');
        }

        $temp = $this->toTempFile($file->disk, $file->path, $file->original_name, $file->mime);

        try {
            $processed = app(FileProcessor::class)->process($temp);
        } finally {
            @unlink($temp->getRealPath());
        }

        $text = $processed->isText()
            ? mb_substr($processed->content, 0, (int) config('tools.limits.resume_text_chars', 24000))
            : '';

        $this->write($run, [
            // Working data for the parse stage. Dropped again once the report is
            // built — a shared result should not carry the whole CV text around.
            'resume_text' => $text,
            'needs_vision' => $processed->isImage(),
            // Taken from the upload record, never from the temp copy: a stat on
            // a file that is still being written reports nonsense, and a wrong
            // MIME is the difference between a vision call working and failing.
            'file_mime' => $file->mime ?: $processed->mime,
            'file_name' => $file->original_name,
            'file_size' => (int) $file->size_bytes,
        ]);

        $file->forceFill(['extracted_chars' => mb_strlen($text)])->save();

        return StageResult::local([
            'extraction_path' => $processed->type,
            'chars' => mb_strlen($text),
        ]);
    }

    /** The providers want an UploadedFile; the CV lives on a private disk. */
    private function toTempFile(string $disk, string $path, string $name, ?string $mime): UploadedFile
    {
        return new UploadedFile(self::copyToTemp($disk, $path), $name, $mime ?: null, null, true);
    }

    /**
     * Copy a stored file to a local temp path the AI providers can read.
     *
     * The copy is verified: a silently short write leaves a zero-byte file, and
     * a zero-byte "PDF" fails in the worst possible way — text extraction finds
     * nothing, the run falls through to the vision path, and the provider is
     * handed an empty document. Better to fail here, loudly, than there.
     */
    private static function copyToTemp(string $disk, string $path): string
    {
        if (! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException('The uploaded file is no longer available.');
        }

        $contents = (string) Storage::disk($disk)->get($path);

        // An empty upload is an unreadable one, and no amount of retrying will
        // put words in it — so it is a permanent failure, not a transient one.
        if ($contents === '') {
            throw new FileProcessingException('The uploaded file is empty.');
        }

        $temp = tempnam(sys_get_temp_dir(), 'tool_cv_');

        if ($temp === false) {
            throw new RuntimeException('Could not open a temporary file to read the resume.');
        }

        $written = file_put_contents($temp, $contents);
        clearstatcache(true, $temp);

        // A short write here is a local problem (disk full, tmp not writable),
        // which a retry can genuinely fix — so it stays a retryable failure.
        if ($written === false || $written !== strlen($contents)) {
            @unlink($temp);

            throw new RuntimeException('The resume could not be copied for processing.');
        }

        return $temp;
    }

    /** Rebuild the processed file for a later stage, without re-extracting. */
    public static function processedFor(ToolRun $run): ProcessedFile
    {
        $output = (array) $run->output;

        if (! ($output['needs_vision'] ?? false)) {
            return new ProcessedFile(
                type: ProcessedFile::TYPE_TEXT,
                content: (string) ($output['resume_text'] ?? ''),
                mime: (string) ($output['file_mime'] ?? 'text/plain'),
                filename: (string) ($output['file_name'] ?? 'resume'),
                size: (int) ($output['file_size'] ?? 0),
            );
        }

        // Scanned document: the model needs the file itself, so put it back on
        // disk for the length of this request. The caller unlinks it.
        $file = $run->primaryFile();

        if ($file === null) {
            throw new RuntimeException('The uploaded file is no longer available.');
        }

        return new ProcessedFile(
            type: ProcessedFile::TYPE_IMAGE,
            content: self::copyToTemp($file->disk, $file->path),
            mime: (string) ($output['file_mime'] ?? $file->mime ?? 'application/pdf'),
            filename: (string) ($output['file_name'] ?? $file->original_name),
            size: (int) ($output['file_size'] ?? $file->size_bytes),
        );
    }
}
