<?php

namespace App\Services\CsvImport;

use League\Csv\Reader;

/**
 * Streams a CSV once and returns: the header row, total data-row count, and a
 * random representative sample of rows (reservoir sampling — flat memory at any
 * file size). Sample size is 20 when the file has >= 100 data rows, else 10
 * (all rows when fewer than that). Throws CsvImportException with a
 * user-friendly message when the file is unreadable or has no data rows.
 */
class CsvSampler
{
    public const NO_ROWS_MESSAGE = 'This file has no candidate rows to import — please check the CSV and try again.';

    /** Max cell length forwarded to the AI sample (keeps the prompt small).
     * Applies to SAMPLE ROWS ONLY — header cells are never truncated, because
     * the mapping UI's column labels must match the import job's labels
     * byte-for-byte (both derive from the header row). */
    private const CELL_LIMIT = 160;

    /**
     * @return array{headers: list<string>, row_count: int, samples: list<list<string>>, has_header_guess: bool}
     */
    public function sample(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new CsvImportException(self::NO_ROWS_MESSAGE);
        }

        $reader = Reader::from($path, 'r');
        $reader->setDelimiter(CsvRowReader::sniffDelimiter($path));
        // No header offset: we read the first row ourselves — it may be data.

        $headers = [];
        $count = 0;
        $reservoir = [];

        foreach ($reader->getRecords() as $record) {
            $row = $this->cleanRow($record, cap: $headers !== []);
            if ($row === null) {
                continue; // fully empty line
            }

            if ($headers === []) {
                $headers = $row; // uncapped — labels must match the import stage

                continue;
            }

            $count++;

            // Reservoir sampling against the LARGE-file size (20); trimmed to
            // the right K afterwards once the true row count is known.
            if (count($reservoir) < 20) {
                $reservoir[] = $row;
            } else {
                $slot = random_int(0, $count - 1);
                if ($slot < 20) {
                    $reservoir[$slot] = $row;
                }
            }
        }

        if ($headers === [] || $count === 0) {
            throw new CsvImportException(self::NO_ROWS_MESSAGE);
        }

        $k = $count >= 100 ? 20 : 10;
        if (count($reservoir) > $k) {
            shuffle($reservoir);
            $reservoir = array_slice($reservoir, 0, $k);
        }

        return [
            'headers' => $headers,
            'row_count' => $count,
            'samples' => array_values($reservoir),
            'has_header_guess' => $this->looksLikeHeader($headers),
        ];
    }

    /**
     * Trim cells (BOM stripped from the very first), cap cell length, drop
     * fully-empty rows. Returns null when the row has no content at all.
     *
     * @param  array<int|string, ?string>  $record
     * @return ?list<string>
     */
    private function cleanRow(array $record, bool $cap = true): ?array
    {
        $row = [];
        $hasContent = false;
        foreach (array_values($record) as $i => $cell) {
            $cell = (string) $cell;
            if ($i === 0) {
                $cell = preg_replace('/^\xEF\xBB\xBF/', '', $cell);
            }
            $cell = trim($cell);
            if ($cap && mb_strlen($cell) > self::CELL_LIMIT) {
                $cell = mb_substr($cell, 0, self::CELL_LIMIT).'…';
            }
            if ($cell !== '') {
                $hasContent = true;
            }
            $row[] = $cell;
        }

        return $hasContent ? $row : null;
    }

    /**
     * Cheap guess whether the first row is a header (labels) rather than data:
     * headers don't contain emails and are rarely long or mostly numeric.
     *
     * @param  list<string>  $headers
     */
    private function looksLikeHeader(array $headers): bool
    {
        $suspicious = 0;
        foreach ($headers as $cell) {
            if ($cell === '') {
                continue;
            }
            if (filter_var($cell, FILTER_VALIDATE_EMAIL) || is_numeric($cell) || mb_strlen($cell) > 60) {
                $suspicious++;
            }
        }

        return $suspicious === 0;
    }
}
