<?php

namespace App\Filament\Admin\Pages;

use App\Support\Stats\StatsPeriod;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Replaces Filament's stock dashboard with the usage overview. The duration
 * select at the top drives every widget on the page through
 * InteractsWithPageFilters; it defaults to today.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Usage overview';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('period')
                            ->label('Duration')
                            ->options(StatsPeriod::options())
                            ->default(StatsPeriod::Today->value)
                            ->selectablePlaceholder(false)
                            ->native(false),
                    ])
                    ->columns(1),
            ]);
    }

    public function getColumns(): int | array
    {
        return 2;
    }
}
