<?php

namespace App\Filament\Admin\Widgets;

use App\Services\Admin\AdminUsageStats;

/** Per-tool opened / processed counts and the tokens each tool burned. */
class ToolsBreakdownTable extends BreakdownTableWidget
{
    protected static ?int $sort = 5;

    protected function heading(): string
    {
        return 'Per tool'.$this->periodSuffix();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'label', 'label' => 'Tool'],
            ['key' => 'opened', 'label' => 'Opened', 'align' => 'right'],
            ['key' => 'processed', 'label' => 'AI processed', 'align' => 'right'],
            ['key' => 'openai', 'label' => 'OpenAI tokens', 'align' => 'right'],
            ['key' => 'gemini', 'label' => 'Gemini tokens', 'align' => 'right'],
            ['key' => 'total', 'label' => 'Total tokens', 'align' => 'right'],
        ];
    }

    protected function rows(): array
    {
        return array_map(fn (array $tool): array => [
            'label' => $tool['label'],
            'opened' => number_format($tool['opened']),
            'processed' => number_format($tool['processed']),
            'openai' => number_format($tool['tokens'][AdminUsageStats::PROVIDER_OPENAI]),
            'gemini' => number_format($tool['tokens'][AdminUsageStats::PROVIDER_GEMINI]),
            'total' => number_format($tool['tokens']['total']),
        ], $this->stats()->tools()['perTool']);
    }

    protected function emptyMessage(): string
    {
        return 'No tool runs were opened or processed in this period.';
    }
}
