<?php

namespace App\Filament\Admin\Resources\PipelineUsageLogs\Pages;

use App\Filament\Admin\Resources\PipelineUsageLogs\PipelineUsageLogResource;
use Filament\Resources\Pages\ListRecords;

class ListPipelineUsageLogs extends ListRecords
{
    protected static string $resource = PipelineUsageLogResource::class;
}
