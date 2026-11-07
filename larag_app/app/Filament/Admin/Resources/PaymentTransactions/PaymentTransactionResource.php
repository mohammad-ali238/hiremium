<?php

namespace App\Filament\Admin\Resources\PaymentTransactions;

use App\Filament\Admin\Resources\PaymentTransactions\Pages\ListPaymentTransactions;
use App\Filament\Admin\Resources\PaymentTransactions\Tables\PaymentTransactionsTable;
use App\Models\PaymentTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PaymentTransactionResource extends Resource
{
    protected static ?string $model = PaymentTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $recordTitleAttribute = 'event_name';

    public static function table(Table $table): Table
    {
        return PaymentTransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentTransactions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
