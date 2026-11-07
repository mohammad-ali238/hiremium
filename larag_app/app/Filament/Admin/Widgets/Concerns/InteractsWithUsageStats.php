<?php

namespace App\Filament\Admin\Widgets\Concerns;

use App\Services\Admin\AdminUsageStats;
use App\Support\Stats\StatsPeriod;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Binds a dashboard widget to the duration filter at the top of the page and
 * hands it a stats instance for that window.
 */
trait InteractsWithUsageStats
{
    use InteractsWithPageFilters;

    private ?AdminUsageStats $usageStats = null;

    protected function period(): StatsPeriod
    {
        return StatsPeriod::fromFilters($this->pageFilters);
    }

    protected function stats(): AdminUsageStats
    {
        $period = $this->period();

        if ($this->usageStats?->period !== $period) {
            $this->usageStats = AdminUsageStats::for($period);
        }

        return $this->usageStats;
    }

    /** Every section heading carries the window it is describing. */
    protected function periodSuffix(): string
    {
        return ' · '.$this->period()->label();
    }
}
