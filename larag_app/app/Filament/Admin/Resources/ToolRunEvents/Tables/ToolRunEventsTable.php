<?php

namespace App\Filament\Admin\Resources\ToolRunEvents\Tables;

use App\Filament\Admin\Resources\ToolRuns\ToolRunFileActions;
use App\Models\ToolRunEvent;
use App\Services\Tools\ToolRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ToolRunEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('aitools_run_id')
                    ->label('Run #')
                    ->sortable(),
                TextColumn::make('tool')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => app(ToolRegistry::class)->find($state)?->name() ?? $state),
                TextColumn::make('stage')->badge()->color('gray'),
                TextColumn::make('attempt')
                    ->numeric()
                    // A first attempt is unremarkable; a third is the story.
                    ->color(fn (int $state): string => $state > 1 ? 'warning' : 'gray'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ToolRunEvent::STATUS_SUCCESS => 'success',
                        ToolRunEvent::STATUS_FAILED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('provider')->placeholder('—'),
                TextColumn::make('model')->placeholder('—')->toggleable(),
                IconColumn::make('provider_inherited')
                    ->label('Inherited')
                    ->boolean()
                    ->tooltip('On: the stage used the global AI Settings. Off: a per-tool override.')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tokens_total')
                    ->label('Tokens')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—')
                    ->summarize(Sum::make()->label('Total')),
                TextColumn::make('duration_ms')
                    ->label('ms')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(40)
                    ->tooltip(fn (ToolRunEvent $record): ?string => $record->error_message)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('tool')
                    ->options(fn (): array => app(ToolRegistry::class)->options()),
                SelectFilter::make('stage')->options(fn (): array => ToolRunEvent::query()
                    ->distinct()
                    ->orderBy('stage')
                    ->pluck('stage', 'stage')
                    ->all()),
                SelectFilter::make('status')->options([
                    ToolRunEvent::STATUS_SUCCESS => 'Success',
                    ToolRunEvent::STATUS_FAILED => 'Failed',
                    ToolRunEvent::STATUS_SKIPPED => 'Skipped',
                ]),
                SelectFilter::make('provider')->options(fn (): array => ToolRunEvent::query()
                    ->whereNotNull('provider')
                    ->distinct()
                    ->orderBy('provider')
                    ->pluck('provider', 'provider')
                    ->all()),
                Filter::make('logged')
                    ->schema([
                        DatePicker::make('from')->label('Logged from'),
                        DatePicker::make('until')->label('Logged until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
                Filter::make('failed_only')
                    ->label('Failed only')
                    ->query(fn (Builder $query): Builder => $query->where('status', ToolRunEvent::STATUS_FAILED))
                    ->toggle(),
            ])
            ->recordActions([
                Action::make('payload')
                    ->label('Payload')
                    ->icon('heroicon-o-code-bracket')
                    ->modalHeading(fn (ToolRunEvent $record): string => 'Event #'.$record->id.' · '.$record->stage)
                    ->modalWidth('4xl')
                    ->modalContent(fn (ToolRunEvent $record) => view('filament.admin.tool-event-payload', ['event' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                ToolRunFileActions::forEvent(),
            ])
            ->defaultSort('id', 'desc');
    }
}
