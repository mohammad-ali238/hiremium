<?php

namespace App\Filament\Admin\Resources\ResumeBatches\Schemas;

use App\Models\ResumeBatch;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ResumeBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Processing lanes')
                    ->description('How this batch\'s CVs were dispatched: immediately via the queue (live lane) or through the provider\'s async Batch API.')
                    ->columns(3)
                    ->columnSpanFull()
                    ->visible(fn (?ResumeBatch $record): bool => $record !== null)
                    ->schema([
                        TextEntry::make('lane_total')
                            ->label('Total files')
                            ->state(fn (ResumeBatch $record): int => (int) $record->total_files),
                        TextEntry::make('lane_batch_api')
                            ->label('Via batch request (async)')
                            ->state(fn (ResumeBatch $record): int => (int) $record->providerBatches()->sum('request_count'))
                            ->badge()
                            ->color(fn ($state): string => $state > 0 ? 'info' : 'gray'),
                        TextEntry::make('lane_live')
                            ->label('Via live queue')
                            ->state(fn (ResumeBatch $record): int => max(0, (int) $record->total_files - (int) $record->providerBatches()->sum('request_count'))),
                        TextEntry::make('usage_credits')
                            ->label('Credits charged')
                            ->state(fn (ResumeBatch $record): int => (int) $record->usageLogs()->sum('credits_charged'))
                            ->badge()
                            ->color('warning'),
                        TextEntry::make('usage_tokens')
                            ->label('AI tokens used')
                            ->state(fn (ResumeBatch $record): string => number_format((int) $record->usageLogs()->sum('tokens_used'))),
                        TextEntry::make('usage_calls')
                            ->label('AI calls (see usage log below)')
                            ->state(fn (ResumeBatch $record): string => $record->usageLogs()->count().' ('.$record->usageLogs()->where('status', 'failed')->count().' failed)'),
                    ]),
                Select::make('workspace_id')
                    ->relationship('workspace', 'name')
                    ->required(),
                Select::make('job_id')
                    ->relationship('job', 'title')
                    ->required(),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('status')
                    ->required()
                    ->default('uploaded'),
                TextInput::make('total_files')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('parsed_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('analyzed_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('failed_count')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
