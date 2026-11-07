<?php

namespace App\Filament\Admin\Pages;

use App\Services\Tools\Contracts\ToolDefinition;
use App\Services\Tools\ToolAi;
use App\Services\Tools\ToolPurger;
use App\Services\Tools\ToolRegistry;
use App\Support\ToolSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Everything that governs the public free tools: the switches, the three daily
 * ceilings, which model each AI stage runs on, the blocklists, and how long a
 * visitor's data is kept.
 *
 * Anything left empty inherits — models fall back to AI Settings, copy falls
 * back to the built-in wording — so the page can be saved half-filled without
 * blanking the site out.
 *
 * @property Schema $form
 */
class ManageToolSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.admin.pages.manage-tool-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Free Tools';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Free Tools Settings';

    protected static ?string $navigationLabel = 'Settings';

    public ?array $data = [];

    public function mount(): void
    {
        $state = [
            'enabled' => ToolSettings::enabled(),
            'daily_per_email' => ToolSettings::dailyPerEmail(),
            'daily_per_device' => ToolSettings::dailyPerDevice(),
            'per_ip_per_minute' => ToolSettings::perIpPerMinute(),
            'daily_per_ip' => ToolSettings::dailyPerIp(),
            'site_daily_cap' => ToolSettings::siteDailyCap(),
            'max_file_mb' => ToolSettings::maxFileMb(),
            'marketing_opt_in' => ToolSettings::marketingOptIn(),
            'allow_cv_download' => ToolSettings::allowCvDownload(),
            'recaptcha_enabled' => (bool) (ToolSettings::all()['recaptcha_enabled'] ?? true),
            'consent_text' => (string) (ToolSettings::all()['consent_text'] ?? ''),
            'contact_url' => (string) (ToolSettings::all()['contact_url'] ?? ''),

            'store_raw_payloads' => ToolSettings::storeRawPayloads(),

            'email_from_name' => ToolSettings::emailFromName(),
            'resend_max' => ToolSettings::resendMax(),
            'resend_max_per_email' => ToolSettings::resendMaxPerEmail(),

            'extra_banned_domains' => (string) (ToolSettings::all()['extra_banned_domains'] ?? ''),
            'blocked_emails' => (string) (ToolSettings::all()['blocked_emails'] ?? ''),
            'blocked_ips' => (string) (ToolSettings::all()['blocked_ips'] ?? ''),

            'expiry_hours' => ToolSettings::expiryHours(),
            'retention_days' => ToolSettings::retentionDays(),
            'auto_cleanup_expired' => ToolSettings::autoCleanupExpired(),
            'auto_cleanup_retention' => ToolSettings::autoCleanupRetention(),
        ];

        foreach ($this->tools() as $slug => $tool) {
            $key = $this->key($slug);

            $state["tool_{$key}_enabled"] = ToolSettings::toolEnabled($slug);
            $state["email_subject_{$key}"] = ToolSettings::emailSubject($slug);

            foreach (array_keys($tool->aiStages()) as $stage) {
                $state["ai_{$key}_{$stage}_provider"] = ToolSettings::aiProvider($slug, $stage);
                $state["ai_{$key}_{$stage}_model"] = ToolSettings::aiModel($slug, $stage);
            }

            foreach (ToolSettings::SEO_FIELDS as $field) {
                $state["seo_{$key}_{$field}"] = ToolSettings::seoField($slug, $field);
            }
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('tool_settings')
                    ->tabs([
                        $this->generalTab(),
                        $this->aiTab(),
                        $this->emailTab(),
                        $this->blocklistTab(),
                        $this->retentionTab(),
                        $this->seoTab(),
                    ]),
            ])
            ->statePath('data');
    }

    /* ------------------------------------------------------------------ */
    /* Tabs                                                                */
    /* ------------------------------------------------------------------ */

    private function generalTab(): Tab
    {
        $toolToggles = [];

        foreach ($this->tools() as $slug => $tool) {
            $toolToggles[] = Toggle::make('tool_'.$this->key($slug).'_enabled')
                ->label($tool->name())
                ->helperText('Off: the page 404s and the tool leaves the header menu.');
        }

        return Tab::make('General')
            ->schema([
                Section::make('Switches')
                    ->schema(array_merge([
                        Toggle::make('enabled')
                            ->label('Free Tools enabled')
                            ->helperText('The master switch. Off: every tool page 404s and no new submission is accepted. Runs already created still finish — someone holding a result link is not stranded.'),
                    ], $toolToggles)),

                Section::make('Daily limits')
                    ->description('Four ceilings, because one person has more than one identity. A visitor who is out of runs sees the same message whichever ceiling stopped them, so a refusal never teaches anyone which one to work around.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('daily_per_email')
                            ->label('Runs per email per day')
                            ->numeric()->minValue(1)->maxValue(1000)->required()
                            ->helperText('Counted on the canonical address, so a.b+tag@gmail and ab@gmail share one allowance.'),
                        TextInput::make('daily_per_device')
                            ->label('Runs per browser per day')
                            ->numeric()->minValue(1)->maxValue(1000)->required()
                            ->helperText('The same device cookie registration uses. Stops a second email address on the same browser buying a fresh set of runs. A visitor with cookies disabled is judged on their email alone.'),
                        TextInput::make('daily_per_ip')
                            ->label('Runs per IP per day')
                            ->numeric()->minValue(0)->maxValue(1000)->required()
                            ->helperText('The ceiling left when someone changes both their email address and their browser. Blunt: an office, campus or mobile network is one address for everyone behind it, so set it high — or 0 for no ceiling — if your visitors share networks.'),
                        TextInput::make('per_ip_per_minute')
                            ->label('Submissions per IP per minute')
                            ->numeric()->minValue(1)->maxValue(120)->required()
                            ->helperText('A burst guard, not a daily allowance — it says how fast, never how many. Only complete submissions count, so fixing a typo never spends it.'),
                        TextInput::make('site_daily_cap')
                            ->label('Site-wide runs per day')
                            ->numeric()->minValue(0)->maxValue(100000)->required()
                            ->helperText('The token-spend guard across all tools. 0 = no ceiling.'),
                    ]),

                Section::make('The form')
                    ->columns(2)
                    ->schema([
                        TextInput::make('max_file_mb')
                            ->label('Maximum upload size (MB)')
                            ->numeric()->minValue(1)->maxValue(25)->required()
                            ->helperText('Never advertised on the page — it only shapes the error a too-large file gets.'),
                        Toggle::make('recaptcha_enabled')
                            ->label('reCAPTCHA on tool forms')
                            ->helperText('Uses the same keys as registration (Site Settings). Has no effect until those keys are set.'),
                        Toggle::make('marketing_opt_in')
                            ->label('Show the marketing opt-in checkbox')
                            ->helperText('Optional and separate from the required consent. Opted-in addresses are filterable in Tool Runs.'),
                        Toggle::make('allow_cv_download')
                            ->label('Offer the CV on the public result page')
                            ->helperText('Off by default: the result link is shareable, so the file would travel with it.'),
                        Textarea::make('consent_text')
                            ->label('Consent sentence')
                            ->rows(2)
                            ->placeholder(ToolSettings::DEFAULT_CONSENT_TEXT)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Shown beside the required checkbox. The Privacy Policy link is added after it automatically.'),
                        TextInput::make('contact_url')
                            ->label('Contact link used in refusals')
                            ->url()
                            ->placeholder(route('contact'))
                            ->maxLength(2048)
                            ->columnSpanFull()
                            ->helperText('Where "Contact us" points when a submission is turned away.'),
                    ]),
            ]);
    }

    private function aiTab(): Tab
    {
        $sections = [];

        foreach ($this->tools() as $slug => $tool) {
            $key = $this->key($slug);
            $fields = [];

            foreach ($tool->aiStages() as $stage => $label) {
                $inherited = ToolAi::inherited($stage);

                $fields[] = Select::make("ai_{$key}_{$stage}_provider")
                    ->label($label.' — provider')
                    ->options(ToolAi::providerOptions())
                    ->placeholder('Inherit: '.($inherited['provider'] ?: 'not set'))
                    ->helperText('Empty inherits the global AI Settings.');

                $fields[] = TextInput::make("ai_{$key}_{$stage}_model")
                    ->label($label.' — model')
                    ->placeholder($inherited['model'] !== '' ? 'Inherit: '.$inherited['model'] : 'Provider default')
                    ->maxLength(80)
                    ->helperText('Empty uses the chosen provider\'s model from AI Settings.');
            }

            $sections[] = Section::make($tool->name())
                ->description('API keys, prompts, temperature and the scoring gates always come from AI Settings — only the provider and model can be pinned here.')
                ->columns(2)
                ->schema($fields);
        }

        $sections[] = Section::make('Logging')
            ->schema([
                Toggle::make('store_raw_payloads')
                    ->label('Store raw AI payloads in the usage log')
                    ->helperText('Keeps the request preview and the raw response on every call, which is what makes a bad score diagnosable. Costs database space.'),
            ]);

        return Tab::make('AI')->schema($sections);
    }

    private function emailTab(): Tab
    {
        $subjects = [];

        foreach ($this->tools() as $slug => $tool) {
            $subjects[] = TextInput::make('email_subject_'.$this->key($slug))
                ->label($tool->name())
                ->placeholder($tool->emailSubject())
                ->maxLength(200)
                ->helperText('Empty uses the tool\'s own subject line.');
        }

        return Tab::make('Email')
            ->schema([
                Section::make('The result email')
                    ->description('The one email a visitor gets. Opening its link is what confirms the address and starts the run, so it is deliberately just the link.')
                    ->schema(array_merge([
                        TextInput::make('email_from_name')
                            ->label('From name')
                            ->placeholder((string) config('mail.from.name'))
                            ->maxLength(120)
                            ->helperText('Empty uses the application\'s own mail from-name.'),
                        TextInput::make('resend_max')
                            ->label('Resends allowed per run')
                            ->numeric()->minValue(0)->maxValue(10)->required()
                            ->helperText('The "Didn\'t get it?" button on the success screen, also limited to one a minute.'),
                        TextInput::make('resend_max_per_email')
                            ->label('Resends allowed per email address / day')
                            ->numeric()->minValue(0)->maxValue(50)->required()
                            ->helperText('Total across every run one address submits — the real ceiling on how many emails one inbox can be sent.'),
                    ], $subjects)),
            ]);
    }

    private function blocklistTab(): Tab
    {
        return Tab::make('Blocklists')
            ->schema([
                Section::make('Who is turned away')
                    ->description('One entry per line. Disposable-inbox domains are already blocked from the shared list in config/disposable_email.php — these are your additions.')
                    ->schema([
                        Textarea::make('extra_banned_domains')
                            ->label('Extra banned email domains')
                            ->rows(4)
                            ->placeholder("spam-source.test\nthrowaway.example"),
                        Textarea::make('blocked_emails')
                            ->label('Blocked email addresses')
                            ->rows(4)
                            ->helperText('Matched on the canonical address, so aliases of a blocked address are blocked too.'),
                        Textarea::make('blocked_ips')
                            ->label('Blocked IP addresses')
                            ->rows(4)
                            ->helperText('Checked before anything else, so a blocked IP never reaches the captcha or the quota.'),
                    ]),
            ]);
    }

    private function retentionTab(): Tab
    {
        $purger = app(ToolPurger::class);

        return Tab::make('Retention')
            ->schema([
                Section::make('How long data is kept')
                    ->columns(2)
                    ->schema([
                        TextInput::make('expiry_hours')
                            ->label('Unopened link lifetime (hours)')
                            ->numeric()->minValue(1)->maxValue(720)->required()
                            ->helperText('An unopened link dies after this. Opening one clears the expiry for good.'),
                        TextInput::make('retention_days')
                            ->label('Hard retention (days)')
                            ->numeric()->minValue(1)->maxValue(3650)->required()
                            ->helperText('Every run is purged this long after it was submitted, opened or not.'),
                        Toggle::make('auto_cleanup_expired')
                            ->label('Nightly: delete expired unopened runs'),
                        Toggle::make('auto_cleanup_retention')
                            ->label('Nightly: delete runs past retention'),
                    ]),

                Section::make('Clean up now')
                    ->description(sprintf(
                        'Right now: %s expired unopened, %s past retention. Deleting takes the run, its AI logs, its day fingerprints and the uploaded file.',
                        number_format($purger->expiredUnopenedCount()),
                        number_format($purger->pastRetentionCount()),
                    ))
                    ->schema([]),
            ]);
    }

    private function seoTab(): Tab
    {
        $tabs = [];

        foreach ($this->tools() as $slug => $tool) {
            $key = $this->key($slug);
            $defaults = $tool->seoDefaults();

            $tabs[] = Tab::make($tool->name())
                ->schema([
                    TextInput::make("seo_{$key}_title")
                        ->label('Meta title')
                        ->placeholder($defaults['title'] ?? '')
                        ->maxLength(255),
                    Textarea::make("seo_{$key}_description")
                        ->label('Meta description')
                        ->rows(3)
                        ->placeholder($defaults['description'] ?? '')
                        ->maxLength(500),
                    TextInput::make("seo_{$key}_keywords")
                        ->label('Meta keywords')
                        ->placeholder($defaults['keywords'] ?? '')
                        ->maxLength(500),
                    TextInput::make("seo_{$key}_image")
                        ->label('Share image URL')
                        ->url()
                        ->placeholder(asset('images/og-default.png'))
                        ->maxLength(2048),
                ]);
        }

        return Tab::make('SEO')
            ->schema([
                Section::make('Tool pages')
                    ->description('Empty fields fall back to the tool\'s own defaults, shown as placeholders. Result pages are never indexed and never appear in the sitemap.')
                    ->schema([Tabs::make('tool_seo')->tabs($tabs)]),
            ]);
    }

    /* ------------------------------------------------------------------ */
    /* Saving and the cleanup buttons                                      */
    /* ------------------------------------------------------------------ */

    public function save(): void
    {
        $data = $this->form->getState();

        ToolSettings::setEnabled((bool) ($data['enabled'] ?? true));
        ToolSettings::put('daily_per_email', max(1, (int) ($data['daily_per_email'] ?? 5)));
        ToolSettings::put('daily_per_device', max(1, (int) ($data['daily_per_device'] ?? 5)));
        ToolSettings::put('per_ip_per_minute', max(1, (int) ($data['per_ip_per_minute'] ?? 6)));
        ToolSettings::put('daily_per_ip', max(0, (int) ($data['daily_per_ip'] ?? 5)));
        ToolSettings::put('site_daily_cap', max(0, (int) ($data['site_daily_cap'] ?? 300)));
        ToolSettings::put('max_file_mb', max(1, (int) ($data['max_file_mb'] ?? 2)));
        ToolSettings::put('marketing_opt_in', (bool) ($data['marketing_opt_in'] ?? true));
        ToolSettings::put('allow_cv_download', (bool) ($data['allow_cv_download'] ?? false));
        ToolSettings::setRecaptchaEnabled((bool) ($data['recaptcha_enabled'] ?? true));
        ToolSettings::setConsentText((string) ($data['consent_text'] ?? ''));
        ToolSettings::setContactUrl((string) ($data['contact_url'] ?? ''));

        ToolSettings::put('store_raw_payloads', (bool) ($data['store_raw_payloads'] ?? true));

        ToolSettings::setEmailFromName((string) ($data['email_from_name'] ?? ''));
        ToolSettings::put('resend_max', max(0, (int) ($data['resend_max'] ?? 3)));
        ToolSettings::put('resend_max_per_email', max(0, (int) ($data['resend_max_per_email'] ?? 5)));

        ToolSettings::setExtraBannedDomains((string) ($data['extra_banned_domains'] ?? ''));
        ToolSettings::setBlockedEmails((string) ($data['blocked_emails'] ?? ''));
        ToolSettings::setBlockedIps((string) ($data['blocked_ips'] ?? ''));

        ToolSettings::put('expiry_hours', max(1, (int) ($data['expiry_hours'] ?? 72)));
        ToolSettings::put('retention_days', max(1, (int) ($data['retention_days'] ?? 90)));
        ToolSettings::put('auto_cleanup_expired', (bool) ($data['auto_cleanup_expired'] ?? true));
        ToolSettings::put('auto_cleanup_retention', (bool) ($data['auto_cleanup_retention'] ?? true));

        foreach ($this->tools() as $slug => $tool) {
            $key = $this->key($slug);

            ToolSettings::setToolEnabled($slug, (bool) ($data["tool_{$key}_enabled"] ?? true));
            ToolSettings::setEmailSubject($slug, (string) ($data["email_subject_{$key}"] ?? ''));

            foreach (array_keys($tool->aiStages()) as $stage) {
                ToolSettings::setAiProvider($slug, $stage, (string) ($data["ai_{$key}_{$stage}_provider"] ?? ''));
                ToolSettings::setAiModel($slug, $stage, (string) ($data["ai_{$key}_{$stage}_model"] ?? ''));
            }

            foreach (ToolSettings::SEO_FIELDS as $field) {
                ToolSettings::setSeoField($slug, $field, (string) ($data["seo_{$key}_{$field}"] ?? ''));
            }
        }

        Notification::make()->title('Free Tools settings saved')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        $purger = app(ToolPurger::class);

        return [
            Action::make('save')
                ->label('Save settings')
                ->action('save')
                ->keyBindings(['mod+s']),

            Action::make('purge_expired')
                ->label(fn (): string => 'Delete '.number_format($purger->expiredUnopenedCount()).' expired unopened')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalDescription('These links were never opened inside their window, so nothing was ever produced from them. The runs, their fingerprints and their uploaded files are deleted.')
                ->disabled(fn (): bool => $purger->expiredUnopenedCount() === 0)
                ->action(function () use ($purger): void {
                    $count = $purger->purgeExpiredUnopened(5000);

                    Notification::make()->title($count.' expired runs deleted')->success()->send();
                }),

            Action::make('purge_retention')
                ->label(fn (): string => 'Delete '.number_format($purger->pastRetentionCount()).' past retention')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalDescription('These runs are older than the retention period, opened or not. The runs, their AI logs, their fingerprints and their uploaded files are deleted.')
                ->disabled(fn (): bool => $purger->pastRetentionCount() === 0)
                ->action(function () use ($purger): void {
                    $count = $purger->purgeRetention(5000);

                    Notification::make()->title($count.' runs deleted')->success()->send();
                }),
        ];
    }

    /** @return array<string, ToolDefinition> */
    private function tools(): array
    {
        return app(ToolRegistry::class)->all();
    }

    /** Settings keys are built from slugs, so dashes become underscores. */
    private function key(string $slug): string
    {
        return str_replace('-', '_', $slug);
    }
}
