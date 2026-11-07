<?php

namespace App\Filament\Admin\Resources\Candidates\Schemas;

use App\Models\Candidate;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

class CandidateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('workspace_id')
                    ->relationship('workspace', 'name')
                    ->required(),
                TextInput::make('name'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('job_title'),
                TextInput::make('location'),
                Textarea::make('summary')
                    ->columnSpanFull(),
                // `skills` is an array cast: a TextInput renders it as
                // "[object Object]" and would save a string over the array.
                TagsInput::make('skills')
                    ->placeholder('Add a skill')
                    ->columnSpanFull(),
                TextInput::make('experience_years')
                    ->numeric(),
                TextInput::make('primary_resume_file_id')
                    ->numeric(),

                // Everything the CV parser returned. Read-only and behind a
                // modal: it is deeply nested, so no form control can show it.
                Section::make('Parsed CV data')
                    ->description(fn (?Candidate $record): string => filled($record?->parsed_data)
                        ? 'What the parser extracted from this candidate\'s CV.'
                        : 'Nothing parsed for this candidate yet.')
                    ->columnSpanFull()
                    ->schema([
                        Actions::make([
                            Action::make('view_parsed_data')
                                ->label('View parsed data')
                                ->icon('heroicon-o-document-magnifying-glass')
                                ->color('gray')
                                ->disabled(fn (?Candidate $record): bool => blank($record?->parsed_data))
                                ->modalHeading('Parsed CV data')
                                ->modalDescription(fn (?Candidate $record): ?string => $record?->name)
                                ->modalWidth(Width::FourExtraLarge)
                                ->modalSubmitAction(false)
                                ->modalCancelActionLabel('Close')
                                ->infolist(fn (?Candidate $record): array => ParsedDataInfolist::for($record?->parsed_data)),
                        ]),
                    ]),
            ]);
    }
}
