<?php

namespace App\Filament\Admin\Resources\UsageLogs;

use App\Filament\Admin\Resources\UsageLogs\Pages\ListUsageLogs;
use App\Filament\Admin\Resources\UsageLogs\Tables\UsageLogsTable;
use App\Models\UsageLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UsageLogResource extends Resource
{
    protected static ?string $model = UsageLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 70;

    protected static ?string $recordTitleAttribute = 'endpoint';

    public static function table(Table $table): Table
    {
        return UsageLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsageLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
