<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Widgets\Concerns\InteractsWithUsageStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Free Tools totals. "Opened" is counted on `opened_at` and "AI processed" on
 * `completed_at`, so a run opened today still counts today even when it was
 * submitted earlier.
 */
class ToolsStatsOverview extends StatsOverviewWidget
{
    use InteractsWithUsageStats;

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        return 'Free Tools'.$this->periodSuffix();
    }

    protected function getDescription(): ?string
    {
        return 'Public tool runs — opened from the emailed link, then processed through every AI stage.';
    }

    protected function getStats(): array
    {
        $stats = $this->stats();
        $tools = $stats->tools();

        return [
            Stat::make('Opened successfully', number_format($tools['opened']))
                ->description(number_format($tools['submitted']).' submitted in this period')
                ->descriptionIcon('heroicon-m-envelope-open')
                ->color('info'),

            Stat::make('AI processed', number_format($tools['processed']))
                ->description($tools['failed'] > 0
                    ? number_format($tools['failed']).' failed'
                    : 'No failures')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color($tools['failed'] > 0 ? 'warning' : 'success'),

            Stat::make('Tokens used', number_format($tools['tokens']['total']))
                ->description($stats->formatTokens($tools['tokens']))
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('primary'),
        ];
    }
}
