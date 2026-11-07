<?php

namespace App\Filament\Admin\Resources\TrialCredits\Pages;

use App\Filament\Admin\Resources\TrialCredits\TrialCreditResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrialCredits extends ListRecords
{
    protected static string $resource = TrialCreditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add entry'),
        ];
    }
}
