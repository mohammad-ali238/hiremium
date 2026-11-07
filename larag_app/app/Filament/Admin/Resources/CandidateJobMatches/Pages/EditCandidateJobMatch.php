<?php

namespace App\Filament\Admin\Resources\CandidateJobMatches\Pages;

use App\Filament\Admin\Resources\CandidateJobMatches\CandidateJobMatchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCandidateJobMatch extends EditRecord
{
    protected static string $resource = CandidateJobMatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
