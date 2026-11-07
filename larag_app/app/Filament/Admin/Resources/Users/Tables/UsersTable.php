<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanHistory;
use App\Services\Billing\SubscriptionProvisioner;
use App\Support\Impersonation;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('provider_name')
                    ->label('Signed up via')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'google' => 'Google',
                        'facebook' => 'Facebook',
                        'linkedin' => 'LinkedIn',
                        default => 'Email',
                    })
                    ->default('email')
                    ->color(fn (?string $state): string => match ($state) {
                        'google' => 'danger',
                        'facebook' => 'info',
                        'linkedin' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('linked_accounts')
                    ->label('Linked')
                    ->state(fn (Model $record) => $record->linkedAccounts()->count() ?: null)
                    ->badge()
                    ->color('warning')
                    ->placeholder('—')
                    ->tooltip('Other accounts sharing this user\'s device, network or email identity'),
                TextColumn::make('review_status')
                    ->label('Status')
                    ->state(fn (Model $record) => $record->trusted_at
                        ? 'Trusted'
                        : ($record->restricted_at ? 'Restricted' : ($record->flagged_at ? 'Flagged' : null)))
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'Trusted' => 'success',
                        'Restricted' => 'danger',
                        'Flagged' => 'warning',
                        default => 'gray',
                    })
                    ->placeholder('—'),
                IconColumn::make('is_admin')->boolean()->label('Admin'),
                TextColumn::make('activeSubscription.plan.name')
                    ->label('Plan')
                    ->placeholder('—'),
                TextColumn::make('activeSubscription.credits_remaining')
                    ->label('Credits')
                    // Remaining out of the total stored on the user's own subscription.
                    ->state(fn (Model $record): ?string => $record->activeSubscription
                        ? number_format((int) $record->activeSubscription->credits_remaining).' / '.number_format($record->activeSubscription->creditsTotal())
                        : null)
                    ->placeholder('—'),
                TextColumn::make('activeSubscription.ends_at')
                    ->label('Expires')
                    ->state(function (Model $record): ?string {
                        $subscription = $record->activeSubscription;
                        if (! $subscription) {
                            return null;
                        }

                        // A free period re-assigns itself when it ends.
                        return $subscription->ends_at?->format('Y-m-d')
                            .($subscription->plan?->isFree() ? ' (renews)' : '');
                    })
                    ->placeholder('—'),
                TextColumn::make('created_at')->dateTime('Y-m-d')->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Filter::make('flagged')
                    ->label('Flagged for review')
                    ->query(fn (Builder $query) => $query->whereNotNull('flagged_at')),
                Filter::make('restricted')
                    ->label('AI restricted')
                    ->query(fn (Builder $query) => $query->whereNotNull('restricted_at')),
                Filter::make('linked')
                    ->label('Has linked accounts')
                    ->query(fn (Builder $query) => $query->where(function (Builder $q) {
                        foreach (['device_hash', 'registration_ip', 'email_canonical'] as $column) {
                            $q->orWhereIn($column, fn ($sub) => $sub
                                ->select($column)
                                ->from('users')
                                ->whereNotNull($column)
                                ->groupBy($column)
                                ->havingRaw('COUNT(*) > 1'));
                        }
                    })),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('loginAs')
                    ->label('Login as')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('gray')
                    // Admin-only, and never for your own account.
                    ->visible(fn (Model $record): bool => (bool) Auth::user()?->is_admin && ! $record->is(Auth::user()))
                    ->requiresConfirmation()
                    ->modalHeading('Log in as this user?')
                    ->modalDescription('You will be signed in as them in the user dashboard. The admin panel stays locked until you switch back, and a bar at the bottom of their screen brings you straight back to this page.')
                    ->modalSubmitActionLabel('Log in as user')
                    ->action(function (Model $record, $livewire): void {
                        Impersonation::handOff($record->getKey(), url()->previous());

                        $livewire->redirect(route('impersonate.start'));
                    }),
                Action::make('cancelPlan')
                    ->label('Cancel plan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Model $record): bool => $record->activeSubscription !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Cancel this user\'s plan?')
                    ->modalDescription(function (Model $record): string {
                        $subscription = $record->activeSubscription;

                        return $subscription && ! empty($subscription->lemonsqueezy_subscription_id)
                            ? 'This cancels the subscription at LemonSqueezy too, so it stops renewing. The user keeps their plan and AI credits until the current period ends.'
                            : 'This plan was assigned by hand, so there is nothing to cancel at the payment provider. The user loses it immediately and will be put on the Free plan on their next visit.';
                    })
                    ->action(function (Model $record): void {
                        /** @var User $record */
                        $subscription = $record->activeSubscription;

                        if (! $subscription) {
                            Notification::make()->title('This user has no active plan')->warning()->send();

                            return;
                        }

                        try {
                            $message = app(SubscriptionProvisioner::class)->cancelByAdmin($subscription, Auth::id());
                        } catch (RuntimeException $e) {
                            Notification::make()->title('Could not cancel the plan')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Plan cancelled')->body($message)->success()->send();
                    }),
                Action::make('assignPlan')
                    ->label('Assign plan')
                    ->icon('heroicon-o-credit-card')
                    ->color('primary')
                    ->schema([
                        Select::make('plan_id')
                            ->label('Plan')
                            ->options(fn () => Plan::query()->where('is_active', true)->pluck('name', 'id'))
                            ->required(),
                        DateTimePicker::make('starts_at')
                            ->default(now())
                            ->helperText('A future date only SCHEDULES the plan: the user keeps their current one until then.')
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->helperText('Optional. Defaults to starts_at + plan duration.'),
                    ])
                    ->action(function (array $data, Model $record): void {
                        /** @var User $record */
                        $plan = Plan::findOrFail($data['plan_id']);
                        $startsAt = $data['starts_at'] ? Carbon::parse($data['starts_at']) : now();
                        $endsAt = $data['ends_at'] ? Carbon::parse($data['ends_at']) : null;

                        app(SubscriptionProvisioner::class)->provision(
                            $record,
                            $plan,
                            [],
                            $startsAt,
                            $endsAt,
                            UserPlanHistory::SOURCE_ADMIN,
                            Auth::id(),
                        );

                        Notification::make()
                            ->title('Plan assigned')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
