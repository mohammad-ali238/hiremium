<?php

namespace App\Filament\Admin\Pages;

use App\Support\SiteSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property Schema $form
 */
class ManageSiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.admin.pages.manage-site-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Site Settings';

    protected static ?string $navigationLabel = 'Site Settings';

    public ?array $data = [];

    public function mount(): void
    {
        $state = [
            'registration_enabled' => SiteSettings::registrationEnabled(),
            'ssl_verify_enabled' => SiteSettings::sslVerifyEnabled(),
            'testing_mode_enabled' => SiteSettings::testingModeEnabled(),
            'recaptcha_enabled' => (bool) (SiteSettings::all()['recaptcha_enabled'] ?? false),
            'recaptcha_site_key' => SiteSettings::recaptchaSiteKey(),
            'recaptcha_secret_key' => SiteSettings::recaptchaSecretKey(),
            'contact_recaptcha_enabled' => (bool) (SiteSettings::all()['contact_recaptcha_enabled'] ?? false),
            'contact_send_to_email' => (string) (SiteSettings::all()['contact_send_to_email'] ?? ''),
        ];

        foreach (SiteSettings::SOCIAL_PROVIDERS as $provider) {
            $state["social_{$provider}_enabled"] = (bool) (SiteSettings::all()["social_{$provider}_enabled"] ?? false);
            $state["social_{$provider}_client_id"] = SiteSettings::socialClientId($provider);
            $state["social_{$provider}_client_secret"] = SiteSettings::socialClientSecret($provider);
            $state["social_{$provider}_callback_display"] = route('social.callback', ['provider' => $provider]);
        }

        foreach (array_keys(SiteSettings::SEO_PAGES) as $page) {
            $overrides = SiteSettings::seoOverrides($page);
            foreach (SiteSettings::SEO_FIELDS as $field) {
                $state["seo_{$page}_{$field}"] = $overrides[$field];
            }
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General')
                    ->description('Global switches for the public site.')
                    ->schema([
                        Toggle::make('registration_enabled')
                            ->label('Allow new registrations')
                            ->helperText('When off, the register page redirects to login with a notice.'),
                    ]),

                Section::make('Testing mode')
                    ->description('Exercise the bulk-upload pipeline end to end without calling the real AI providers.')
                    ->schema([
                        Toggle::make('testing_mode_enabled')
                            ->label('Testing mode (fake AI results)')
                            ->helperText('When ON, resume parsing and match scoring for bulk uploads use FAKE data: random scores 30–95, generated candidate details, canned reasons and skills. No AI tokens are spent. Credits are still charged as normal so billing can be tested too. Keep OFF in production.'),
                    ]),

                Section::make('Security')
                    ->description('Advanced connection settings for outgoing requests to AI providers.')
                    ->schema([
                        Toggle::make('ssl_verify_enabled')
                            ->label('Verify SSL certificates')
                            ->helperText('Keep this ON in production. Turn OFF only for local development behind a self-signed certificate — disabling it makes outgoing HTTPS calls to AI providers skip certificate verification (insecure).'),
                    ]),

                Section::make('Google reCAPTCHA (v2 checkbox)')
                    ->description('Protects the register form from bots. Get keys at google.com/recaptcha (choose reCAPTCHA v2 → "I\'m not a robot" checkbox).')
                    ->columns(2)
                    ->schema([
                        Toggle::make('recaptcha_enabled')
                            ->label('Enable reCAPTCHA on register form')
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('recaptcha_site_key')
                            ->label('Site key')
                            ->requiredIf('recaptcha_enabled', true)
                            ->maxLength(255),
                        TextInput::make('recaptcha_secret_key')
                            ->label('Secret key')
                            ->password()
                            ->revealable()
                            ->requiredIf('recaptcha_enabled', true)
                            ->maxLength(255)
                            ->helperText('Stored encrypted at rest.'),
                    ]),

                Section::make('Contact Settings')
                    ->description('Where contact-form submissions are emailed, and whether reCAPTCHA is required on the contact form (uses the reCAPTCHA keys above).')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_send_to_email')
                            ->label('Send contact emails to')
                            ->email()
                            ->maxLength(255)
                            ->placeholder(config('mail.from.address'))
                            ->helperText('All contact-form messages are sent here. Defaults to the mail "from" address when empty.'),
                        Toggle::make('contact_recaptcha_enabled')
                            ->label('Enable reCAPTCHA on contact form')
                            ->helperText('Requires the reCAPTCHA site & secret keys above.'),
                    ]),

                Section::make('Social Login')
                    ->description('Enable sign-in with third-party providers on the login and register pages. Client secrets are stored encrypted.')
                    ->schema(array_merge(...array_map(
                        fn (string $provider): array => $this->socialProviderFields($provider),
                        SiteSettings::SOCIAL_PROVIDERS,
                    ))),

                Section::make('SEO')
                    ->description('Meta title, description, keywords, and social share image for each public page. Empty fields fall back to the built-in defaults shown as placeholders.')
                    ->schema([
                        Tabs::make('seo_pages')
                            ->tabs(array_map(
                                fn (string $page, string $label): Tab => $this->seoPageTab($page, $label),
                                array_keys(SiteSettings::SEO_PAGES),
                                array_values(SiteSettings::SEO_PAGES),
                            )),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Toggle + credential fields for one social provider, including the
     * callback URL the admin must whitelist in the provider's console.
     *
     * @return array<int, Component|Field>
     */
    private function socialProviderFields(string $provider): array
    {
        $label = ucfirst($provider);

        return [
            Section::make($label)
                ->columns(2)
                ->compact()
                ->schema([
                    Toggle::make("social_{$provider}_enabled")
                        ->label("Enable {$label} login")
                        ->live()
                        ->columnSpanFull(),
                    TextInput::make("social_{$provider}_client_id")
                        ->label('Client ID')
                        ->requiredIf("social_{$provider}_enabled", true)
                        ->maxLength(255),
                    TextInput::make("social_{$provider}_client_secret")
                        ->label('Client secret')
                        ->password()
                        ->revealable()
                        ->requiredIf("social_{$provider}_enabled", true)
                        ->maxLength(255),
                    TextInput::make("social_{$provider}_callback_display")
                        ->label('Authorized redirect / callback URL')
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull()
                        ->helperText("Paste this URL into the {$label} developer console as the allowed redirect URI."),
                ]),
        ];
    }

    /**
     * SEO override fields for one public page. Placeholders show the
     * built-in defaults that apply while a field is left empty.
     */
    private function seoPageTab(string $page, string $label): Tab
    {
        $defaults = SiteSettings::seoDefaults()[$page];

        return Tab::make($label)
            ->schema([
                TextInput::make("seo_{$page}_title")
                    ->label('Meta title')
                    ->placeholder($defaults['title'])
                    ->maxLength(255)
                    ->helperText('Shown in search results and browser tabs. ~60 characters recommended.'),
                Textarea::make("seo_{$page}_description")
                    ->label('Meta description')
                    ->placeholder($defaults['description'])
                    ->rows(3)
                    ->maxLength(500)
                    ->helperText('~155 characters recommended.'),
                TextInput::make("seo_{$page}_keywords")
                    ->label('Meta keywords')
                    ->placeholder($defaults['keywords'])
                    ->maxLength(500)
                    ->helperText('Comma-separated.'),
                TextInput::make("seo_{$page}_image")
                    ->label('Share image URL')
                    ->url()
                    ->placeholder(asset('images/og-default.png'))
                    ->maxLength(2048)
                    ->helperText('Absolute URL to a 1200×630 image for social shares (og:image). Leave empty to use the default share image.'),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteSettings::setRegistrationEnabled((bool) ($data['registration_enabled'] ?? true));
        SiteSettings::setSslVerifyEnabled((bool) ($data['ssl_verify_enabled'] ?? true));
        SiteSettings::setTestingModeEnabled((bool) ($data['testing_mode_enabled'] ?? false));
        SiteSettings::setRecaptchaEnabled((bool) ($data['recaptcha_enabled'] ?? false));
        SiteSettings::setRecaptchaSiteKey((string) ($data['recaptcha_site_key'] ?? ''));
        SiteSettings::setRecaptchaSecretKey((string) ($data['recaptcha_secret_key'] ?? ''));
        SiteSettings::setContactRecaptchaEnabled((bool) ($data['contact_recaptcha_enabled'] ?? false));
        SiteSettings::setContactSendToEmail((string) ($data['contact_send_to_email'] ?? ''));

        foreach (SiteSettings::SOCIAL_PROVIDERS as $provider) {
            SiteSettings::setSocialEnabled($provider, (bool) ($data["social_{$provider}_enabled"] ?? false));
            SiteSettings::setSocialClientId($provider, (string) ($data["social_{$provider}_client_id"] ?? ''));
            SiteSettings::setSocialClientSecret($provider, (string) ($data["social_{$provider}_client_secret"] ?? ''));
        }

        foreach (array_keys(SiteSettings::SEO_PAGES) as $page) {
            $fields = [];
            foreach (SiteSettings::SEO_FIELDS as $field) {
                $fields[$field] = (string) ($data["seo_{$page}_{$field}"] ?? '');
            }
            SiteSettings::setSeo($page, $fields);
        }

        Notification::make()
            ->title('Site settings saved')
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
