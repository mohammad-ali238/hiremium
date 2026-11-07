<?php

namespace App\Filament\Admin\Resources\ToolRunEvents;

use App\Filament\Admin\Resources\ToolRunEvents\Pages\ListToolRunEvents;
use App\Filament\Admin\Resources\ToolRunEvents\Tables\ToolRunEventsTable;
use App\Models\ToolRunEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Every AI call the free tools have made, across all tools — the place to answer
 * "what did the free tools cost us this month, and on which model?".
 *
 * Immutable by design: these rows are written once by the runner and never
 * updated, so there is nothing here to edit.
 */
class ToolRunEventResource extends Resource
{
    protected static ?string $model = ToolRunEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|UnitEnum|null $navigationGroup = 'Free Tools';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'tool usage log';

    protected static ?string $navigationLabel = 'Tool Usage Logs';

    public static function table(Table $table): Table
    {
        return ToolRunEventsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListToolRunEvents::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
