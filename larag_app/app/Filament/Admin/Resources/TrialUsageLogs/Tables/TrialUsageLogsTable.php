<?php

namespace App\Filament\Admin\Resources\TrialUsageLogs\Tables;

use App\Models\TrialUsageLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TrialUsageLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('created_at')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('trialCredit.email')->label('Email')->searchable()->sortable(),
                TextColumn::make('endpoint')->searchable(),
                TextColumn::make('ai_provider')->label('Provider')->placeholder('—'),
                TextColumn::make('ai_model')->label('Model')->placeholder('—'),
                TextColumn::make('score')->numeric()->placeholder('—'),
                TextColumn::make('tokens_used')->numeric()->placeholder('—'),
                TextColumn::make('credits_consumed')->numeric()->label('Credits'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'refunded' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'success' => 'Success',
                    'refunded' => 'Refunded',
                    'failed' => 'Failed',
                ]),
                SelectFilter::make('endpoint')
                    ->options(fn () => TrialUsageLog::query()->distinct()->pluck('endpoint', 'endpoint')->toArray()),
            ])
            ->defaultSort('id', 'desc');
    }
}
