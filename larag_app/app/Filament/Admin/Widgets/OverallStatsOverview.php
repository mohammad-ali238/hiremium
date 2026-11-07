<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Widgets\Concerns\InteractsWithUsageStats;
use App\Services\Admin\AdminUsageStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The headline row: every AI request and every token spent in the window,
 * across Free Tools, the CV batch pipeline, the paid API and free credits.
 */
class OverallStatsOverview extends StatsOverviewWidget
{
    use InteractsWithUsageStats;

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    public function getHeading(): ?string
    {
        return 'Overall'.$this->periodSuffix();
    }

    protected function getStats(): array
    {
        $stats = $this->stats();
        $tokens = $stats->overallTokens();
        $sources = $stats->requestsBySource();

        return [
            Stat::make('Total AI requests', number_format(array_sum($sources)))
                ->description('Tools, batches, API and free credits')
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color('primary'),

            Stat::make('Total tokens used', number_format($tokens['total']))
                ->description($stats->formatTokens($tokens))
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('primary'),

            Stat::make('OpenAI tokens', number_format($tokens[AdminUsageStats::PROVIDER_OPENAI]))
                ->description($this->share($tokens[AdminUsageStats::PROVIDER_OPENAI], $tokens['total']))
                ->color('success'),

            Stat::make('Gemini tokens', number_format($tokens[AdminUsageStats::PROVIDER_GEMINI]))
                ->description($this->share($tokens[AdminUsageStats::PROVIDER_GEMINI], $tokens['total']))
                ->color('info'),
        ];
    }

    private function share(int $value, int $total): string
    {
        if ($total === 0) {
            return 'No usage in this period';
        }

        return round($value / $total * 100, 1).'% of all tokens';
    }
}
