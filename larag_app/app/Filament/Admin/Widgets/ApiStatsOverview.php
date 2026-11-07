<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Widgets\Concerns\InteractsWithUsageStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Paid API traffic. A refunded row is a failed call — the credits went back to
 * the customer — so refunds are counted as failures here.
 */
class ApiStatsOverview extends StatsOverviewWidget
{
    use InteractsWithUsageStats;

    protected static ?int $sort = 8;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    public function getHeading(): ?string
    {
        return 'APIs'.$this->periodSuffix();
    }

    protected function getDescription(): ?string
    {
        return 'Subscription API calls — the sync endpoints and the queued async lane.';
    }

    protected function getStats(): array
    {
        $stats = $this->stats();
        $api = $stats->api();

        return [
            Stat::make('Total requests', number_format($api['total']))
                ->description('Sync: '.number_format($api['sync']).' | Async: '.number_format($api['async']))
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color('info'),

            Stat::make('Successful', number_format($api['success']))
                ->description($this->rate($api['success'], $api['total']))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Failed', number_format($api['failed']))
                ->description('Refunded back to the customer')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($api['failed'] > 0 ? 'danger' : 'gray'),

            Stat::make('Tokens used', number_format($api['tokens']['total']))
                ->description($stats->formatTokens($api['tokens']))
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('primary'),
        ];
    }

    private function rate(int $value, int $total): string
    {
        if ($total === 0) {
            return 'No requests in this period';
        }

        return round($value / $total * 100, 1).'% success rate';
    }
}
