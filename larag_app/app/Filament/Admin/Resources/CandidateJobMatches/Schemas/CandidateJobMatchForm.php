<?php

namespace App\Filament\Admin\Resources\CandidateJobMatches\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CandidateJobMatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('workspace_id')
                    ->relationship('workspace', 'name')
                    ->required(),
                Select::make('candidate_id')
                    ->relationship('candidate', 'name')
                    ->required(),
                Select::make('job_id')
                    ->relationship('job', 'title')
                    ->required(),
                Select::make('batch_id')
                    ->relationship('batch', 'id'),
                Select::make('resume_file_id')
                    ->relationship('resumeFile', 'id'),
                TextInput::make('score')
                    ->label('Score (rubric)')
                    ->numeric(),
                TextInput::make('ai_score')
                    ->label('AI score (admin only)')
                    ->helperText('What the model scored this candidate on its own. Stored for review; never shown to the recruiter.')
                    ->numeric(),
                TextInput::make('category'),
                Textarea::make('reason')
                    ->columnSpanFull(),
                TextInput::make('matched_skills'),
                TextInput::make('missing_skills'),
                TextInput::make('sub_scores'),
                Textarea::make('score_breakdown')
                    ->label('Score breakdown')
                    ->helperText('Per-dimension quota, percentage and points, the career-gap verdict and any cap that was applied.')
                    ->rows(6)
                    ->columnSpanFull()
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                    ->dehydrated(false)
                    ->disabled(),
                TextInput::make('status')
                    ->required()
                    ->default('new'),
                TextInput::make('rejection_reason'),
                TextInput::make('processing_status')
                    ->required()
                    ->default('pending'),
                TextInput::make('credits_used')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
