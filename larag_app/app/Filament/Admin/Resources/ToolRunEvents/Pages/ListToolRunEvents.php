<?php

namespace App\Filament\Admin\Resources\ToolRunEvents\Pages;

use App\Filament\Admin\Resources\ToolRunEvents\ToolRunEventResource;
use Filament\Resources\Pages\ListRecords;

class ListToolRunEvents extends ListRecords
{
    protected static string $resource = ToolRunEventResource::class;
}
