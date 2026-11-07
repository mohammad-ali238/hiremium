<?php

namespace App\Filament\Admin\Resources\CandidateJobMatches\Pages;

use App\Filament\Admin\Resources\CandidateJobMatches\CandidateJobMatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCandidateJobMatches extends ListRecords
{
    protected static string $resource = CandidateJobMatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
