<?php

namespace App\Filament\Admin\Resources\ResumeBatches\Tables;

use App\Models\ResumeBatch;
use App\Services\Pipeline\BatchPurger;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ResumeBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workspace.name')
                    ->searchable(),
                TextColumn::make('job.title')
                    ->label('Job / Batch')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state, ResumeBatch $record): string => ($state ?: 'Job').' — Batch #'.$record->id),
                TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('total_files')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('parsed_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('analyzed_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('failed_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('provider_batches_sum_request_count')
                    ->label('Via batch API')
                    ->sum('providerBatches', 'request_count')
                    ->placeholder('0')
                    ->badge()
                    ->color('info'),
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
            ->defaultSort('id', 'desc')
            ->filters([
                TrashedFilter::make()->label('Deleted by user'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('purgeBatch')
                    ->label('Delete & files')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (): bool => ! app()->isProduction())
                    ->requiresConfirmation()
                    ->modalHeading('Delete this batch and everything it produced?')
                    ->modalDescription('Removes stored CV files from disk plus all related files, matches, provider requests, usage logs and orphan candidates. Cannot be undone.')
                    ->action(function (ResumeBatch $record): void {
                        $r = app(BatchPurger::class)->purge($record);
                        Notification::make()->title('Batch deleted')
                            ->body("{$r['files_deleted']} file(s) removed, {$r['matches_deleted']} match(es), {$r['candidates_deleted']} orphan candidate(s).")
                            ->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('purgeBatches')
                        ->label('Delete selected & files')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn (): bool => ! app()->isProduction())
                        ->requiresConfirmation()
                        ->modalHeading('Delete the selected batches and everything they produced?')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $files = 0;
                            foreach ($records as $batch) {
                                $files += app(BatchPurger::class)->purge($batch)['files_deleted'];
                            }
                            Notification::make()->title("{$records->count()} batch(es) deleted")
                                ->body("{$files} file(s) removed from disk.")->success()->send();
                        }),
                ]),
            ]);
    }
}
