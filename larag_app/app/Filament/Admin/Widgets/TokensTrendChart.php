<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Widgets\Concerns\InteractsWithUsageStats;
use Filament\Widgets\ChartWidget;

/**
 * Tokens per provider over the selected window, bucketed by hour for a single
 * day, by day for the week/month ranges and by month for anything longer.
 */
class TokensTrendChart extends ChartWidget
{
    use InteractsWithUsageStats;

    protected static ?int $sort = 2;

    protected ?string $maxHeight = '260px';

    public function getHeading(): ?string
    {
        return 'Tokens used'.$this->periodSuffix();
    }

    public function getDescription(): ?string
    {
        return 'Every AI call, whichever ledger booked it.';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $trend = $this->stats()->tokenTrend();

        return [
            'datasets' => [
                [
                    'label' => 'OpenAI',
                    'data' => $trend['openai'],
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Gemini',
                    'data' => $trend['gemini'],
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $trend['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'bottom'],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
