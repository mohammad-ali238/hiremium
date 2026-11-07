<?php

namespace App\Filament\Admin\Resources\UsageLogs\Pages;

use App\Filament\Admin\Resources\UsageLogs\UsageLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsageLogs extends ListRecords
{
    protected static string $resource = UsageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
