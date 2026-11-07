<?php

namespace App\Filament\Admin\Resources\PipelineUsageLogs\Tables;

use App\Filament\Admin\Resources\PipelineUsageLogs\PipelineUsageLogActions;
use App\Models\PipelineUsageLog;
use App\Services\ResumeParsing\ParseConfidence;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PipelineUsageLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('workspace.name')
                    ->searchable(),
                TextColumn::make('resume_batch_id')
                    ->label('Batch #')
                    ->sortable(),
                TextColumn::make('resumeFile.original_name')
                    ->label('File')
                    ->limit(28)
                    ->searchable()
                    ->placeholder('(deleted)'),
                TextColumn::make('operation')
                    ->badge()
                    ->color(fn (string $state): string => $state === PipelineUsageLog::OP_COMBINED ? 'info' : 'gray'),
                TextColumn::make('lane')
                    ->badge()
                    ->color(fn (string $state): string => $state === PipelineUsageLog::LANE_BATCH ? 'info' : 'gray'),
                TextColumn::make('ai_provider')
                    ->label('Provider'),
                TextColumn::make('ai_model')
                    ->label('Model')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tokens_used')
                    ->label('Tokens')
                    ->numeric()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')),
                TextColumn::make('credits_charged')
                    ->label('Credits')
                    ->numeric()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')),
                TextColumn::make('score')
                    ->numeric()
                    ->placeholder('—'),
                // What the parse (or combined) call reported about how well it
                // could read the CV.
                TextColumn::make('confidence')
                    ->label('Confidence')
                    ->sortable()
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state): string => ParseConfidence::format($state)),
                TextColumn::make('duration_ms')
                    ->label('ms')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === PipelineUsageLog::STATUS_SUCCESS ? 'success' : 'danger'),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(30)
                    ->tooltip(fn (PipelineUsageLog $record): ?string => $record->error_message)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('workspace_id')
                    ->label('Workspace')
                    ->relationship('workspace', 'name'),
                // A free-text box rather than a select: batch ids run into the
                // thousands, and you always arrive here already knowing one.
                Filter::make('resume_batch_id')
                    ->label('Batch')
                    ->schema([
                        TextInput::make('resume_batch_id')
                            ->label('Batch #')
                            ->numeric()
                            ->placeholder('e.g. 128'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['resume_batch_id'] ?? null),
                        fn (Builder $q) => $q->where('resume_batch_id', (int) $data['resume_batch_id']),
                    ))
                    ->indicateUsing(fn (array $data): ?string => filled($data['resume_batch_id'] ?? null)
                        ? 'Batch #'.$data['resume_batch_id']
                        : null),
                SelectFilter::make('operation')->options([
                    PipelineUsageLog::OP_PARSE => 'Parse',
                    PipelineUsageLog::OP_MATCH => 'Match',
                    PipelineUsageLog::OP_COMBINED => 'Combined (batch)',
                ]),
                SelectFilter::make('lane')->options([
                    PipelineUsageLog::LANE_LIVE => 'Live',
                    PipelineUsageLog::LANE_BATCH => 'Batch',
                ]),
                SelectFilter::make('status')->options([
                    PipelineUsageLog::STATUS_SUCCESS => 'Success',
                    PipelineUsageLog::STATUS_FAILED => 'Failed',
                ]),
            ])
            ->recordActions([
                PipelineUsageLogActions::view(),
                PipelineUsageLogActions::downloadCv(),
            ])
            ->defaultSort('id', 'desc');
    }
}
