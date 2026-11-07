<?php

namespace App\Services\ResumeParsing;

use App\Exceptions\Api\FileProcessingException;
use App\Exceptions\Api\UnsupportedFileTypeException;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * Extracts plain text from a resume file for inclusion in an AI prompt.
 * Unlike FileProcessor (which falls back to vision for scanned files),
 * this extractor is text-only: extraction must succeed or it throws.
 */
class ResumeTextExtractor
{
    public const MAX_WORDS = 3000;

    public const MAX_CHARS = 10000;

    public function extract(UploadedFile $file): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getMimeType());
        $path = (string) $file->getRealPath();

        $text = match (true) {
            $ext === 'pdf' || $mime === 'application/pdf' => $this->fromPdf($path),
            $ext === 'docx' => $this->fromWord($path, 'Word2007'),
            $ext === 'doc' => $this->fromWord($path, 'MsDoc'),
            $ext === 'txt' || str_starts_with($mime, 'text/') => $this->fromText($path),
            default => throw new UnsupportedFileTypeException('Resume file must be PDF, DOC, DOCX, or TXT.'),
        };

        $text = trim($text);

        if ($text === '') {
            throw new FileProcessingException('No text could be extracted from the resume file. If it is a scanned document, use the parse-resume endpoint instead.');
        }

        return $this->limit($text);
    }

    /**
     * Cap at MAX_WORDS words, then MAX_CHARS characters.
     */
    public function limit(string $text): string
    {
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) > self::MAX_WORDS) {
            $text = implode(' ', array_slice($words, 0, self::MAX_WORDS));
        }

        if (mb_strlen($text) > self::MAX_CHARS) {
            $text = mb_substr($text, 0, self::MAX_CHARS);
        }

        return $text;
    }

    private function fromPdf(string $path): string
    {
        try {
            $document = (new PdfParser)->parseFile($path);

            return $document->getText();
        } catch (Throwable $e) {
            throw new FileProcessingException('Could not read the PDF file: '.$e->getMessage());
        }
    }

    private function fromWord(string $path, string $reader): string
    {
        try {
            $phpWord = WordIOFactory::load($path, $reader);
        } catch (Throwable $e) {
            throw new FileProcessingException('Could not read the Word file: '.$e->getMessage());
        }

        $buffer = '';
        foreach ($phpWord->getSections() as $section) {
            $buffer .= $this->walkElements($section->getElements());
        }

        return $buffer;
    }

    private function fromText(string $path): string
    {
        $text = @file_get_contents($path);
        if ($text === false) {
            throw new FileProcessingException('The text file is unreadable.');
        }

        return $text;
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
}
