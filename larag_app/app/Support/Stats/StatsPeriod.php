<?php

namespace App\Support\Stats;

use Illuminate\Support\Carbon;

/**
 * The duration filter shared by every widget on the admin dashboard. Each case
 * resolves to an inclusive [from, to] window plus the bucket the trend chart
 * should group by, so hourly detail is kept for a single day and monthly
 * detail for the long ranges.
 */
enum StatsPeriod: string
{
    case Today = 'today';
    case LastWeek = 'last_week';
    case LastMonth = 'last_month';
    case LastSixMonths = 'last_6_months';
    case LastYear = 'last_year';
    case Everything = 'everything';

    public static function fromFilters(?array $filters): self
    {
        return self::tryFrom($filters['period'] ?? '') ?? self::Today;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Today->value => 'Today',
            self::LastWeek->value => 'Last week',
            self::LastMonth->value => 'Last month',
            self::LastSixMonths->value => 'Last 6 months',
            self::LastYear->value => 'Last year',
            self::Everything->value => 'Everything',
        ];
    }

    public function label(): string
    {
        return self::options()[$this->value];
    }

    /** Start of the window; null for Everything, which reaches back to the first row. */
    public function startsAt(): ?Carbon
    {
        $today = Carbon::today();

        return match ($this) {
            self::Today => $today,
            self::LastWeek => $today->copy()->subDays(6),
            self::LastMonth => $today->copy()->subDays(29),
            self::LastSixMonths => $today->copy()->subMonths(6)->addDay(),
            self::LastYear => $today->copy()->subYear()->addDay(),
            self::Everything => null,
        };
    }

    /** End of the window — always the end of the current day. */
    public function endsAt(): Carbon
    {
        return Carbon::today()->endOfDay();
    }

    /** Grouping for the tokens trend chart: hour, day or month. */
    public function bucket(): string
    {
        return match ($this) {
            self::Today => 'hour',
            self::LastWeek, self::LastMonth => 'day',
            self::LastSixMonths, self::LastYear, self::Everything => 'month',
        };
    }
}
