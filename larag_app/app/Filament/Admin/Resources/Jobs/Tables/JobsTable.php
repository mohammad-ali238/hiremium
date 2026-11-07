<?php

namespace App\Filament\Admin\Resources\Jobs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class JobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workspace.name')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('employment_type')
                    ->searchable(),
                TextColumn::make('location')
                    ->searchable(),
                TextColumn::make('location_type')
                    ->searchable(),
                TextColumn::make('min_experience_years')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('education')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('deleted_at')
                    ->label('Deleted')
                    ->state(fn ($record) => $record->trashed() ? 'Deleted by user' : null)
                    ->badge()
                    ->color('danger')
                    ->placeholder('—')
                    ->tooltip(fn ($record) => $record->trashed() ? 'Removed from the user dashboard on '.$record->deleted_at->format('M j, Y H:i') : null),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()->label('Deleted by user'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
