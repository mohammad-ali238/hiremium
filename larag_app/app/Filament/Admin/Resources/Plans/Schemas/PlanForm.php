<?php

namespace App\Filament\Admin\Resources\Plans\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                Toggle::make('is_active')->default(true),
                Toggle::make('is_public')
                    ->label('Show in public pricing / upgrade lists')
                    ->default(true)
                    ->helperText('OFF = internal plan: hidden from the pricing page and all user-facing lists, and users cannot check out with it. Only an admin can assign it to a user (Subscriptions). Existing subscribers keep working normally.'),
                TextInput::make('duration_days')
                    ->label('Monthly period (days)')
                    ->required()->numeric()->minValue(1)->default(30),

                Section::make('Monthly billing')
                    ->columns(3)
                    ->schema([
                        TextInput::make('credit_limit')
                            ->label('Credits / month')
                            ->required()->numeric()->minValue(0),
                        TextInput::make('price')
                            ->label('Monthly price')
                            ->required()->numeric()->minValue(0)->prefix('$'),
                        TextInput::make('lemonsqueezy_variant_id')
                            ->label('Monthly variant ID')
                            ->maxLength(255)
                            ->helperText('LemonSqueezy variant for monthly checkout.'),
                    ]),

                Section::make('Yearly billing (optional)')
                    ->description('Leave blank to offer this plan monthly only. Credits default to 12× the monthly amount when left empty.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('yearly_credit_limit')
                            ->label('Credits / year')
                            ->numeric()->minValue(0)
                            ->placeholder('12× monthly'),
                        TextInput::make('yearly_price')
                            ->label('Yearly price')
                            ->numeric()->minValue(0)->prefix('$')
                            ->helperText('Set below 12× the monthly price to show a savings badge.'),
                        TextInput::make('lemonsqueezy_yearly_variant_id')
                            ->label('Yearly variant ID')
                            ->maxLength(255)
                            ->helperText('LemonSqueezy variant for yearly checkout.'),
                    ]),

                Section::make('Plan limits')
                    ->columns(4)
                    ->schema([
                        TextInput::make('max_jobs')
                            ->label('Max jobs')->required()->numeric()->minValue(0)->default(3),
                        TextInput::make('max_seats')
                            ->label('Max team seats')->required()->numeric()->minValue(1)->default(1),
                        TextInput::make('max_files_per_batch')
                            ->label('CVs per upload')->required()->numeric()->minValue(1)->default(25),
                        TextInput::make('max_file_mb')
                            ->label('Max file size (MB)')->required()->numeric()->minValue(1)->default(10),
                    ]),

                Textarea::make('description')->rows(3)->columnSpanFull(),
            ]);
    }
}
