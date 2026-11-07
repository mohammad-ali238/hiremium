<?php

namespace App\Services\Experience;

use App\Services\Matching\ExperienceCalculator;
use Carbon\Carbon;

/**
 * Turns a span of years into the way a recruiter says it out loud.
 *
 * "5.2 years" is a machine talking. A person says "5 years", "5+ years" or
 * "almost 6 years", and that is what the report shows — the same rule the
 * prompt gives the model for any duration it writes into prose, so the page
 * never contradicts itself.
 */
class Duration
{
    /** Below this the remainder is noise; above the upper band it rounds up. */
    private const PLUS_FROM = 0.25;

    private const ALMOST_FROM = 0.75;

    /** e.g. 5.1 → "5 years", 5.4 → "5+ years", 5.8 → "Almost 6 years". */
    public static function humanYears(?float $years): string
    {
        if ($years === null || $years <= 0) {
            return '';
        }

        if ($years < 1) {
            return 'Under a year';
        }

        $whole = (int) floor($years);
        $fraction = $years - $whole;

        if ($fraction >= self::ALMOST_FROM) {
            return 'Almost '.($whole + 1).' years';
        }

        if ($fraction >= self::PLUS_FROM) {
            return $whole.'+ years';
        }

        return $whole.' '.($whole === 1 ? 'year' : 'years');
    }

    /**
     * The length of one role, from the loose date strings a parser produces.
     *
     * Returns '' when the dates cannot be read at all — better a row with no
     * duration than a made-up one.
     */
    public static function forRole(?string $startDate, ?string $endDate, ?Carbon $today = null): string
    {
        $today = ($today ?? Carbon::today())->startOfDay();

        $start = ExperienceCalculator::parseDate($startDate, false, $today);

        if ($start === null) {
            return '';
        }

        $end = ExperienceCalculator::parseDate($endDate, true, $today) ?? $today;

        if ($end->lessThan($start)) {
            return '';
        }

        return self::humanYears($start->diffInDays($end) / 365.25);
    }

    /** What a role's end reads as. An empty end date means they are still there. */
    public static function endLabel(?string $endDate): string
    {
        $raw = trim((string) $endDate);

        if ($raw === '') {
            return 'Present';
        }

        return preg_match('/^(present|current|now|to date|ongoing)$/i', $raw) ? 'Present' : $raw;
    }
}
