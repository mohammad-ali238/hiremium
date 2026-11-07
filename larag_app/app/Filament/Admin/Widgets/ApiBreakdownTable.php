<?php

namespace App\Filament\Admin\Widgets;

/** Per-endpoint API counts, split by sync/async lane and outcome. */
class ApiBreakdownTable extends BreakdownTableWidget
{
    protected static ?int $sort = 9;

    protected function heading(): string
    {
        return 'Per endpoint'.$this->periodSuffix();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'endpoint', 'label' => 'Endpoint'],
            ['key' => 'lane', 'label' => 'Mode'],
            ['key' => 'success', 'label' => 'Success', 'align' => 'right'],
            ['key' => 'failed', 'label' => 'Failed', 'align' => 'right'],
            ['key' => 'total', 'label' => 'Total', 'align' => 'right'],
        ];
    }

    protected function rows(): array
    {
        return $this->formatEndpointRows($this->stats()->api()['perEndpoint']);
    }

    /** @param  array<int, array<string, mixed>>  $endpoints */
    protected function formatEndpointRows(array $endpoints): array
    {
        return array_map(fn (array $row): array => [
            'endpoint' => $row['endpoint'],
            'lane' => ucfirst($row['lane']),
            'success' => number_format($row['success']),
            'failed' => number_format($row['failed']),
            'total' => number_format($row['total']),
        ], $endpoints);
    }

    protected function emptyMessage(): string
    {
        return 'No API requests in this period.';
    }
}
