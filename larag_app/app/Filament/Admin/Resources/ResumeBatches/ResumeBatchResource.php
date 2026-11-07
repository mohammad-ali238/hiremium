<?php

namespace App\Filament\Admin\Resources\ResumeBatches;

use App\Filament\Admin\Resources\ResumeBatches\Pages\CreateResumeBatch;
use App\Filament\Admin\Resources\ResumeBatches\Pages\EditResumeBatch;
use App\Filament\Admin\Resources\ResumeBatches\Pages\ListResumeBatches;
use App\Filament\Admin\Resources\ResumeBatches\RelationManagers\ProviderBatchesRelationManager;
use App\Filament\Admin\Resources\ResumeBatches\RelationManagers\UsageLogsRelationManager;
use App\Filament\Admin\Resources\ResumeBatches\Schemas\ResumeBatchForm;
use App\Filament\Admin\Resources\ResumeBatches\Tables\ResumeBatchesTable;
use App\Models\ResumeBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ResumeBatchResource extends Resource
{
    protected static ?string $model = ResumeBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Recruitment Management';

    protected static ?string $navigationParentItem = 'Workspaces';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return ResumeBatchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResumeBatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProviderBatchesRelationManager::class,
            UsageLogsRelationManager::class,
        ];
    }

    /** Rows a user deleted stay visible to admins, tagged "Deleted by user". */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withTrashed();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResumeBatches::route('/'),
            'create' => CreateResumeBatch::route('/create'),
            'edit' => EditResumeBatch::route('/{record}/edit'),
        ];
    }
}
