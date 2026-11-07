<?php

namespace App\Services\CsvImport;

use Generator;
use League\Csv\Reader;

/**
 * Shared low-level CSV access for the import feature: delimiter sniffing, BOM
 * handling, unique column labels, and a streaming row generator that reads the
 * FULL cell values (unlike CsvSampler, which caps cells for the AI prompt).
 */
class CsvRowReader
{
    /** Pick the delimiter (',' ';' or tab) that appears most in the first line. */
    public static function sniffDelimiter(string $path): string
    {
        $line = '';
        $fh = @fopen($path, 'rb');
        if ($fh) {
            $line = (string) fgets($fh, 65536);
            fclose($fh);
        }

        $best = ',';
        $bestCount = -1;
        foreach ([',', ';', "\t"] as $delimiter) {
            $n = substr_count($line, $delimiter);
            if ($n > $bestCount) {
                $best = $delimiter;
                $bestCount = $n;
            }
        }

        return $best;
    }

    /**
     * Column labels must be unique (they are the dropdown values and the keys a
     * saved mapping refers to) — duplicates get a numeric suffix; empties get a
     * positional name. MUST stay in sync between the mapping UI and the import
     * job: both call this on the same header row.
     *
     * @param  list<string>  $headers
     * @return list<string>
     */
    public static function uniqueLabels(array $headers): array
    {
        $seen = [];
        $labels = [];
        foreach ($headers as $i => $header) {
            $label = trim($header) !== '' ? $header : 'Column '.($i + 1);
            $base = $label;
            $n = 1;
            while (isset($seen[mb_strtolower($label)])) {
                $label = $base.' ('.++$n.')';
            }
            $seen[mb_strtolower($label)] = true;
            $labels[] = $label;
        }

        return $labels;
    }

    /**
     * Positional column labels for a CSV with NO header row: "Col #1".."Col #N".
     * Used consistently by the mapping UI and the import job so a confirmed
     * positional mapping resolves identically at import time.
     *
     * @return list<string>
     */
    public static function positionalLabels(int $count): array
    {
        return array_map(fn ($i) => 'Col #'.($i + 1), range(0, max(0, $count - 1)));
    }

    /**
     * The header row: first row of the file that has any content (BOM stripped,
     * cells trimmed). Empty array when the file has none.
     *
     * @return list<string>
     */
    public function headers(string $path): array
    {
        foreach ($this->allRows($path) as $row) {
            return $row;
        }

        return [];
    }

    /**
     * Stream the DATA rows with FULL cell values. By default the first row is
     * treated as the header and skipped; pass $skipFirst = false for a CSV
     * with no header row (every row, including the first, is data). Yields
     * [dataRowNumber (1-based), cells]; fully-empty lines are skipped and not
     * numbered.
     *
     * @return Generator<int, array{0: int, 1: list<string>}>
     */
    public function rows(string $path, bool $skipFirst = true): Generator
    {
        $first = true;
        $n = 0;
        foreach ($this->allRows($path) as $row) {
            if ($first) {
                $first = false;
                if ($skipFirst) {
                    continue;
                }
            }
            yield [++$n, $row];
        }
    }

    /**
     * Every non-empty row of the file, cleaned (BOM off the very first cell,
     * cells trimmed, no length cap).
     *
     * @return Generator<int, list<string>>
     */
    private function allRows(string $path): Generator
    {
        $reader = Reader::from($path, 'r');
        $reader->setDelimiter(self::sniffDelimiter($path));

        $firstRecord = true;
        foreach ($reader->getRecords() as $record) {
            $row = [];
            $hasContent = false;
            foreach (array_values($record) as $i => $cell) {
                $cell = (string) $cell;
                if ($firstRecord && $i === 0) {
                    $cell = preg_replace('/^\xEF\xBB\xBF/', '', $cell);
                }
                $cell = trim($cell);
                if ($cell !== '') {
                    $hasContent = true;
                }
                $row[] = $cell;
            }
            $firstRecord = false;
            if ($hasContent) {
                yield $row;
            }
        }
    }
}
