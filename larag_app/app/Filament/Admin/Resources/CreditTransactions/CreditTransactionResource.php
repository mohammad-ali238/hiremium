<?php

namespace App\Filament\Admin\Resources\CreditTransactions;

use App\Filament\Admin\Resources\CreditTransactions\Pages\CreateCreditTransaction;
use App\Filament\Admin\Resources\CreditTransactions\Pages\EditCreditTransaction;
use App\Filament\Admin\Resources\CreditTransactions\Pages\ListCreditTransactions;
use App\Filament\Admin\Resources\CreditTransactions\Schemas\CreditTransactionForm;
use App\Filament\Admin\Resources\CreditTransactions\Tables\CreditTransactionsTable;
use App\Models\CreditTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CreditTransactionResource extends Resource
{
    protected static ?string $model = CreditTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Recruitment Management';

    protected static ?string $navigationParentItem = 'Workspaces';

    protected static ?int $navigationSort = 80;

    public static function form(Schema $schema): Schema
    {
        return CreditTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CreditTransactionsTable::configure($table);
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
            'index' => ListCreditTransactions::route('/'),
            'create' => CreateCreditTransaction::route('/create'),
            'edit' => EditCreditTransaction::route('/{record}/edit'),
        ];
    }
}
