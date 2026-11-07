<?php

namespace App\Filament\Admin\Resources\ResumeBatches\Pages;

use App\Filament\Admin\Resources\ResumeBatches\ResumeBatchResource;
use App\Services\Pipeline\BatchPurger;
use App\Services\Pipeline\ProviderBatchIngestor;
use Filament\Actions\Action;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditResumeBatch extends EditRecord
{
    protected static string $resource = ResumeBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('jobSnapshot')
                ->label('Job details (as analyzed)')
                ->icon('heroicon-o-briefcase')
                ->color('gray')
                ->modalHeading(fn () => 'Job as analyzed — Batch #'.$this->getRecord()->id)
                ->modalWidth('3xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn () => view('filament.admin.job-snapshot', [
                    'snapshot' => $this->getRecord()->job_snapshot ?: $this->getRecord()->job?->toSnapshot(),
                ])),
            RestoreAction::make()->label('Restore for user'),
            Action::make('checkProviderBatches')
                ->label('Check batch requests now')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => $this->getRecord()->providerBatches()->exists())
                ->action(function (): void {
                    $checked = 0;
                    $errors = 0;

                    foreach ($this->getRecord()->providerBatches as $pb) {
                        try {
                            app(ProviderBatchIngestor::class)->process($pb);
                            $checked++;
                        } catch (Throwable) {
                            $errors++;
                        }
                    }

                    Notification::make()
                        ->title("Checked {$checked} batch request(s)".($errors ? ", {$errors} failed" : ''))
                        ->status($errors ? 'warning' : 'success')
                        ->send();

                    $this->refreshFormData(['status', 'total_files', 'parsed_count', 'analyzed_count', 'failed_count']);
                }),
            // Test-cleanup: hard-delete the batch + all its files/matches/candidates.
            // Only offered outside production (APP_ENV).
            Action::make('purgeBatch')
                ->label('Delete batch & files')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => ! app()->isProduction())
                ->requiresConfirmation()
                ->modalHeading('Delete this batch and everything it produced?')
                ->modalDescription('Removes the stored CV files from disk, all resume files, matches, provider batch requests, usage logs, and any candidates that exist only because of this batch. This cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete everything')
                ->action(function (): void {
                    $r = app(BatchPurger::class)->purge($this->getRecord());

                    Notification::make()
                        ->title('Batch deleted')
                        ->body("{$r['files_deleted']} file(s) removed from disk, {$r['matches_deleted']} match(es) and {$r['candidates_deleted']} orphan candidate(s) deleted.")
                        ->success()
                        ->send();

                    $this->redirect(ResumeBatchResource::getUrl('index'));
                }),
        ];
    }
}
