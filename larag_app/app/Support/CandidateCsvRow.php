<?php

namespace App\Support;

use App\Models\CandidateJobMatch;
use App\Services\ResumeParsing\ParseConfidence;

/**
 * One analysed candidate as a CSV line, shared by the Matched Candidates export
 * and the per-job screening report so both files carry the same columns.
 *
 * Candidate experience is the JOB-RELEVANT total the rubric scored, never the
 * candidate's overall years — matches analysed before rubric scoring have no
 * such figure and leave the cell empty.
 */
class CandidateCsvRow
{
    /**
     * @return array<int, string>
     */
    public static function headers(bool $withJob = false): array
    {
        return array_values(array_filter([
            'Candidate',
            'Email',
            'Phone',
            $withJob ? 'Job' : null,
            'Score',
            'Resume parsing confidence',
            'Candidate experience',
            'Reasons to hire',
            'Reasons to reject',
            'Status',
            'Matched skills',
            'Missing skills',
        ]));
    }

    /**
     * Cells for one match, in the same order as headers() and already guarded
     * against spreadsheet formula injection.
     *
     * @return array<int, mixed>
     */
    public static function cells(CandidateJobMatch $match, bool $withJob = false): array
    {
        $confidence = ParseConfidence::percent(
            $match->resumeFile?->parse_confidence ?? $match->candidate?->parse_confidence
        );

        $cells = [
            SafeCsv::cell($match->candidate?->name),
            SafeCsv::cell($match->candidate?->email),
            // Phone numbers start with "+", which SafeCsv::cell() would prefix
            // with a stray apostrophe, so they are stripped to digits instead.
            SafeCsv::phone($match->candidate?->phone),
        ];

        if ($withJob) {
            $cells[] = SafeCsv::cell($match->job?->title);
        }

        return array_merge($cells, SafeCsv::row([
            $match->score,
            $confidence === null ? '' : $confidence.'%',
            self::years($match->score_breakdown['relevant_experience_years'] ?? null),
            self::reasons($match->strengths ?? [], 'No reason to hire'),
            self::reasons($match->concerns ?? [], 'No reason to reject'),
            $match->status,
            implode('; ', $match->matched_skills ?? []),
            implode('; ', $match->missing_skills ?? []),
        ]));
    }

    /** "4.5" / "3" / "" — no trailing zeros, blank when the figure is unknown. */
    private static function years(mixed $value): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private static function reasons(array $items, string $empty): string
    {
        $items = array_values(array_filter(array_map('trim', array_map('strval', $items))));

        return $items === [] ? $empty : implode('; ', $items);
    }
}
