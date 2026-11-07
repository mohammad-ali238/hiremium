<?php

namespace App\Filament\Admin\Resources\Jobs\Schemas;

use App\Support\RichText;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class JobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('workspace_id')
                    ->relationship('workspace', 'name')
                    ->required(),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('title')
                    ->required(),
                // Same restricted rich text as the /app form: small toolbar, no
                // attachments, sanitized on save, 50–8,000 visible characters.
                RichEditor::make('description')
                    ->toolbarButtons([
                        ['bold', 'italic'],
                        ['h2', 'h3'],
                        ['bulletList', 'orderedList', 'blockquote'],
                        ['undo', 'redo'],
                    ])
                    ->fileAttachments(false)
                    ->helperText('Optional. If filled: '.RichText::MIN_CHARS.'–'.number_format(RichText::MAX_CHARS).' characters of visible text. Only bold, italic, headings, lists and quotes are kept — no links.')
                    ->rule(function () {
                        return function (string $attribute, $value, \Closure $fail) {
                            $len = RichText::length($value);
                            if ($len === 0) {
                                return;
                            }
                            if ($len < RichText::MIN_CHARS) {
                                $fail('The description must be at least '.RichText::MIN_CHARS.' characters ('.$len.' so far).');
                            }
                            if ($len > RichText::MAX_CHARS) {
                                $fail('The description may not exceed '.number_format(RichText::MAX_CHARS).' characters.');
                            }
                        };
                    })
                    ->dehydrateStateUsing(fn ($state) => RichText::sanitize($state))
                    ->columnSpanFull(),
                TextInput::make('employment_type'),
                TextInput::make('location'),
                TextInput::make('location_type'),
                TextInput::make('min_experience_years')
                    ->numeric(),
                TextInput::make('education'),
                TextInput::make('must_have_skills'),
                TextInput::make('nice_to_have_skills'),
                TextInput::make('status')
                    ->required()
                    ->default('open'),
            ]);
    }
}
