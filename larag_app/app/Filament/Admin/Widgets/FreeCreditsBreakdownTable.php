<?php

namespace App\Filament\Admin\Widgets;

/** The same per-endpoint breakdown as the API section, for trial-key calls. */
class FreeCreditsBreakdownTable extends ApiBreakdownTable
{
    protected static ?int $sort = 11;

    protected function heading(): string
    {
        return 'Per endpoint (free credits)'.$this->periodSuffix();
    }

    protected function rows(): array
    {
        return $this->formatEndpointRows($this->stats()->freeCredits()['perEndpoint']);
    }

    protected function emptyMessage(): string
    {
        return 'No free-credit requests in this period.';
    }
}
