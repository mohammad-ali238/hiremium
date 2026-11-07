<?php

namespace App\Filament\Admin\Resources\TrialCredits\Schemas;

use App\Models\TrialCredit;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TrialCreditForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                Select::make('status')
                    ->options([
                        TrialCredit::STATUS_PENDING => 'Pending',
                        TrialCredit::STATUS_VERIFIED => 'Verified',
                        TrialCredit::STATUS_EXPIRED => 'Expired',
                        TrialCredit::STATUS_REVOKED => 'Revoked',
                    ])
                    ->default(TrialCredit::STATUS_VERIFIED)
                    ->helperText('Manually added entries are auto-verified: an api_key is generated on save.')
                    ->required(),
                TextInput::make('credits_total')
                    ->label('Total credits')
                    ->numeric()
                    ->minValue(0)
                    ->default(fn () => (int) config('trial.credits', TrialCredit::DEFAULT_CREDITS))
                    ->required(),
                TextInput::make('credits_used')
                    ->label('Used credits')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                DateTimePicker::make('expires_at')
                    ->label('Expires at')
                    ->default(fn () => now()->addDays((int) config('trial.days', TrialCredit::DEFAULT_DAYS)))
                    ->helperText('Leave the default for a standard trial window.'),
                TextInput::make('site_url')
                    ->label('Site URL')
                    ->url()
                    ->maxLength(255),
                TextInput::make('api_key')
                    ->label('API key')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Generated automatically on verification')
                    ->visibleOn('edit'),
            ]);
    }
}
