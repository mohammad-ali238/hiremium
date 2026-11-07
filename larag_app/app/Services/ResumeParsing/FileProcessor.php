<?php

namespace App\Services\ResumeParsing;

use App\Exceptions\Api\FileProcessingException;
use App\Exceptions\Api\UnsupportedFileTypeException;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

class FileProcessor
{
    public function __construct(private ResumeTextExtractor $extractor) {}

    private const MIN_TEXT_CHARS = 100;

    private const IMAGE_MIMES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
    ];

    public function process(UploadedFile $file): ProcessedFile
    {
        $mime = strtolower((string) $file->getMimeType());
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $filename = (string) $file->getClientOriginalName();
        $size = (int) $file->getSize();
        $path = (string) $file->getRealPath();

        if ($this->isPdf($mime, $ext)) {
            return $this->processPdf($path, $filename, $mime ?: 'application/pdf', $size);
        }

        if ($this->isDocx($mime, $ext)) {
            return $this->processDocx($path, $filename, $mime ?: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', $size);
        }

        if ($this->isDoc($mime, $ext)) {
            return $this->processDoc($path, $filename, $mime ?: 'application/msword', $size);
        }

        if ($this->isText($mime, $ext)) {
            return $this->processText($path, $filename, $mime ?: 'text/plain', $size);
        }

        if ($this->isImage($mime, $ext)) {
            return new ProcessedFile(
                type: ProcessedFile::TYPE_IMAGE,
                content: $path,
                mime: $mime ?: 'application/octet-stream',
                filename: $filename,
                size: $size,
            );
        }

        throw new UnsupportedFileTypeException;
    }

    private function processPdf(string $path, string $filename, string $mime, int $size): ProcessedFile
    {
        try {
            $parser = new PdfParser;
            $document = $parser->parseFile($path);
            $text = trim($document->getText());
        } catch (Throwable) {
            $text = '';
        }

        if (mb_strlen($text) >= self::MIN_TEXT_CHARS) {
            return new ProcessedFile(
                type: ProcessedFile::TYPE_TEXT,
                content: $this->extractor->limit($text),
                mime: $mime,
                filename: $filename,
                size: $size,
            );
        }

        // Fallback: send raw PDF to vision-capable AI.
        return new ProcessedFile(
            type: ProcessedFile::TYPE_IMAGE,
            content: $path,
            mime: $mime,
            filename: $filename,
            size: $size,
        );
    }

    private function processDocx(string $path, string $filename, string $mime, int $size): ProcessedFile
    {
        try {
            $phpWord = WordIOFactory::load($path);
            $text = $this->extractDocxText($phpWord);
        } catch (Throwable $e) {
            throw new FileProcessingException('Could not read the DOCX file: '.$e->getMessage());
        }

        if (mb_strlen(trim($text)) < self::MIN_TEXT_CHARS) {
            throw new FileProcessingException('The DOCX appears to be empty or contains too little extractable text.');
        }

        return new ProcessedFile(
            type: ProcessedFile::TYPE_TEXT,
            content: $this->extractor->limit(trim($text)),
            mime: $mime,
            filename: $filename,
            size: $size,
        );
    }

    private function processDoc(string $path, string $filename, string $mime, int $size): ProcessedFile
    {
        // Legacy binary Word documents — PhpWord's MsDoc reader (no vision
        // fallback exists for the binary format, so extraction must work).
        try {
            $phpWord = WordIOFactory::load($path, 'MsDoc');
            $text = $this->repairDocEncoding($this->extractDocxText($phpWord));
        } catch (Throwable $e) {
            throw new FileProcessingException('Could not read the DOC file: '.$e->getMessage());
        }

        if (mb_strlen(trim($text)) < self::MIN_TEXT_CHARS) {
            throw new FileProcessingException('The DOC appears to be empty or contains too little extractable text.');
        }

        return new ProcessedFile(
            type: ProcessedFile::TYPE_TEXT,
            content: $this->extractor->limit(trim($text)),
            mime: $mime,
            filename: $filename,
            size: $size,
        );
    }

    /**
     * The MsDoc reader sometimes decodes a DOC's single-byte (ANSI) text
     * stream as UTF-16LE, turning plain latin text into CJK mojibake
     * ("JA" becomes U+414A). Detect that by the share of basic-latin
     * characters and reverse it: re-encode the string back to its original
     * byte stream and read those bytes as Windows-1252. The repair is only
     * kept when it clearly yields readable latin text, so genuinely
     * non-latin resumes are never touched.
     */
    private function repairDocEncoding(string $text): string
    {
        if (trim($text) === '' || $this->latinRatio($text) >= 0.6) {
            return $text;
        }

        try {
            $bytes = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
            $candidate = mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
        } catch (Throwable) {
            return $text;
        }

        // Drop stray control bytes the round-trip may surface.
        $candidate = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $candidate);

        return $this->latinRatio($candidate) >= 0.85 ? $candidate : $text;
    }

    /**
     * Share of basic-latin (printable ASCII + whitespace) characters.
     */
    private function latinRatio(string $text): float
    {
        $total = mb_strlen($text);
        if ($total < 1) {
            return 1.0;
        }

        return (int) preg_match_all('/[\x20-\x7E\s]/u', $text) / $total;
    }

    private function processText(string $path, string $filename, string $mime, int $size): ProcessedFile
    {
        $text = @file_get_contents($path);
        if ($text === false || trim($text) === '') {
            throw new FileProcessingException('The text file is empty or unreadable.');
        }

        return new ProcessedFile(
            type: ProcessedFile::TYPE_TEXT,
            content: $this->extractor->limit(trim($text)),
            mime: $mime,
            filename: $filename,
            size: $size,
        );
    }

    private function extractDocxText(PhpWord $phpWord): string
    {
        $buffer = '';
        foreach ($phpWord->getSections() as $section) {
            $buffer .= $this->walkElements($section->getElements());
        }

        return $buffer;
    }

    private function walkElements(array $elements): string
    {
        $out = '';
        foreach ($elements as $el) {
            if (method_exists($el, 'getText')) {
                $text = $el->getText();
                if (is_string($text)) {
                    $out .= $text."\n";
                }
            }
            if (method_exists($el, 'getElements')) {
                $out .= $this->walkElements($el->getElements());
            }
        }

        return $out;
    }

    private function isPdf(string $mime, string $ext): bool
    {
        return $mime === 'application/pdf' || $ext === 'pdf';
    }

    private function isDocx(string $mime, string $ext): bool
    {
        return $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || $ext === 'docx';
    }

    private function isDoc(string $mime, string $ext): bool
    {
        return $mime === 'application/msword' || $ext === 'doc';
    }

    private function isText(string $mime, string $ext): bool
    {
        return str_starts_with($mime, 'text/') || $ext === 'txt';
    }

    private function isImage(string $mime, string $ext): bool
    {
        return in_array($mime, self::IMAGE_MIMES, true)
            || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }
}
