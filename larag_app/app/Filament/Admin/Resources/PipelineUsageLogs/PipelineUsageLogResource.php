<?php

namespace App\Filament\Admin\Resources\PipelineUsageLogs;

use App\Filament\Admin\Resources\PipelineUsageLogs\Pages\ListPipelineUsageLogs;
use App\Filament\Admin\Resources\PipelineUsageLogs\Tables\PipelineUsageLogsTable;
use App\Models\PipelineUsageLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PipelineUsageLogResource extends Resource
{
    protected static ?string $model = PipelineUsageLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|UnitEnum|null $navigationGroup = 'Recruitment Management';

    protected static ?string $navigationParentItem = 'Workspaces';

    protected static ?int $navigationSort = 60;

    protected static ?string $modelLabel = 'pipeline usage log';

    public static function table(Table $table): Table
    {
        return PipelineUsageLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPipelineUsageLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
