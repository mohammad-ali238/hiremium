<?php

namespace App\Filament\Admin\Pages;

use App\Models\Article;
use App\Support\BlogSettings;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

/**
 * Everything that governs the blog: the master switch, how the index reads,
 * what the editor allows, and the SEO fallbacks behind every article.
 *
 * Nothing here lives in .env or in a config file — the blog is configured
 * entirely from this page, so it can be tuned, switched off or moved to S3
 * without a deploy.
 *
 * @property Schema $form
 */
class ManageBlogSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.admin.pages.manage-blog-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Blog Settings';

    protected static ?string $navigationLabel = 'Settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'enabled' => BlogSettings::enabled(),
            'posts_per_page' => BlogSettings::postsPerPage(),
            'blog_title' => BlogSettings::blogTitle(),
            'blog_intro' => BlogSettings::blogIntro(),
            'show_author' => BlogSettings::showAuthor(),
            'show_reading_time' => BlogSettings::showReadingTime(),

            'default_content_mode' => BlogSettings::defaultContentMode(),
            'sanitize_html' => BlogSettings::sanitizeHtml(),
            'allow_custom_css' => BlogSettings::allowCustomCss(),
            'css_max_bytes' => BlogSettings::cssMaxBytes(),

            'storage_driver' => BlogSettings::storageDriver(),
            'max_image_mb' => BlogSettings::maxImageMb(),
            'allowed_ext' => implode(', ', BlogSettings::allowedExtensions()),
            'local_root' => BlogSettings::localRoot(),
            'local_url' => BlogSettings::localUrl(),
            's3_key' => BlogSettings::s3Key(),
            's3_secret' => BlogSettings::s3Secret(),
            's3_region' => BlogSettings::s3Region(),
            's3_bucket' => BlogSettings::s3Bucket(),
            's3_endpoint' => BlogSettings::s3Endpoint(),
            's3_url' => BlogSettings::s3Url(),
            's3_path_style' => BlogSettings::s3PathStyle(),

            'feed_enabled' => BlogSettings::feedEnabled(),
            'feed_title' => BlogSettings::feedTitle(),
            'feed_description' => BlogSettings::feedDescription(),
            'feed_limit' => BlogSettings::feedLimit(),

            'meta_title_suffix' => BlogSettings::metaTitleSuffix(),
            'default_meta_description' => BlogSettings::defaultMetaDescription(),
            'default_og_image' => BlogSettings::defaultOgImage(),
            'default_robots' => BlogSettings::defaultRobots(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('blog_settings')
                    ->tabs([
                        $this->generalTab(),
                        $this->editorTab(),
                        $this->mediaTab(),
                        $this->seoTab(),
                        $this->feedTab(),
                    ]),
            ])
            ->statePath('data');
    }

    /* ------------------------------------------------------------------ */
    /* Tabs */
    /* ------------------------------------------------------------------ */

    private function generalTab(): Tab
    {
        return Tab::make('General')
            ->schema([
                Section::make('Switches')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Blog enabled')
                            ->helperText('The master switch. Off: /blog and every article 404, the Blog link leaves the header, and no article reaches the sitemap or the feed. Drafts and published posts are all kept — nothing is deleted.'),
                    ]),

                Section::make('The index page')
                    ->columns(2)
                    ->schema([
                        TextInput::make('blog_title')
                            ->label('Heading')
                            ->maxLength(120)
                            ->helperText('Shown at the top of /blog.'),
                        TextInput::make('posts_per_page')
                            ->label('Posts per page')
                            ->numeric()->minValue(1)->maxValue(100)->required(),
                        Textarea::make('blog_intro')
                            ->label('Intro paragraph')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Optional. Sits under the heading on /blog.'),
                    ]),

                Section::make('What each article shows')
                    ->columns(2)
                    ->schema([
                        Toggle::make('show_author')
                            ->label('Show the byline')
                            ->helperText('Articles with no author name never show one either way.'),
                        Toggle::make('show_reading_time')
                            ->label('Show reading time')
                            ->helperText('Estimated from the body at 200 words a minute.'),
                    ]),
            ]);
    }

    private function editorTab(): Tab
    {
        return Tab::make('Content & Editor')
            ->schema([
                Section::make('Writing')
                    ->schema([
                        Select::make('default_content_mode')
                            ->label('New articles open in')
                            ->options([
                                Article::MODE_EDITOR => 'Rich editor',
                                Article::MODE_HTML => 'HTML mode',
                            ])
                            ->required()
                            ->helperText('Either mode can be chosen per article; this only sets the starting point.'),
                    ]),

                Section::make('Pasted HTML')
                    ->schema([
                        Toggle::make('sanitize_html')
                            ->label('Sanitize pasted HTML')
                            ->helperText('OFF (the default) renders HTML mode exactly as pasted, so embeds — video, forms, widgets — work. ON strips <script> tags, on* handlers and javascript: links when the page is rendered; what you pasted is still stored untouched, so this is reversible. Turn it ON if anyone other than a trusted admin will ever write articles.'),
                    ]),

                Section::make('Per-article CSS')
                    ->columns(2)
                    ->schema([
                        Toggle::make('allow_custom_css')
                            ->label('Allow a CSS box per article')
                            ->helperText('The CSS is printed inside <head> on that article only.'),
                        TextInput::make('css_max_bytes')
                            ->label('CSS size limit (bytes)')
                            ->numeric()->minValue(256)->maxValue(65535)->required()
                            ->helperText('Anything longer is trimmed on save.'),
                    ]),
            ]);
    }

    private function mediaTab(): Tab
    {
        return Tab::make('Media & Storage')
            ->schema([
                Section::make('Where images are stored')
                    ->schema([
                        Select::make('storage_driver')
                            ->label('Storage')
                            ->live()
                            ->required()
                            ->options([
                                'local' => 'This server',
                                's3' => 'S3-compatible bucket',
                            ])
                            ->helperText(BlogSettings::s3Available()
                                ? 'Existing images keep working after a switch — every image remembers the disk it was written to. Only images pasted into HTML by absolute URL stay pointing at the old location.'
                                : 'S3 needs the Flysystem S3 driver, which is not installed. Run "composer require league/flysystem-aws-s3-v3" first — this is the one part of the blog that a setting cannot supply.')
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    if ($value === 's3' && ! BlogSettings::s3Available()) {
                                        $fail('The S3 driver is not installed. Run: composer require league/flysystem-aws-s3-v3');
                                    }
                                };
                            }),
                    ]),

                Section::make('Uploads')
                    ->columns(2)
                    ->schema([
                        TextInput::make('max_image_mb')
                            ->label('Maximum image size (MB)')
                            ->numeric()->minValue(1)->maxValue(50)->required(),
                        TextInput::make('allowed_ext')
                            ->label('Allowed extensions')
                            ->required()
                            ->helperText('Comma-separated. SVG and anything executable are always refused, whatever is typed here — the media folder is served straight off the web root.'),
                    ]),

                Section::make('This server')
                    ->visible(fn (Get $get): bool => $get('storage_driver') !== 's3')
                    ->columns(2)
                    ->schema([
                        TextInput::make('local_root')
                            ->label('Folder')
                            ->required()
                            ->helperText('Absolute path. Must be inside the web root and writable by PHP.'),
                        TextInput::make('local_url')
                            ->label('Public base URL')
                            ->required()
                            ->url()
                            ->helperText('Absolute URL that serves that folder.'),
                    ]),

                Section::make('S3 bucket')
                    ->description('The bucket must be public-read: article images are loaded straight by the browser, unlike CV files which are streamed through an authorised route.')
                    ->visible(fn (Get $get): bool => $get('storage_driver') === 's3')
                    ->columns(2)
                    ->schema([
                        TextInput::make('s3_key')
                            ->label('Access key ID')
                            ->password()
                            ->revealable()
                            ->autocomplete(false),
                        TextInput::make('s3_secret')
                            ->label('Secret access key')
                            ->password()
                            ->revealable()
                            ->autocomplete(false)
                            ->helperText('Encrypted before it is stored.'),
                        TextInput::make('s3_bucket')->label('Bucket'),
                        TextInput::make('s3_region')->label('Region'),
                        TextInput::make('s3_endpoint')
                            ->label('Endpoint')
                            ->helperText('Only for S3-compatible providers (R2, Spaces, MinIO). Leave empty for AWS.'),
                        TextInput::make('s3_url')
                            ->label('Public base URL')
                            ->helperText('The bucket or CDN domain images are served from.'),
                        Toggle::make('s3_path_style')
                            ->label('Use path-style endpoints')
                            ->helperText('Needed by MinIO and some S3-compatible hosts.'),
                    ]),
            ]);
    }

    private function seoTab(): Tab
    {
        return Tab::make('SEO defaults')
            ->schema([
                Section::make('Fallbacks')
                    ->description('Used only where an article leaves its own SEO fields empty. An article always wins over these.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('meta_title_suffix')
                            ->label('Title suffix')
                            ->maxLength(80)
                            ->placeholder(' — '.config('app.name'))
                            ->helperText('Appended to every article title in <title> and og:title.'),
                        TextInput::make('default_robots')
                            ->label('Robots')
                            ->maxLength(60)
                            ->placeholder('index,follow')
                            ->helperText('Applied to articles that do not set their own.'),
                        Textarea::make('default_meta_description')
                            ->label('Meta description')
                            ->rows(3)
                            ->maxLength(320)
                            ->columnSpanFull()
                            ->helperText('Last resort only — an article falls back to its excerpt first.'),
                        TextInput::make('default_og_image')
                            ->label('Share image URL')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->placeholder(asset('images/og-default.png'))
                            ->helperText('Absolute URL to a 1200×630 image, used when an article has neither its own share image nor a cover.'),
                    ]),
            ]);
    }

    /* ------------------------------------------------------------------ */
    private function feedTab(): Tab
    {
        return Tab::make('Feed')
            ->schema([
                Section::make('RSS')
                    ->description('Readers subscribe at '.route('blog.feed').'. The feed carries each article\'s summary and a link rather than the whole body — always valid, small, and it brings people to the page, where an article\'s own styling applies.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('feed_enabled')
                            ->label('Publish an RSS feed')
                            ->columnSpanFull()
                            ->helperText('Off: the feed URL 404s and the autodiscovery link leaves every blog page. The blog itself stays up.'),
                        TextInput::make('feed_title')
                            ->label('Feed title')
                            ->maxLength(120),
                        TextInput::make('feed_limit')
                            ->label('Articles in the feed')
                            ->numeric()->minValue(1)->maxValue(200)->required()
                            ->helperText('The newest N articles.'),
                        Textarea::make('feed_description')
                            ->label('Feed description')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /* Save */
    /* ------------------------------------------------------------------ */

    public function save(): void
    {
        $data = $this->form->getState();

        BlogSettings::setEnabled((bool) ($data['enabled'] ?? true));
        BlogSettings::put('posts_per_page', max(1, (int) ($data['posts_per_page'] ?? 12)));
        BlogSettings::put('blog_title', trim((string) ($data['blog_title'] ?? '')));
        BlogSettings::put('blog_intro', trim((string) ($data['blog_intro'] ?? '')));
        BlogSettings::put('show_author', (bool) ($data['show_author'] ?? true));
        BlogSettings::put('show_reading_time', (bool) ($data['show_reading_time'] ?? true));

        BlogSettings::put('default_content_mode', (string) ($data['default_content_mode'] ?? Article::MODE_EDITOR));
        BlogSettings::put('sanitize_html', (bool) ($data['sanitize_html'] ?? false));
        BlogSettings::put('allow_custom_css', (bool) ($data['allow_custom_css'] ?? true));
        BlogSettings::put('css_max_bytes', max(256, (int) ($data['css_max_bytes'] ?? 8192)));

        BlogSettings::put('feed_enabled', (bool) ($data['feed_enabled'] ?? true));
        BlogSettings::put('feed_title', trim((string) ($data['feed_title'] ?? '')));
        BlogSettings::put('feed_description', trim((string) ($data['feed_description'] ?? '')));
        BlogSettings::put('feed_limit', max(1, min(200, (int) ($data['feed_limit'] ?? 20))));

        // Stored untrimmed — a title suffix normally starts with its separator's space.
        BlogSettings::put('storage_driver', ($data['storage_driver'] ?? 'local') === 's3' ? 's3' : 'local');
        BlogSettings::put('max_image_mb', max(1, (int) ($data['max_image_mb'] ?? 5)));
        BlogSettings::put('allowed_ext', trim((string) ($data['allowed_ext'] ?? '')));
        BlogSettings::put('local_root', trim((string) ($data['local_root'] ?? '')));
        BlogSettings::put('local_url', trim((string) ($data['local_url'] ?? '')));
        BlogSettings::setS3Key((string) ($data['s3_key'] ?? ''));
        BlogSettings::setS3Secret((string) ($data['s3_secret'] ?? ''));
        BlogSettings::put('s3_region', trim((string) ($data['s3_region'] ?? '')));
        BlogSettings::put('s3_bucket', trim((string) ($data['s3_bucket'] ?? '')));
        BlogSettings::put('s3_endpoint', trim((string) ($data['s3_endpoint'] ?? '')));
        BlogSettings::put('s3_url', trim((string) ($data['s3_url'] ?? '')));
        BlogSettings::put('s3_path_style', (bool) ($data['s3_path_style'] ?? false));

        BlogSettings::put('meta_title_suffix', (string) ($data['meta_title_suffix'] ?? ''));
        BlogSettings::put('default_meta_description', trim((string) ($data['default_meta_description'] ?? '')));
        BlogSettings::put('default_og_image', trim((string) ($data['default_og_image'] ?? '')));
        BlogSettings::put('default_robots', trim((string) ($data['default_robots'] ?? '')));

        Notification::make()->title('Blog settings saved')->success()->send();
    }

    /**
     * Write a probe file, read it back and delete it.
     *
     * Worth a button: with the credentials in the database rather than in a
     * deploy, a typo would otherwise only surface the first time somebody
     * uploaded an image. It also answers the shared-hosting question of
     * whether PHP can actually write into the web root.
     */
    public function testStorage(): void
    {
        BlogSettings::forgetDisks();
        BlogSettings::registerDisks();

        $disk = BlogSettings::storageDisk();
        $path = 'articles/.probe-'.Str::lower(Str::random(10)).'.txt';

        try {
            Storage::disk($disk)->put($path, 'ok', 'public');

            if (Storage::disk($disk)->get($path) !== 'ok') {
                throw new \RuntimeException('The file was written but read back empty.');
            }

            $url = BlogSettings::mediaUrl($disk, $path);
            Storage::disk($disk)->delete($path);

            Notification::make()
                ->title('Storage is working')
                ->body('Wrote, read and deleted a test file on "'.$disk.'". Public URLs look like '.$url)
                ->success()
                ->persistent()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Storage is not usable')
                ->body(Str::limit($e->getMessage(), 300))
                ->danger()
                ->persistent()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save settings')
                ->action('save')
                ->keyBindings(['mod+s']),

            Action::make('test_storage')
                ->label('Test storage connection')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action('testStorage'),
        ];
    }
}
