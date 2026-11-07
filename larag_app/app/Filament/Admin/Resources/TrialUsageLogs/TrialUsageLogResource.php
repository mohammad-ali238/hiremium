<?php

namespace App\Filament\Admin\Resources\TrialUsageLogs;

use App\Filament\Admin\Resources\TrialUsageLogs\Pages\ListTrialUsageLogs;
use App\Filament\Admin\Resources\TrialUsageLogs\Tables\TrialUsageLogsTable;
use App\Models\TrialUsageLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TrialUsageLogResource extends Resource
{
    protected static ?string $model = TrialUsageLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Free Trials';

    protected static ?string $navigationLabel = 'Free Credit Usage Logs';

    protected static ?string $recordTitleAttribute = 'endpoint';

    public static function table(Table $table): Table
    {
        return TrialUsageLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrialUsageLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
