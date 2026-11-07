<?php

namespace App\Filament\Admin\Resources\TrialUsageLogs\Pages;

use App\Filament\Admin\Resources\TrialUsageLogs\TrialUsageLogResource;
use Filament\Resources\Pages\ListRecords;

class ListTrialUsageLogs extends ListRecords
{
    protected static string $resource = TrialUsageLogResource::class;
}
