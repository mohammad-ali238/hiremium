<?php

namespace App\Filament\Admin\Resources\ToolRuns\Tables;

use App\Filament\Admin\Resources\ToolRuns\ToolRunFileActions;
use App\Jobs\Tools\RunToolStagesJob;
use App\Models\ToolRun;
use App\Services\ResumeParsing\ParseConfidence;
use App\Services\Tools\ToolPurger;
use App\Services\Tools\ToolRegistry;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ToolRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('tool')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => app(ToolRegistry::class)->find($state)?->name() ?? $state)
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->description(fn (ToolRun $record): ?string => $record->quota_email !== $record->email_canonical
                        // The run spent someone else's allowance: same data, new address.
                        ? 'charged to '.$record->quota_email
                        : null),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        ToolRun::STATUS_COMPLETED => 'success',
                        ToolRun::STATUS_FAILED => 'danger',
                        ToolRun::STATUS_PROCESSING => 'info',
                        ToolRun::STATUS_EXPIRED => 'gray',
                        default => 'warning',
                    })
                    ->description(fn (ToolRun $record): ?string => $record->status === ToolRun::STATUS_FAILED
                        ? $record->failed_stage.' · '.$record->failure_code
                        : null),
                TextColumn::make('score')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—'),
                // Read out of the run's own output: how certain the parse behind
                // this run was. '—' for tools that never parse a resume.
                TextColumn::make('parse_confidence')
                    ->label('Read confidence')
                    ->placeholder('—')
                    ->state(fn (ToolRun $record): ?string => $record->parseConfidence() === null
                        ? null
                        : ParseConfidence::format($record->parseConfidence())),
                TextColumn::make('band')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'excellent' => 'success',
                        'good' => 'info',
                        'fair' => 'warning',
                        'weak' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ai_tokens_total')
                    ->label('Tokens')
                    ->numeric()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')),
                TextColumn::make('ai_calls')
                    ->label('AI calls')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('opened_at')
                    ->label('Opened')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('never opened'),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('view_count')
                    ->label('Views')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('marketing_opt_in')
                    ->label('Opted in')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Always present, even with one tool registered: the whole point
                // of the resource is comparing tools once there are several.
                SelectFilter::make('tool')
                    ->options(fn (): array => app(ToolRegistry::class)->options()),
                SelectFilter::make('status')->options([
                    ToolRun::STATUS_AWAITING_OPEN => 'Awaiting open',
                    ToolRun::STATUS_PROCESSING => 'Processing',
                    ToolRun::STATUS_COMPLETED => 'Completed',
                    ToolRun::STATUS_FAILED => 'Failed',
                    ToolRun::STATUS_EXPIRED => 'Expired',
                ]),
                SelectFilter::make('band')->options([
                    'excellent' => 'Excellent',
                    'good' => 'Good',
                    'fair' => 'Fair',
                    'weak' => 'Weak',
                ]),
                Filter::make('submitted')
                    ->schema([
                        DatePicker::make('from')->label('Submitted from'),
                        DatePicker::make('until')->label('Submitted until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
                TernaryFilter::make('opened')
                    ->label('Link opened')
                    ->nullable()
                    ->attribute('opened_at'),
                TernaryFilter::make('marketing_opt_in')
                    ->label('Marketing opt-in'),
                Filter::make('failed_only')
                    ->label('Failed only')
                    ->query(fn (Builder $query): Builder => $query->where('status', ToolRun::STATUS_FAILED))
                    ->toggle(),
                Filter::make('expired_only')
                    ->label('Expired links')
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q
                        ->where('status', ToolRun::STATUS_EXPIRED)
                        ->orWhere(fn (Builder $inner) => $inner->expiredUnopened())))
                    ->toggle(),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (ToolRun $record): string => 'Run #'.$record->id)
                    ->modalWidth('5xl')
                    ->modalContent(fn (ToolRun $record) => view('filament.admin.tool-run-detail', ['run' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                ToolRunFileActions::forRun(),
                ActionGroup::make([
                    Action::make('open')
                        ->label('Open result link')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn (ToolRun $record): string => $record->publicUrl())
                        ->openUrlInNewTab(),
                    Action::make('rerun')
                        ->label('Re-run')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('Clears this run and processes it again from the first stage. It spends AI tokens.')
                        // Nothing to re-run before the visitor has opened the link.
                        ->visible(fn (ToolRun $record): bool => $record->opened_at !== null)
                        ->action(function (ToolRun $record): void {
                            $record->events()->delete();
                            $record->forceFill([
                                'status' => ToolRun::STATUS_PROCESSING,
                                'output' => null,
                                'progress' => 0,
                                'stage' => null,
                                'attempts' => 0,
                                'ai_calls' => 0,
                                'ai_tokens_total' => 0,
                                'duration_ms' => null,
                                'score' => null,
                                'band' => null,
                                'failed_stage' => null,
                                'failure_code' => null,
                                'failure_message' => null,
                                'completed_at' => null,
                                'processing_started_at' => now(),
                            ])->save();

                            RunToolStagesJob::dispatch($record->id)->onQueue(config('hiremium.queue.name', 'cv'));

                            Notification::make()->title('Re-run queued')->success()->send();
                        }),
                    Action::make('purge')
                        ->label('Delete')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Deletes the run, its AI logs, its day fingerprints and the uploaded file.')
                        ->action(function (ToolRun $record): void {
                            app(ToolPurger::class)->purgeRun($record);

                            Notification::make()->title('Run deleted')->success()->send();
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('purge')
                        ->label('Delete selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Deletes each run with its AI logs, fingerprints and uploaded file.')
                        ->action(function (Collection $records): void {
                            $purger = app(ToolPurger::class);

                            $records->each(fn (ToolRun $run) => $purger->purgeRun($run));

                            Notification::make()->title($records->count().' runs deleted')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}
