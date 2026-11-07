<?php

namespace App\Filament\Admin\Resources\Workspaces\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WorkspaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('owner_id')
                    ->relationship('owner', 'name'),
            ]);
    }
}
