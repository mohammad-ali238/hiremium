<?php

namespace App\Filament\Admin\Resources\Articles\Schemas;

use App\Models\Article;
use App\Services\Storage\ArticleStorage;
use App\Support\BlogSettings;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('article')
                    ->columnSpanFull()
                    ->tabs([
                        self::contentTab(),
                        self::seoTab(),
                        self::advancedTab(),
                    ]),
            ]);
    }

    private static function contentTab(): Tab
    {
        return Tab::make('Content')
            ->schema([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            // Fill the slug from the title, but only while it is
                            // still empty: changing a published article's URL is
                            // a deliberate act, never a side effect of a retitle.
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                if (blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(190)
                            ->unique(ignoreRecord: true)
                            ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->helperText('The URL: /blog/your-slug. Lowercase letters, numbers and hyphens. Deleted articles keep their slug reserved.'),
                        Select::make('article_category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')->required()->maxLength(120),
                                Textarea::make('description')->rows(2)->maxLength(500),
                            ])
                            ->editOptionForm([
                                TextInput::make('name')->required()->maxLength(120),
                                Textarea::make('description')->rows(2)->maxLength(500),
                            ])
                            ->helperText('Optional. One category per article — that keeps each post on a single URL.'),
                        TextInput::make('author_name')
                            ->label('Author')
                            ->maxLength(120)
                            ->helperText('Shown as the byline. Left blank, no byline appears.'),
                        Textarea::make('excerpt')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Used on the index cards, in the RSS feed and as the meta description fallback. Leave blank and the opening of the article is used.'),
                    ]),

                Section::make('Cover image')
                    ->description('Shown on the blog index and used as the social share image when no other one is set.')
                    ->schema([
                        FileUpload::make('cover_path')
                            ->hiddenLabel()
                            ->image()
                            ->imagePreviewHeight('160')
                            ->disk(fn (): string => app(ArticleStorage::class)->disk())
                            ->directory('articles/covers')
                            ->visibility('public')
                            ->maxSize(BlogSettings::maxImageMb() * 1024)
                            ->acceptedFileTypes(ArticleStorage::acceptedMimeTypes())
                            // Name the file ourselves, from the DETECTED mime: the
                            // media folder is served straight off the web root, so
                            // the extension is what keeps an upload inert.
                            ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => (string) Str::uuid()
                                .'.'.(ArticleStorage::extensionForMime($file->getMimeType()) ?? 'bin'))
                            ->helperText('Up to '.BlogSettings::maxImageMb().' MB. Replacing the cover deletes the old file. You can also pick one from the Media panel below with "Set as cover".'),
                        TextInput::make('cover_alt')
                            ->label('Cover alt text')
                            ->maxLength(255)
                            ->helperText('Describes the image for screen readers and search engines.'),
                    ]),

                Section::make('Body')
                    ->schema([
                        ToggleButtons::make('content_mode')
                            ->label('Editor')
                            ->inline()
                            ->live()
                            ->default(BlogSettings::defaultContentMode())
                            ->options([
                                Article::MODE_EDITOR => 'Rich editor',
                                Article::MODE_HTML => 'HTML',
                            ])
                            ->helperText('Both versions are kept separately, so switching back and forth never overwrites the other one.'),

                        RichEditor::make('content_editor')
                            ->label('Body')
                            ->hiddenLabel()
                            ->fileAttachments(false)
                            ->visible(fn (Get $get): bool => $get('content_mode') !== Article::MODE_HTML)
                            ->columnSpanFull(),

                        CodeEditor::make('content_html')
                            ->label('Body (HTML)')
                            ->hiddenLabel()
                            ->language(Language::Html)
                            ->visible(fn (Get $get): bool => $get('content_mode') === Article::MODE_HTML)
                            ->columnSpanFull()
                            ->helperText('Pasted exactly as given. Tailwind utility classes will NOT work here — the site only compiles classes it finds in its own templates — so style this markup with inline styles or the CSS box on the Advanced tab.'),
                    ]),

                Section::make('Publishing')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options([
                                Article::STATUS_DRAFT => 'Draft',
                                Article::STATUS_PUBLISHED => 'Published',
                            ])
                            ->default(Article::STATUS_DRAFT)
                            ->required()
                            ->helperText('Drafts are visible to admins only; everyone else gets a 404.'),
                        DateTimePicker::make('published_at')
                            ->label('Publish at')
                            ->seconds(false)
                            ->helperText('Times are '.config('app.timezone').'. A future time schedules the post — it appears on its own, no cron needed. Left blank, publishing means now.'),
                    ]),
            ]);
    }

    private static function seoTab(): Tab
    {
        return Tab::make('SEO')
            ->schema([
                Section::make()
                    ->relationship('meta')
                    ->columns(2)
                    ->schema([
                        TextInput::make('meta_title')
                            ->maxLength(255)
                            ->helperText('Falls back to the article title. ~60 characters recommended.'),
                        TextInput::make('meta_keywords')
                            ->maxLength(255)
                            ->helperText('Comma-separated. Optional.'),
                        Textarea::make('meta_description')
                            ->rows(3)
                            ->maxLength(320)
                            ->columnSpanFull()
                            ->helperText('Falls back to the excerpt, then to the blog default.'),
                        TextInput::make('canonical_url')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Only set this when the article is a republication of a page that lives elsewhere.'),
                        TextInput::make('robots')
                            ->maxLength(60)
                            ->placeholder('index,follow'),
                        Select::make('schema_type')
                            ->options([
                                'BlogPosting' => 'BlogPosting',
                                'Article' => 'Article',
                                'NewsArticle' => 'NewsArticle',
                            ])
                            ->default('BlogPosting'),

                        TextInput::make('og_title')
                            ->label('Share title')
                            ->maxLength(255)
                            ->helperText('Falls back to the meta title.'),
                        Select::make('twitter_card')
                            ->label('Twitter card')
                            ->options([
                                'summary_large_image' => 'Large image',
                                'summary' => 'Summary',
                            ])
                            ->default('summary_large_image'),
                        Textarea::make('og_description')
                            ->label('Share description')
                            ->rows(2)
                            ->maxLength(320)
                            ->columnSpanFull(),
                        TextInput::make('og_image')
                            ->label('Share image URL')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Absolute URL to a 1200×630 image. Falls back to the cover image, then to the blog default.'),
                    ]),
            ]);
    }

    private static function advancedTab(): Tab
    {
        return Tab::make('Advanced')
            ->schema([
                Section::make('Custom CSS')
                    ->description('Printed inside <head> on this article only. Scope your selectors under #article-body so nothing leaks into the header, footer or any other page.')
                    ->visible(fn (): bool => BlogSettings::allowCustomCss())
                    ->schema([
                        CodeEditor::make('custom_css')
                            ->hiddenLabel()
                            ->language(Language::Css)
                            ->columnSpanFull()
                            ->helperText('Trimmed to '.number_format(BlogSettings::cssMaxBytes()).' bytes on save. "<" is stripped and @import is removed — an import would make every visitor call a third-party host.'),
                    ]),
            ]);
    }
}
