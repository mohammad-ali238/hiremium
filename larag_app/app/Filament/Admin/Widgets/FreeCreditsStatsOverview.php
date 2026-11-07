<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Widgets\Concerns\InteractsWithUsageStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The free-credits (trial) API log. Trial keys can only reach the sync
 * endpoints — async dispatch is behind an active subscription.
 */
class FreeCreditsStatsOverview extends StatsOverviewWidget
{
    use InteractsWithUsageStats;

    protected static ?int $sort = 10;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    public function getHeading(): ?string
    {
        return 'Free credits'.$this->periodSuffix();
    }

    protected function getDescription(): ?string
    {
        return 'Trial-key API calls, billed against free credits rather than a subscription.';
    }

    protected function getStats(): array
    {
        $stats = $this->stats();
        $free = $stats->freeCredits();

        return [
            Stat::make('Total requests', number_format($free['total']))
                ->description('Sync only — trial keys cannot queue async jobs')
                ->descriptionIcon('heroicon-m-gift')
                ->color('info'),

            Stat::make('Successful', number_format($free['success']))
                ->description($this->rate($free['success'], $free['total']))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Failed', number_format($free['failed']))
                ->description('Refunded back to the trial balance')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($free['failed'] > 0 ? 'danger' : 'gray'),

            Stat::make('Tokens used', number_format($free['tokens']['total']))
                ->description($stats->formatTokens($free['tokens']))
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
