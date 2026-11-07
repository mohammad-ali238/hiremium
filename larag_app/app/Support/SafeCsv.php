<?php

namespace App\Support;

/**
 * Spreadsheet formula-injection guard for CSV exports (OWASP CSV Injection).
 *
 * Candidate data can be attacker-controlled (CSV imports, parsed CV content),
 * so a cell like "=HYPERLINK(...)" or "=cmd|' /C ...'!A0" must never reach an
 * exported file raw — Excel/Sheets would execute it on the recruiter's machine.
 * Cells starting with a formula trigger are prefixed with an apostrophe, which
 * spreadsheet apps treat as a hidden "this is text" marker.
 */
class SafeCsv
{
    private const TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], self::TRIGGERS, true) ? "'".$value : $value;
    }

    /**
     * A phone number as plain text. Numbers legitimately start with "+", which
     * self::cell() would prefix with an apostrophe — visible as a stray quote
     * in front of every exported number. Dropping everything a phone number
     * cannot contain (letters, quotes, pipes, brackets) leaves nothing a
     * spreadsheet could resolve into a formula call, so the value is safe raw.
     */
    public static function phone(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) preg_replace('/[^0-9+\-() .]/', '', (string) $value));
    }

    /**
     * @param  array<int|string, mixed>  $row
     * @return array<int|string, mixed>
     */
    public static function row(array $row): array
    {
        return array_map([self::class, 'cell'], $row);
    }
}
