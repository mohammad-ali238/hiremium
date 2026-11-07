<?php

namespace App\Filament\Admin\Resources\TopUpPacks\Pages;

use App\Filament\Admin\Resources\TopUpPacks\TopUpPackResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTopUpPack extends EditRecord
{
    protected static string $resource = TopUpPackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
