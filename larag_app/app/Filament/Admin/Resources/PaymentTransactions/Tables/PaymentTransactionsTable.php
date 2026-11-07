<?php

namespace App\Filament\Admin\Resources\PaymentTransactions\Tables;

use App\Models\PaymentTransaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('created_at')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('user.email')->label('User')->searchable()->placeholder('—'),
                TextColumn::make('plan.name')->label('Plan')->placeholder('—'),
                TextColumn::make('event_name')->label('Event')->badge()->color('info'),
                TextColumn::make('status')->placeholder('—'),
                TextColumn::make('amount_total')
                    ->label('Amount')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?int $state, PaymentTransaction $record): string => $state === null
                        ? '—'
                        : number_format($state / 100, 2).' '.strtoupper((string) $record->currency)),
                TextColumn::make('lemonsqueezy_order_id')->label('Order')->placeholder('—')->toggleable(),
                TextColumn::make('lemonsqueezy_subscription_id')->label('LS subscription')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event_name')
                    ->label('Event')
                    ->options(fn () => PaymentTransaction::query()->distinct()->pluck('event_name', 'event_name')->toArray()),
            ])
            ->defaultSort('id', 'desc');
    }
}
