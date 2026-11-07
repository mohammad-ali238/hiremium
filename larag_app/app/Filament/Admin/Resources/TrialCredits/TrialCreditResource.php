<?php

namespace App\Filament\Admin\Resources\TrialCredits;

use App\Filament\Admin\Resources\TrialCredits\Pages\CreateTrialCredit;
use App\Filament\Admin\Resources\TrialCredits\Pages\EditTrialCredit;
use App\Filament\Admin\Resources\TrialCredits\Pages\ListTrialCredits;
use App\Filament\Admin\Resources\TrialCredits\Schemas\TrialCreditForm;
use App\Filament\Admin\Resources\TrialCredits\Tables\TrialCreditsTable;
use App\Models\TrialCredit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TrialCreditResource extends Resource
{
    protected static ?string $model = TrialCredit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Free Trials';

    protected static ?string $navigationLabel = 'Free Credit Entries';

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        return TrialCreditForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrialCreditsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrialCredits::route('/'),
            'create' => CreateTrialCredit::route('/create'),
            'edit' => EditTrialCredit::route('/{record}/edit'),
        ];
    }
}
