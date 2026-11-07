<?php

namespace App\Filament\Admin\Resources\CandidateJobMatches\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CandidateJobMatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workspace.name')
                    ->searchable(),
                TextColumn::make('candidate.name')
                    ->searchable(),
                TextColumn::make('job.title')
                    ->searchable(),
                TextColumn::make('batch.id')
                    ->searchable(),
                TextColumn::make('resumeFile.id')
                    ->searchable(),
                TextColumn::make('score')
                    ->label('Score')
                    ->description('rubric')
                    ->numeric()
                    ->sortable(),
                // The model's own score. Admin-only: recruiters see the rubric
                // score above, which is the one the whole app ranks on.
                TextColumn::make('ai_score')
                    ->label('AI score')
                    ->placeholder('—')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('category')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('rejection_reason')
                    ->searchable(),
                TextColumn::make('processing_status')
                    ->searchable(),
                TextColumn::make('credits_used')
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
