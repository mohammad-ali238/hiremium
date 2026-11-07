<?php

namespace App\Filament\Admin\Resources\ToolRuns;

use App\Filament\Admin\Resources\ToolRuns\Pages\ListToolRuns;
use App\Filament\Admin\Resources\ToolRuns\Tables\ToolRunsTable;
use App\Models\ToolRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Every public free-tool submission: who asked, what they asked, what the AI
 * answered and what it cost. Read-only — these rows are a record of something
 * that already happened, not something an admin authors.
 */
class ToolRunResource extends Resource
{
    protected static ?string $model = ToolRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Free Tools';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'tool run';

    protected static ?string $navigationLabel = 'Tool Runs';

    public static function table(Table $table): Table
    {
        return ToolRunsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListToolRuns::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
