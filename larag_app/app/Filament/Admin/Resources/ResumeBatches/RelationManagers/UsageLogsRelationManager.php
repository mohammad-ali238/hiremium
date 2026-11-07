<?php

namespace App\Filament\Admin\Resources\ResumeBatches\RelationManagers;

use App\Filament\Admin\Resources\PipelineUsageLogs\PipelineUsageLogActions;
use App\Models\PipelineUsageLog;
use App\Services\ResumeParsing\ParseConfidence;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Per-file AI usage breakdown of one resume batch: every parse/match/combined
 * call with its lane, provider, tokens, credits and outcome.
 */
class UsageLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'usageLogs';

    protected static ?string $title = 'AI usage log';

    public function table(Table $table): Table
    {
        return $table
            ->heading('AI usage log (per file)')
            ->description('Every AI call this batch made — parse + match per CV on the live lane, one combined call per CV on the batch lane.')
            ->columns([
                TextColumn::make('resume_file_id')
                    ->label('File #'),
                TextColumn::make('resumeFile.original_name')
                    ->label('File')
                    ->limit(28)
                    ->placeholder('(deleted)'),
                TextColumn::make('operation')
                    ->badge()
                    ->color(fn (string $state): string => $state === PipelineUsageLog::OP_COMBINED ? 'info' : 'gray'),
                TextColumn::make('lane')
                    ->badge()
                    ->color(fn (string $state): string => $state === PipelineUsageLog::LANE_BATCH ? 'info' : 'gray'),
                TextColumn::make('ai_provider')
                    ->label('Provider')
                    ->formatStateUsing(fn (PipelineUsageLog $record): string => trim(($record->ai_provider ?? '').' '.($record->ai_model ?? '')))
                    ->placeholder('—'),
                TextColumn::make('tokens_used')
                    ->label('Tokens')
                    ->numeric()
                    ->placeholder('—')
                    ->summarize(Sum::make()->label('Total')),
                TextColumn::make('credits_charged')
                    ->label('Credits')
                    ->numeric()
                    ->summarize(Sum::make()->label('Total')),
                TextColumn::make('score')
                    ->numeric()
                    ->placeholder('—'),
                TextColumn::make('confidence')
                    ->label('Confidence')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state): string => ParseConfidence::format($state)),
                TextColumn::make('duration_ms')
                    ->label('Duration')
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format($state).' ms' : '—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === PipelineUsageLog::STATUS_SUCCESS ? 'success' : 'danger'),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(30)
                    ->tooltip(fn (PipelineUsageLog $record): ?string => $record->error_message)
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
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
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50]);
    }

    public function isReadOnly(): bool
    {
        return false; // record actions stay enabled; no create/edit is exposed
    }
}
