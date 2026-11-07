<?php

namespace App\Filament\Admin\Resources\Candidates\Tables;

use App\Services\ResumeParsing\ParseConfidence;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CandidatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workspace.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('job_title')
                    ->searchable(),
                TextColumn::make('location')
                    ->searchable(),
                TextColumn::make('experience_years')
                    ->numeric()
                    ->sortable(),
                // The parser's own certainty about the CV behind this profile,
                // shown the way an ATS reports it: a percentage.
                TextColumn::make('parse_confidence')
                    ->label('Parse confidence')
                    ->sortable()
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state): string => ParseConfidence::format($state))
                    ->color(fn ($state): string => match (true) {
                        $state === null => 'gray',
                        (float) $state >= 0.90 => 'success',
                        ParseConfidence::isSufficient((float) $state) => 'info',
                        default => 'danger',
                    }),
                TextColumn::make('primary_resume_file_id')
                    ->numeric()
                    ->sortable(),
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
                //
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
