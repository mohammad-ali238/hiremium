<?php

namespace App\Filament\Admin\Resources\ResumeBatches\Pages;

use App\Filament\Admin\Resources\ResumeBatches\ResumeBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResumeBatches extends ListRecords
{
    protected static string $resource = ResumeBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
