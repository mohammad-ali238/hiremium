<?php

namespace App\Services\Matching;

use Carbon\Carbon;
use Throwable;

/**
 * Deterministic total-experience calculation from a parsed CV's experience[]
 * entries. Merges overlapping/concurrent roles (no double counting), treats
 * "Present"/"Current"/empty end dates as today, and tolerates the messy date
 * formats parsers emit ("2019", "Mar 2019", "03/2019", "2019-03", "2019-03-01").
 * Same input → same answer, unlike asking the model to do date arithmetic.
 */
class ExperienceCalculator
{
    /** A break longer than this many months counts as a career gap. */
    public const GAP_MONTHS = 6;

    /**
     * @param  array<int, array{startDate?: ?string, endDate?: ?string}>  $experience
     * @return float|null total years (1 decimal), or null when no usable dates
     */
    public static function totalYears(array $experience, ?Carbon $today = null): ?float
    {
        $today = ($today ?? Carbon::today())->startOfDay();
        $ranges = [];

        foreach ($experience as $item) {
            if (! is_array($item)) {
                continue;
            }
            $start = self::parseDate($item['startDate'] ?? null, isEnd: false, today: $today);
            if (! $start) {
                continue; // a role without a start date can't be measured
            }
            $end = self::parseDate($item['endDate'] ?? null, isEnd: true, today: $today) ?? $today;
            if ($end->lt($start)) {
                continue;
            }
            $ranges[] = [$start, $end];
        }

        if (! $ranges) {
            return null;
        }

        // Merge overlapping ranges so concurrent roles count once.
        usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($ranges as [$s, $e]) {
            if ($merged && $s->lte(end($merged)[1])) {
                $last = array_pop($merged);
                $merged[] = [$last[0], $e->gt($last[1]) ? $e : $last[1]];
            } else {
                $merged[] = [$s, $e];
            }
        }

        $days = 0;
        foreach ($merged as [$s, $e]) {
            $days += $s->diffInDays($e);
        }

        return round($days / 365.25, 1);
    }

    /**
     * Career-gap verdict, from the same dates — no AI involved, so the same CV
     * always gives the same answer. A gap counts when EITHER:
     *
     *  - more than GAP_MONTHS pass between one role ending and the next starting
     *    (overlapping/concurrent roles are merged first, so they never count), OR
     *  - no role is ongoing ("Present") AND the most recent role ended more than
     *    GAP_MONTHS ago.
     *
     * @param  array<int, array{startDate?: ?string, endDate?: ?string}>  $experience
     * @return array{has_gap: bool, reasons: string[], longest_gap_months: int, months_since_last: ?int, ongoing: bool}
     */
    public static function careerGap(array $experience, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->startOfDay();
        $ranges = [];
        $ongoing = false;

        foreach ($experience as $item) {
            if (! is_array($item)) {
                continue;
            }
            $start = self::parseDate($item['startDate'] ?? null, isEnd: false, today: $today);
            if (! $start) {
                continue;
            }
            $rawEnd = trim((string) ($item['endDate'] ?? ''));
            $isOngoing = $rawEnd === '' || (bool) preg_match('/^(present|current|now|ongoing|till date|to date|-)$/i', $rawEnd);
            $end = self::parseDate($item['endDate'] ?? null, isEnd: true, today: $today) ?? $today;
            if ($end->lt($start)) {
                continue;
            }
            $ongoing = $ongoing || $isOngoing;
            $ranges[] = [$start, $end];
        }

        $out = ['has_gap' => false, 'reasons' => [], 'longest_gap_months' => 0, 'months_since_last' => null, 'ongoing' => $ongoing];

        if (! $ranges) {
            return $out; // nothing datable — not evidence of a gap
        }

        usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($ranges as [$s, $e]) {
            if ($merged && $s->lte(end($merged)[1])) {
                $last = array_pop($merged);
                $merged[] = [$last[0], $e->gt($last[1]) ? $e : $last[1]];
            } else {
                $merged[] = [$s, $e];
            }
        }

        // 1) Gaps between consecutive roles.
        for ($i = 1; $i < count($merged); $i++) {
            $months = (int) floor($merged[$i - 1][1]->diffInMonths($merged[$i][0]));
            $out['longest_gap_months'] = max($out['longest_gap_months'], $months);
        }
        if ($out['longest_gap_months'] > self::GAP_MONTHS) {
            $out['has_gap'] = true;
            $out['reasons'][] = sprintf('a %d-month break between roles', $out['longest_gap_months']);
        }

        // 2) Nothing current, and the last role ended a while ago.
        $latestEnd = end($merged)[1];
        $out['months_since_last'] = (int) floor($latestEnd->diffInMonths($today));
        if (! $ongoing && $out['months_since_last'] > self::GAP_MONTHS) {
            $out['has_gap'] = true;
            $out['reasons'][] = sprintf('no current role — the most recent one ended %d months ago', $out['months_since_last']);
        }

        return $out;
    }

    /**
     * Parse the loose date strings parsers produce. Start dates default to the
     * first day of the period, end dates to the last day, so "2019"–"2021" = 3y.
     */
    public static function parseDate(?string $raw, bool $isEnd, ?Carbon $today = null): ?Carbon
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        if ($isEnd && preg_match('/^(present|current|now|ongoing|till date|to date|-)$/i', $raw)) {
            return ($today ?? Carbon::today())->startOfDay();
        }

        $s = str_replace(['.', '/', '\\'], '-', $raw);

        try {
            if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) {          // 2019-03-15
                return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
            }
            if (preg_match('/^(\d{4})-(\d{1,2})$/', $s, $m)) {                    // 2019-03
                $d = Carbon::create((int) $m[1], (int) $m[2], 1);

                return ($isEnd ? $d->endOfMonth() : $d)->startOfDay();
            }
            if (preg_match('/^(\d{1,2})-(\d{4})$/', $s, $m)) {                    // 03-2019
                $d = Carbon::create((int) $m[2], (int) $m[1], 1);

                return ($isEnd ? $d->endOfMonth() : $d)->startOfDay();
            }
            if (preg_match('/^(\d{4})$/', $s, $m)) {                              // 2019
                $d = Carbon::create((int) $m[1], 1, 1);

                return ($isEnd ? $d->endOfYear() : $d)->startOfDay();
            }
            if (preg_match('/([A-Za-z]{3,9})[\s\-,]+(\d{4})/', $raw, $m)) {       // Mar 2019 / March, 2019
                // The leading `!` resets every field the format does not supply.
                // Without it PHP fills the missing DAY from today, so "Feb 2021"
                // parsed on a 31st becomes 2021-02-31 → March 3rd, and the
                // startOfMonth/endOfMonth below then lands on the wrong month.
                $d = Carbon::createFromFormat('!M Y', substr(ucfirst(strtolower($m[1])), 0, 3).' '.$m[2]);
                if ($d) {
                    return ($isEnd ? $d->endOfMonth() : $d->startOfMonth())->startOfDay();
                }
            }
            if (preg_match('/(\d{4})/', $raw, $m)) {                             // anything containing a year
                $d = Carbon::create((int) $m[1], 1, 1);

                return ($isEnd ? $d->endOfYear() : $d)->startOfDay();
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
