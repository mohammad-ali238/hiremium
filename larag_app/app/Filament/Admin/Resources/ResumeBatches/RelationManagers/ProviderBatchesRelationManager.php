<?php

namespace App\Filament\Admin\Resources\ResumeBatches\RelationManagers;

use App\Models\ProviderBatch;
use App\Services\Pipeline\ProviderBatchIngestor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

/**
 * Admin view of the async provider Batch API submissions behind a resume
 * batch: which items went through a batch request, the provider ids/URLs to
 * check them, and a "Check status now" action that polls + ingests on demand.
 */
class ProviderBatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'providerBatches';

    protected static ?string $title = 'Provider batch requests';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Provider batch requests (async Batch API)')
            ->description('Overflow CVs submitted to the provider\'s Batch API. Everything not listed here ran on the live queue lane.')
            ->columns([
                TextColumn::make('provider')
                    ->badge()
                    ->color('info'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ProviderBatch::STATUS_COMPLETED => 'success',
                        ProviderBatch::STATUS_FAILED, ProviderBatch::STATUS_CANCELLED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('request_count')
                    ->label('Items via batch')
                    ->numeric(),
                TextColumn::make('completed_count')
                    ->label('Completed')
                    ->numeric(),
                TextColumn::make('failed_count')
                    ->label('Failed')
                    ->numeric(),
                TextColumn::make('provider_batch_id')
                    ->label('Provider batch ID')
                    ->copyable()
                    ->limit(30)
                    ->tooltip(fn (ProviderBatch $record): ?string => $record->provider_batch_id),
                TextColumn::make('check_url')
                    ->label('Check URL')
                    ->state(fn (ProviderBatch $record): ?string => $record->checkUrl())
                    ->copyable()
                    ->copyableState(fn (ProviderBatch $record): ?string => $record->checkUrl())
                    ->url(fn (ProviderBatch $record): ?string => $record->provider === 'openai' ? $record->checkUrl() : null, shouldOpenInNewTab: true)
                    ->limit(40)
                    ->tooltip(fn (ProviderBatch $record): ?string => $record->provider === 'gemini'
                        ? 'REST endpoint — needs the x-goog-api-key header (use "Check status now" instead)'
                        : 'Opens the OpenAI console'),
                TextColumn::make('error')
                    ->limit(40)
                    ->tooltip(fn (ProviderBatch $record): ?string => $record->error)
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label('Last checked')
                    ->since(),
            ])
            ->recordActions([
                Action::make('checkStatus')
                    ->label('Check status now')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (ProviderBatch $record): void {
                        try {
                            app(ProviderBatchIngestor::class)->process($record);
                            $record->refresh();

                            Notification::make()
                                ->title("Provider batch is {$record->status}")
                                ->body("{$record->completed_count} completed / {$record->failed_count} failed of {$record->request_count} submitted.")
                                ->status($record->status === ProviderBatch::STATUS_COMPLETED ? 'success' : ($record->isPending() ? 'info' : 'danger'))
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Status check failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('id', 'desc');
    }

    public function isReadOnly(): bool
    {
        return false; // enables the record action while keeping no create/edit forms
    }
}
