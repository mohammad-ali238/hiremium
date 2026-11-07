<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Widgets\Concerns\InteractsWithUsageStats;
use Filament\Widgets\ChartWidget;

/** Where the AI requests in this window came from. */
class RequestsBySourceChart extends ChartWidget
{
    use InteractsWithUsageStats;

    protected static ?int $sort = 3;

    protected ?string $maxHeight = '260px';

    public function getHeading(): ?string
    {
        return 'AI requests by source'.$this->periodSuffix();
    }

    public function getDescription(): ?string
    {
        return 'Share of all AI calls across the four ledgers.';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $sources = $this->stats()->requestsBySource();

        return [
            'datasets' => [
                [
                    'label' => 'AI requests',
                    'data' => array_values($sources),
                    'backgroundColor' => ['#f59e0b', '#8b5cf6', '#3b82f6', '#10b981'],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => array_keys($sources),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'bottom'],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
