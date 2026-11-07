<?php

namespace App\Filament\Admin\Resources\TopUpPacks\Pages;

use App\Filament\Admin\Resources\TopUpPacks\TopUpPackResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTopUpPacks extends ListRecords
{
    protected static string $resource = TopUpPackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
