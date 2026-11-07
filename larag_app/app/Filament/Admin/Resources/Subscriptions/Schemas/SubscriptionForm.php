<?php

namespace App\Filament\Admin\Resources\Subscriptions\Schemas;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('User')
                    ->options(fn () => User::query()->pluck('email', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('plan_id')
                    ->label('Plan')
                    ->options(fn () => Plan::query()->pluck('name', 'id'))
                    ->required(),
                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'expired' => 'Expired',
                        'superseded' => 'Superseded',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required()
                    ->default('active')
                    // LemonSqueezy is the source of truth for a paid subscription:
                    // editing the status here would be undone by the next webhook,
                    // so use the Cancel action (it tells the provider) instead.
                    ->disabled(fn (?Subscription $record): bool => $record !== null && ! empty($record->lemonsqueezy_subscription_id))
                    ->helperText(fn (?Subscription $record): ?string => $record !== null && ! empty($record->lemonsqueezy_subscription_id)
                        ? 'Managed by LemonSqueezy — use the Cancel action on the subscriptions list to cancel it at the provider.'
                        : null),
                TextInput::make('credits_remaining')->numeric()->required()->minValue(0),
                DateTimePicker::make('starts_at')
                    ->required()
                    ->helperText('A future date only schedules the plan — it starts granting credits on that date.'),
                DateTimePicker::make('ends_at')->required(),
            ]);
    }
}
