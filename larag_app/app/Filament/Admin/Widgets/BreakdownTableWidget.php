<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Widgets\Concerns\InteractsWithUsageStats;
use Filament\Widgets\Widget;

/**
 * Base for the per-row breakdown tables under each section's stat row.
 * Subclasses describe their columns and hand back already-formatted rows; the
 * shared view carries its own styles because the panel has no custom theme
 * build to compile utility classes into.
 */
abstract class BreakdownTableWidget extends Widget
{
    use InteractsWithUsageStats;

    protected string $view = 'filament.admin.widgets.breakdown-table';

    protected int | string | array $columnSpan = 'full';

    /** @return array<int, array{key: string, label: string, align?: string}> */
    abstract protected function columns(): array;

    /** @return array<int, array<string, mixed>> */
    abstract protected function rows(): array;

    abstract protected function heading(): string;

    protected function emptyMessage(): string
    {
        return 'Nothing recorded in this period.';
    }

    protected function getViewData(): array
    {
        return [
            'heading' => $this->heading(),
            'columns' => $this->columns(),
            'rows' => $this->rows(),
            'emptyMessage' => $this->emptyMessage(),
        ];
    }
}
