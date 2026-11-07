<?php

namespace App\Filament\Admin\Resources\CreditTransactions\Pages;

use App\Filament\Admin\Resources\CreditTransactions\CreditTransactionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCreditTransactions extends ListRecords
{
    protected static string $resource = CreditTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
