<?php

namespace App\Filament\Admin\Resources\TrialCredits\Tables;

use App\Models\TrialCredit;
use App\Services\Trial\TrialService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TrialCreditsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'verified' => 'success',
                        'pending' => 'warning',
                        'expired' => 'gray',
                        'revoked' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('credits_total')->label('Total')->numeric()->sortable(),
                TextColumn::make('credits_used')->label('Used')->numeric()->sortable(),
                TextColumn::make('credits_remaining')->label('Remaining')->numeric(),
                TextColumn::make('verified_at')->dateTime('Y-m-d')->placeholder('—')->sortable(),
                TextColumn::make('expires_at')->dateTime('Y-m-d')->placeholder('—')->sortable(),
                TextColumn::make('site_url')->label('Site')->limit(30)->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'verified' => 'Verified',
                    'expired' => 'Expired',
                    'revoked' => 'Revoked',
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (TrialCredit $record): bool => $record->status !== TrialCredit::STATUS_VERIFIED)
                    ->requiresConfirmation()
                    ->modalHeading('Verify this trial')
                    ->modalDescription('This activates the grant, generates an API key, and starts the expiry window.')
                    ->action(function (TrialCredit $record): void {
                        app(TrialService::class)->activate($record);
                        Notification::make()->title('Trial verified and activated.')->success()->send();
                    }),
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (TrialCredit $record): bool => $record->status === TrialCredit::STATUS_VERIFIED)
                    ->requiresConfirmation()
                    ->modalHeading('Revoke this trial')
                    ->modalDescription('The API key will stop working immediately.')
                    ->action(function (TrialCredit $record): void {
                        $record->update(['status' => TrialCredit::STATUS_REVOKED]);
                        Notification::make()->title('Trial revoked.')->warning()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
