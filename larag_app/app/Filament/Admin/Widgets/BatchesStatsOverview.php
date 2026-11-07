<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Widgets\Concerns\InteractsWithUsageStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * CV batches. A batch is one recruiter upload; the AI requests inside it are
 * the per-CV parse/match calls, across the live and provider-batch lanes.
 */
class BatchesStatsOverview extends StatsOverviewWidget
{
    use InteractsWithUsageStats;

    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    public function getHeading(): ?string
    {
        return 'Batches'.$this->periodSuffix();
    }

    protected function getDescription(): ?string
    {
        return 'Recruiter CV uploads and the AI calls they generated.';
    }

    protected function getStats(): array
    {
        $stats = $this->stats();
        $batches = $stats->batches();
        $requests = $batches['requestsSuccess'] + $batches['requestsFailed'];

        return [
            Stat::make('Batches run', number_format($batches['total']))
                ->description($batches['providerBatches'] > 0
                    ? number_format($batches['providerBatches']).' provider batches submitted'
                    : 'Uploads started in this period')
                ->descriptionIcon('heroicon-m-inbox-stack')
                ->color('info'),

            Stat::make('Completed successfully', number_format($batches['completed']))
                ->description(trim(implode(', ', array_filter([
                    $batches['partial'] > 0 ? number_format($batches['partial']).' partial' : null,
                    $batches['failed'] > 0 ? number_format($batches['failed']).' failed' : null,
                ]))) ?: 'No partial or failed batches')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('AI requests in batches', number_format($requests))
                ->description('Success: '.number_format($batches['requestsSuccess']).', Failed: '.number_format($batches['requestsFailed']))
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color($batches['requestsFailed'] > 0 ? 'warning' : 'success'),

            Stat::make('Tokens used', number_format($batches['tokens']['total']))
                ->description($stats->formatTokens($batches['tokens']))
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('primary'),
        ];
    }
}
