<?php

namespace App\Filament\Admin\Resources\CreditTransactions\Pages;

use App\Filament\Admin\Resources\CreditTransactions\CreditTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCreditTransaction extends CreateRecord
{
    protected static string $resource = CreditTransactionResource::class;
}
