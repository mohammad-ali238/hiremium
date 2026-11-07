<?php

namespace App\Filament\Admin\Pages;

use App\Models\BillingSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property Schema $form
 */
class ManageBillingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.admin.pages.manage-billing-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Billing Settings';

    protected static ?string $navigationLabel = 'Billing Settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = BillingSetting::current();

        $this->form->fill([
            'enabled' => $settings->enabled,
            'store_id' => $settings->store_id,
            'api_key' => $settings->api_key,
            'signing_secret' => $settings->signing_secret,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('LemonSqueezy')
                    ->description('Connect your LemonSqueezy store so users can pay for plans. Secrets are stored encrypted at rest.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Enable online checkout')
                            ->helperText('When off, the pricing page falls back to sign-up only and the webhook is rejected.')
                            ->columnSpanFull(),
                        TextInput::make('store_id')
                            ->label('Store ID')
                            ->helperText('Your LemonSqueezy store numeric ID.'),
                        TextInput::make('api_key')
                            ->label('API key')
                            ->password()
                            ->revealable()
                            ->helperText('Stored encrypted at rest.'),
                        TextInput::make('signing_secret')
                            ->label('Webhook signing secret')
                            ->password()
                            ->revealable()
                            ->helperText('Stored encrypted at rest. Must match the secret set on the webhook in LemonSqueezy.'),
                    ]),

                Section::make('Webhook')
                    ->description('Add this URL as a webhook in LemonSqueezy and subscribe to the subscription events.')
                    ->schema([
                        Placeholder::make('webhook_url')
                            ->label('Webhook URL')
                            ->content(fn (): string => url('/api/webhooks/lemonsqueezy')),
                        Placeholder::make('webhook_events')
                            ->label('Events to enable')
                            ->content('subscription_created, subscription_updated, subscription_payment_success, subscription_cancelled, subscription_expired'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        BillingSetting::current()->update([
            'enabled' => (bool) ($data['enabled'] ?? false),
            'store_id' => $data['store_id'] ?? null,
            'api_key' => $data['api_key'] ?? null,
            'signing_secret' => $data['signing_secret'] ?? null,
        ]);

        Notification::make()
            ->title('Billing settings saved')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save settings')
                ->action('save')
                ->keyBindings(['mod+s']),
        ];
    }
}
