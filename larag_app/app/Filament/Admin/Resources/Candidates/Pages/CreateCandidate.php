<?php

namespace App\Filament\Admin\Resources\Candidates\Pages;

use App\Filament\Admin\Resources\Candidates\CandidateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCandidate extends CreateRecord
{
    protected static string $resource = CandidateResource::class;
}
