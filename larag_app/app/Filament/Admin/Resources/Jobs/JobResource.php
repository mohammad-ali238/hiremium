<?php

namespace App\Filament\Admin\Resources\Jobs;

use App\Filament\Admin\Resources\Jobs\Pages\CreateJob;
use App\Filament\Admin\Resources\Jobs\Pages\EditJob;
use App\Filament\Admin\Resources\Jobs\Pages\ListJobs;
use App\Filament\Admin\Resources\Jobs\Schemas\JobForm;
use App\Filament\Admin\Resources\Jobs\Tables\JobsTable;
use App\Models\Job;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class JobResource extends Resource
{
    protected static ?string $model = Job::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Recruitment Management';

    protected static ?string $navigationParentItem = 'Workspaces';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return JobForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
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
            'index' => ListJobs::route('/'),
            'create' => CreateJob::route('/create'),
            'edit' => EditJob::route('/{record}/edit'),
        ];
    }
}
