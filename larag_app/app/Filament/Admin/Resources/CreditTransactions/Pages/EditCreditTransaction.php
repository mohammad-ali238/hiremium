<?php

namespace App\Filament\Admin\Resources\CreditTransactions\Pages;

use App\Filament\Admin\Resources\CreditTransactions\CreditTransactionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCreditTransaction extends EditRecord
{
    protected static string $resource = CreditTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
