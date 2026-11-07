<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Support\Impersonation;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('loginAs')
                ->label('Login as')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('gray')
                // Admin-only, and never for your own account.
                ->visible(fn (): bool => (bool) Auth::user()?->is_admin && ! $this->record->is(Auth::user()))
                ->requiresConfirmation()
                ->modalHeading('Log in as this user?')
                ->modalDescription('You will be signed in as them in the user dashboard. The admin panel stays locked until you switch back, and a bar at the bottom of their screen brings you straight back to this page.')
                ->modalSubmitActionLabel('Log in as user')
                ->action(function (): void {
                    Impersonation::handOff($this->record->getKey(), url()->previous());

                    $this->redirect(route('impersonate.start'));
                }),

            Action::make('trustUser')
                ->label('Trust user')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Trust this user?')
                ->modalDescription('Clears any flag/restriction and permanently exempts this user from automatic multi-account detection.')
                ->visible(fn () => ! $this->record->trusted_at)
                ->action(function (): void {
                    $this->record->forceFill([
                        'trusted_at' => now(),
                        'restricted_at' => null,
                        'flagged_at' => null,
                    ])->save();

                    Notification::make()->title('User trusted')->success()->send();
                    $this->refreshFormData(['trusted_at', 'restricted_at', 'flagged_at']);
                }),

            Action::make('restrictAi')
                ->label('Restrict AI')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Restrict AI analysis?')
                ->modalDescription('The user keeps their dashboard and data, but every CV upload/analysis is blocked with an "account under review" message.')
                ->visible(fn () => ! $this->record->restricted_at)
                ->action(function (): void {
                    $this->record->forceFill(['restricted_at' => now(), 'trusted_at' => null])->save();

                    Notification::make()->title('AI analysis restricted')->warning()->send();
                    $this->refreshFormData(['restricted_at', 'trusted_at']);
                }),

            Action::make('liftRestriction')
                ->label('Lift restriction')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Lift the AI restriction?')
                ->modalDescription('AI analysis works again. The review flag is kept for history — use "Trust user" to exempt them from future detection.')
                ->visible(fn () => (bool) $this->record->restricted_at)
                ->action(function (): void {
                    $this->record->forceFill(['restricted_at' => null])->save();

                    Notification::make()->title('Restriction lifted')->success()->send();
                    $this->refreshFormData(['restricted_at']);
                }),

            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Admin status can never be changed through the panel — keep whatever is stored.
        unset($data['is_admin']);

        return $data;
    }
}
