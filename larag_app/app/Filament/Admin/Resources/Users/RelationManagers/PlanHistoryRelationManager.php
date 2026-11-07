<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use App\Models\UserPlanHistory;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only plan history on the user edit page: every plan this user has been
 * on, how it was assigned and when it ended. Paginated (AJAX) like every other
 * Filament table.
 */
class PlanHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'planHistories';

    protected static ?string $title = 'Plan history';

    protected static ?string $modelLabel = 'plan history entry';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Plan history')
            ->description('Every plan assignment, renewal, cancellation and expiry for this user — newest first.')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('plan_name')
                    ->label('Plan')
                    ->searchable(),
                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn (Model $record): string => $record->eventLabel())
                    ->color(fn (Model $record): string => $record->eventColor()),
                TextColumn::make('source')
                    ->label('Source')
                    ->formatStateUsing(fn (Model $record): string => $record->sourceLabel()),
                TextColumn::make('actor.name')
                    ->label('By')
                    ->placeholder('—'),
                TextColumn::make('credits_total')
                    ->label('AI credits')
                    ->numeric(),
                TextColumn::make('billing_interval')
                    ->label('Interval')
                    ->placeholder('—'),
                TextColumn::make('price')
                    ->label('Price')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('Y-m-d')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('Y-m-d')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cancelled_at')
                    ->label('Cancelled')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—'),
                TextColumn::make('expired_at')
                    ->label('Expired')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—'),
                TextColumn::make('note')
                    ->label('Note')
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->options([
                        UserPlanHistory::EVENT_ASSIGNED => 'Assigned',
                        UserPlanHistory::EVENT_RENEWED => 'Renewed',
                        UserPlanHistory::EVENT_SUPERSEDED => 'Replaced',
                        UserPlanHistory::EVENT_CANCELLED => 'Cancelled',
                        UserPlanHistory::EVENT_RESUMED => 'Resumed',
                        UserPlanHistory::EVENT_EXPIRED => 'Expired',
                        UserPlanHistory::EVENT_DELETED => 'Deleted',
                    ]),
                SelectFilter::make('source')
                    ->options([
                        UserPlanHistory::SOURCE_ADMIN => 'Assigned by admin',
                        UserPlanHistory::SOURCE_PAYMENT => 'Payment',
                        UserPlanHistory::SOURCE_AUTO_FREE => 'Auto (free plan)',
                        UserPlanHistory::SOURCE_CRON => 'Scheduled job',
                        UserPlanHistory::SOURCE_SELF => 'User action',
                        UserPlanHistory::SOURCE_SYSTEM => 'System',
                    ]),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('No plan history yet')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
