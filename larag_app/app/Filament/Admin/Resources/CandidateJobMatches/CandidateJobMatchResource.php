<?php

namespace App\Filament\Admin\Resources\CandidateJobMatches;

use App\Filament\Admin\Resources\CandidateJobMatches\Pages\CreateCandidateJobMatch;
use App\Filament\Admin\Resources\CandidateJobMatches\Pages\EditCandidateJobMatch;
use App\Filament\Admin\Resources\CandidateJobMatches\Pages\ListCandidateJobMatches;
use App\Filament\Admin\Resources\CandidateJobMatches\Schemas\CandidateJobMatchForm;
use App\Filament\Admin\Resources\CandidateJobMatches\Tables\CandidateJobMatchesTable;
use App\Models\CandidateJobMatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CandidateJobMatchResource extends Resource
{
    protected static ?string $model = CandidateJobMatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Recruitment Management';

    protected static ?string $navigationParentItem = 'Workspaces';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return CandidateJobMatchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CandidateJobMatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCandidateJobMatches::route('/'),
            'create' => CreateCandidateJobMatch::route('/create'),
            'edit' => EditCandidateJobMatch::route('/{record}/edit'),
        ];
    }
}
