<?php

namespace App\Filament\Admin\Resources\Subscriptions\Tables;

use App\Models\Subscription;
use App\Services\Billing\SubscriptionProvisioner;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.email')->label('User')->searchable()->sortable(),
                TextColumn::make('plan.name')->label('Plan')->sortable(),
                TextColumn::make('credits_remaining')->numeric()->sortable(),
                // The user's own stored allotment for the current period.
                TextColumn::make('credits_total')->label('Credits total')->numeric()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    // An active row dated into the future hasn't started yet.
                    ->state(fn ($record): string => match (true) {
                        $record->isScheduled() => 'scheduled',
                        $record->inRenewalGrace() => 'renewal grace',
                        default => $record->status,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'scheduled' => 'info',
                        'renewal grace' => 'warning',
                        'expired', 'superseded' => 'gray',
                        'cancelled' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('assign_source')
                    ->label('Assigned via')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('starts_at')->dateTime('Y-m-d')->sortable(),
                TextColumn::make('ends_at')
                    // A free period re-assigns itself when it ends (new row + history).
                    ->state(fn ($record): ?string => $record->ends_at?->format('Y-m-d')
                        .($record->plan?->isFree() ? ' (renews)' : ''))
                    ->sortable(),
                TextColumn::make('cancelled_at')->dateTime('Y-m-d H:i')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('expired_at')->dateTime('Y-m-d H:i')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('renewal_grace_at')
                    ->label('Renewal grace given')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'expired' => 'Expired',
                    'superseded' => 'Superseded',
                    'cancelled' => 'Cancelled',
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                EditAction::make(),
                Action::make('cancelPlan')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record): bool => $record->status === Subscription::STATUS_ACTIVE)
                    ->requiresConfirmation()
                    ->modalHeading('Cancel this subscription?')
                    ->modalDescription(fn ($record): string => ! empty($record->lemonsqueezy_subscription_id)
                        ? 'This cancels it at LemonSqueezy too, so it stops renewing. Access continues until the period ends.'
                        : 'Assigned by hand, so there is nothing to cancel at the payment provider. Access ends immediately and the user is put on Free on their next visit.')
                    ->action(function ($record): void {
                        try {
                            $message = app(SubscriptionProvisioner::class)->cancelByAdmin($record, Auth::id());
                        } catch (RuntimeException $e) {
                            Notification::make()->title('Could not cancel')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Subscription cancelled')->body($message)->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
