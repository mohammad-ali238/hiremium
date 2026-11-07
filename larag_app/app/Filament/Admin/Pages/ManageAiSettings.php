<?php

namespace App\Filament\Admin\Pages;

use App\Models\AiSetting;
use App\Services\Ai\MatchPrompt;
use App\Services\Ats\AtsPrompt;
use App\Services\Experience\ExperienceProfilePrompt;
use App\Services\Jd\JdPrompt;
use App\Services\Matching\CareerGapGate;
use App\Services\Matching\ExperienceCalculator;
use App\Services\ResumeParsing\ParseConfidence;
use App\Services\Skills\SkillProfilePrompt;
use App\Support\AtsSettings;
use App\Support\ExperienceSettings;
use App\Support\JdSettings;
use App\Support\SkillsSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * @property Schema $form
 */
class ManageAiSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.admin.pages.manage-ai-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'AI Settings';

    protected static ?string $navigationLabel = 'AI Settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = AiSetting::current();

        $this->form->fill([
            'openai_api_key' => $settings->openai_api_key,
            'gemini_api_key' => $settings->gemini_api_key,
            'byo_keys_enabled' => $settings->byo_keys_enabled,
            'default_provider' => $settings->default_provider,
            'default_model' => $settings->default_model,
            'temperature' => $settings->temperature,
            'max_tokens' => $settings->max_tokens,
            'enforce_min_experience' => (bool) ($settings->enforce_min_experience ?? true),
            'min_experience_cap_score' => (int) ($settings->min_experience_cap_score ?? 59),
            'enforce_must_have_skills' => (bool) ($settings->enforce_must_have_skills ?? true),
            'must_have_cap_score' => (int) ($settings->must_have_cap_score ?? 74),
            'must_have_soft_cap_min_skills' => (int) ($settings->must_have_soft_cap_min_skills ?? 4),
            'must_have_soft_cap_score' => (int) ($settings->must_have_soft_cap_score ?? 88),
            'enforce_career_gap' => (bool) ($settings->enforce_career_gap ?? true),
            'career_gap_cap_score' => (int) ($settings->career_gap_cap_score ?? CareerGapGate::DEFAULT_CAP),
            'parsing_ai_provider' => $settings->parsing_ai_provider,
            'parsing_openai_model' => $settings->parsing_openai_model,
            'parsing_gemini_model' => $settings->parsing_gemini_model,
            'parsing_system_prompt' => $settings->parsing_system_prompt,
            'parsing_temperature' => $settings->parsing_temperature,
            'parsing_credit_cost_text' => $settings->parsing_credit_cost_text,
            'parsing_credit_cost_vision' => $settings->parsing_credit_cost_vision,
            'parsing_max_file_size_kb' => $settings->parsing_max_file_size_kb,
            'trial_credit_cost_match' => $settings->trial_credit_cost_match,
            'trial_credit_cost_parse_text' => $settings->trial_credit_cost_parse_text,
            'trial_credit_cost_parse_vision' => $settings->trial_credit_cost_parse_vision,

            // ATS Scoring AI lives in a JSON settings row, not on AiSetting.
            'ats_provider' => AtsSettings::provider(),
            'ats_openai_model' => AtsSettings::openaiModel(),
            'ats_gemini_model' => AtsSettings::geminiModel(),
            'ats_temperature' => AtsSettings::temperature(),
            'ats_max_tokens' => AtsSettings::maxTokens(),

            // Job Description Analyzer AI — also a JSON settings row.
            'jd_provider' => JdSettings::provider(),
            'jd_openai_model' => JdSettings::openaiModel(),
            'jd_gemini_model' => JdSettings::geminiModel(),
            'jd_temperature' => JdSettings::temperature(),
            'jd_max_tokens' => JdSettings::maxTokens(),

            // Resume Skills Extractor AI — also a JSON settings row.
            'skills_provider' => SkillsSettings::provider(),
            'skills_openai_model' => SkillsSettings::openaiModel(),
            'skills_gemini_model' => SkillsSettings::geminiModel(),
            'skills_temperature' => SkillsSettings::temperature(),
            'skills_max_tokens' => SkillsSettings::maxTokens(),

            // Resume Experience Extractor AI — also a JSON settings row.
            'experience_provider' => ExperienceSettings::provider(),
            'experience_openai_model' => ExperienceSettings::openaiModel(),
            'experience_gemini_model' => ExperienceSettings::geminiModel(),
            'experience_temperature' => ExperienceSettings::temperature(),
            'experience_max_tokens' => ExperienceSettings::maxTokens(),
        ]);
    }

    /** Keys on the form that are stored in AtsSettings, not on the AiSetting row. */
    private const ATS_KEYS = ['ats_provider', 'ats_openai_model', 'ats_gemini_model', 'ats_temperature', 'ats_max_tokens'];

    /** Keys on the form that are stored in JdSettings, not on the AiSetting row. */
    private const JD_KEYS = ['jd_provider', 'jd_openai_model', 'jd_gemini_model', 'jd_temperature', 'jd_max_tokens'];

    /** Keys on the form that are stored in SkillsSettings, not on the AiSetting row. */
    private const SKILLS_KEYS = ['skills_provider', 'skills_openai_model', 'skills_gemini_model', 'skills_temperature', 'skills_max_tokens'];

    /** Keys on the form that are stored in ExperienceSettings, not on the AiSetting row. */
    private const EXPERIENCE_KEYS = ['experience_provider', 'experience_openai_model', 'experience_gemini_model', 'experience_temperature', 'experience_max_tokens'];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('API keys')
                    ->description('Stored encrypted at rest.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('openai_api_key')
                            ->label('OpenAI API key')
                            ->password()
                            ->revealable(),
                        TextInput::make('gemini_api_key')
                            ->label('Gemini API key')
                            ->password()
                            ->revealable(),
                        Toggle::make('byo_keys_enabled')
                            ->label('Allow account-level BYO provider keys')
                            ->helperText('When on, recruiter accounts may use their own OpenAI/Gemini keys instead of platform credits.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Matching Score AI')
                    ->description('Candidate matching for the recruiter pipeline, the free match tool and the /api/v1/matching-score endpoint. The score is NOT the model\'s: the model reports findings and code scores them against fixed weights (Skills 50, Experience 25, Education 10, Nice-to-have 10, Communication & motivation 5 — Education 15 and Communication 10 when the job lists no nice-to-have skills). The model\'s own score is stored per match for review but never shown to recruiters. The prompt is fixed in code and shown read-only below.')
                    ->columns(2)
                    ->schema([
                        Select::make('default_provider')
                            ->label('Provider')
                            ->options([
                                AiSetting::PROVIDER_OPENAI => 'OpenAI',
                                AiSetting::PROVIDER_GEMINI => 'Gemini',
                            ])
                            ->required(),
                        TextInput::make('default_model')
                            ->label('Model')
                            ->required()
                            ->helperText('e.g. gpt-4o-mini, gpt-4o, gemini-1.5-flash, gemini-1.5-pro'),
                        TextInput::make('temperature')
                            ->numeric()->step(0.05)->minValue(0)->maxValue(2)->required(),
                        TextInput::make('max_tokens')
                            ->numeric()->minValue(64)->maxValue(50000)->required(),
                        Toggle::make('enforce_min_experience')
                            ->label('Enforce job minimum experience')
                            ->helperText('The candidate\'s total experience is computed in code from their CV dates (overlaps merged, "Present" = today) and sent to the AI alongside the job\'s minimum. When ON, a candidate below the minimum has their score hard-capped at the value below, whatever the model returned.')
                            ->live(),
                        TextInput::make('min_experience_cap_score')
                            ->label('Score cap when below minimum experience')
                            ->numeric()->minValue(0)->maxValue(100)->required()
                            ->helperText('Default 59 → lands in the "Low fit / Not recommended" band so under-experienced candidates never rank as Recommended.')
                            ->visible(fn ($get) => (bool) $get('enforce_min_experience')),
                        Toggle::make('enforce_must_have_skills')
                            ->label('Enforce must-have skills')
                            ->helperText('The model returns a verdict per must-have skill on the job (met / not met, with evidence), judging synonyms, abbreviations and other-language spellings semantically. Code then counts coverage; when ON, any unmet must-have hard-caps the score at the value below.')
                            ->live(),
                        TextInput::make('must_have_cap_score')
                            ->label('Hard cap — 2+ must-haves missing (or any miss on a short list)')
                            ->numeric()->minValue(0)->maxValue(100)->required()
                            ->helperText('Default 74 → keeps the candidate out of the "Recommended" band (which starts at 75).')
                            ->visible(fn ($get) => (bool) $get('enforce_must_have_skills')),
                        TextInput::make('must_have_soft_cap_min_skills')
                            ->label('"Long list" threshold — job lists MORE than this many must-haves')
                            ->numeric()->minValue(1)->maxValue(50)->required()
                            ->helperText('Default 4. On a long list, exactly ONE missing must-have gets the softer cap below instead of the hard cap.')
                            ->visible(fn ($get) => (bool) $get('enforce_must_have_skills')),
                        TextInput::make('must_have_soft_cap_score')
                            ->label('Soft cap — exactly 1 must-have missing on a long list')
                            ->numeric()->minValue(0)->maxValue(100)->required()
                            ->helperText('Default 88. Never lower than the hard cap.')
                            ->visible(fn ($get) => (bool) $get('enforce_must_have_skills')),
                        Toggle::make('enforce_career_gap')
                            ->label('Enforce career gap')
                            ->helperText('Read in code from the CV dates, never from the model: a break longer than '.ExperienceCalculator::GAP_MONTHS.' months between roles, or no current role with the most recent one ended more than '.ExperienceCalculator::GAP_MONTHS.' months ago. When ON, either case hard-caps the score at the value below.')
                            ->live(),
                        TextInput::make('career_gap_cap_score')
                            ->label('Score cap when a career gap is found')
                            ->numeric()->minValue(0)->maxValue(100)->required()
                            ->helperText('Default '.CareerGapGate::DEFAULT_CAP.' — still inside the "Recommended" band, so a gap holds a candidate back without ruling them out.')
                            ->visible(fn ($get) => (bool) $get('enforce_career_gap')),
                        Placeholder::make('match_prompt_preview')
                            ->label('Matching prompt (read-only) — the complete prompt the model receives')
                            ->columnSpanFull()
                            ->content(new HtmlString(
                                '<div class="text-xs text-gray-500 dark:text-gray-400 mb-2">Fixed in code and no longer editable: the scoring weights above depend on these exact findings, so wording that drifted from them would quietly break the score. The per-job details (title, required and nice-to-have skills, minimum experience) and the candidate\'s resume are added to each request.</div>'
                                .'<pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs leading-5 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">'
                                .e(MatchPrompt::system())
                                .'</pre>'
                            )),
                    ]),

                Section::make('Resume Parsing AI')
                    ->description('Settings for the /api/v1/parse-resume endpoint. Independent from matching-score.')
                    ->columns(2)
                    ->schema([
                        Select::make('parsing_ai_provider')
                            ->label('Provider')
                            ->options([
                                AiSetting::PROVIDER_OPENAI => 'OpenAI',
                                AiSetting::PROVIDER_GEMINI => 'Gemini',
                            ])
                            ->required(),
                        TextInput::make('parsing_temperature')
                            ->label('Temperature')
                            ->numeric()->step(0.05)->minValue(0)->maxValue(1)->required(),
                        TextInput::make('parsing_openai_model')
                            ->label('OpenAI model')
                            ->required()
                            ->helperText('Used for both text and vision modes. Recommended: gpt-4o-mini.'),
                        TextInput::make('parsing_gemini_model')
                            ->label('Gemini model')
                            ->required()
                            ->helperText('Used for both text and vision modes. Recommended: gemini-1.5-flash.'),
                        TextInput::make('parsing_credit_cost_text')
                            ->label('Credit cost (text extraction)')
                            ->numeric()->minValue(1)->maxValue(100)->required(),
                        TextInput::make('parsing_credit_cost_vision')
                            ->label('Credit cost (vision fallback)')
                            ->numeric()->minValue(1)->maxValue(100)->required(),
                        TextInput::make('parsing_max_file_size_kb')
                            ->label('Max file size (KB)')
                            ->numeric()->minValue(64)->maxValue(51200)->required()
                            ->columnSpanFull(),
                        Textarea::make('parsing_system_prompt')
                            ->rows(18)
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Must instruct the model to return the camelCase resume schema: fullName, email, phone, jobTitle, gender, dateOfBirth, websiteUrl, linkedinUrl, summary, experience[] (company, title, location, dates, description), education[] (institution, degree, field, location, YYYY-MM dates, grade), projects[], certificates[], awards[], expertise[] (auto-detected, max 5, level 1-5), languages[] (name + level 1-5), skills[]. Dates as YYYY-MM. Gender/DOB only when explicitly stated. A "confidence" key (0-1, the parser\'s own certainty about the document) is requested automatically unless this prompt already asks for one; a CV below '.ParseConfidence::format(ParseConfidence::minimum()).' confidence is reported as unreadable instead of being scored.'),
                    ]),

                Section::make('ATS Scoring AI')
                    ->description('Settings for the ATS Score Checker (a single AI call per resume). The prompt below is fixed in code and shown read-only. A vision-capable model is required so image / scanned CVs can still be scored in one call.')
                    ->columns(2)
                    ->schema([
                        Select::make('ats_provider')
                            ->label('Provider')
                            ->options([
                                AiSetting::PROVIDER_OPENAI => 'OpenAI',
                                AiSetting::PROVIDER_GEMINI => 'Gemini',
                            ])
                            ->required(),
                        TextInput::make('ats_temperature')
                            ->label('Temperature')
                            ->numeric()->step(0.05)->minValue(0)->maxValue(2)->required(),
                        TextInput::make('ats_openai_model')
                            ->label('OpenAI model')
                            ->required()
                            ->helperText('Must support vision for image / scanned CVs. Recommended: gpt-4o-mini.'),
                        TextInput::make('ats_gemini_model')
                            ->label('Gemini model')
                            ->required()
                            ->helperText('Must support vision for image / scanned CVs. Recommended: gemini-1.5-flash.'),
                        TextInput::make('ats_max_tokens')
                            ->label('Max tokens')
                            ->numeric()->minValue(256)->maxValue(50000)->required()
                            ->columnSpanFull()
                            ->helperText('The ATS report has four feedback lists, so allow more than the match score. Default 1500.'),
                        Placeholder::make('ats_prompt_preview')
                            ->label('ATS scoring prompt (read-only) — fixed in code')
                            ->columnSpanFull()
                            ->content(fn () => new HtmlString(
                                '<div class="text-xs text-gray-500 dark:text-gray-400 mb-2">This prompt defines the JSON output the ATS report depends on, so it is not editable here — only the provider, model, temperature and token limit above are. The resume text (or image) is added per request.</div>'
                                .'<pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs leading-5 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">'
                                .e(AtsPrompt::system())
                                .'</pre>'
                            )),
                    ]),

                Section::make('Job Description Analyzer AI')
                    ->description('Settings for the Job Description Analyzer (a single AI call per description). The prompt below is fixed in code and shown read-only.')
                    ->columns(2)
                    ->schema([
                        Select::make('jd_provider')
                            ->label('Provider')
                            ->options([
                                AiSetting::PROVIDER_OPENAI => 'OpenAI',
                                AiSetting::PROVIDER_GEMINI => 'Gemini',
                            ])
                            ->required(),
                        TextInput::make('jd_temperature')
                            ->label('Temperature')
                            ->numeric()->step(0.05)->minValue(0)->maxValue(2)->required(),
                        TextInput::make('jd_openai_model')
                            ->label('OpenAI model')
                            ->required()
                            ->helperText('Recommended: gpt-4o-mini.'),
                        TextInput::make('jd_gemini_model')
                            ->label('Gemini model')
                            ->required()
                            ->helperText('Recommended: gemini-1.5-flash.'),
                        TextInput::make('jd_max_tokens')
                            ->label('Max tokens')
                            ->numeric()->minValue(256)->maxValue(50000)->required()
                            ->columnSpanFull()
                            ->helperText('The report has four lists plus a level note. Default 1200.'),
                        Placeholder::make('jd_prompt_preview')
                            ->label('Job description prompt (read-only) — fixed in code')
                            ->columnSpanFull()
                            ->content(fn () => new HtmlString(
                                '<div class="text-xs text-gray-500 dark:text-gray-400 mb-2">This prompt defines the JSON output the report depends on, so it is not editable here. The job title, description and the selected seniority level are added per request.</div>'
                                .'<pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs leading-5 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">'
                                .e(JdPrompt::system())
                                .'</pre>'
                            )),
                    ]),

                Section::make('Resume Skills Extractor AI')
                    ->description('Settings for the Resume Skills Extractor\'s extraction call — the one that produces the industry, the skill lists and the short profile. Reading the file into a structured resume is the separate "Resume Parsing AI" above. The prompt below is fixed in code and shown read-only.')
                    ->columns(2)
                    ->schema([
                        Select::make('skills_provider')
                            ->label('Provider')
                            ->options([
                                AiSetting::PROVIDER_OPENAI => 'OpenAI',
                                AiSetting::PROVIDER_GEMINI => 'Gemini',
                            ])
                            ->required(),
                        TextInput::make('skills_temperature')
                            ->label('Temperature')
                            ->numeric()->step(0.05)->minValue(0)->maxValue(2)->required()
                            ->helperText('Low keeps the skill lists steady between runs. Default 0.20.'),
                        TextInput::make('skills_openai_model')
                            ->label('OpenAI model')
                            ->required()
                            ->helperText('Recommended: gpt-4o-mini.'),
                        TextInput::make('skills_gemini_model')
                            ->label('Gemini model')
                            ->required()
                            ->helperText('Recommended: gemini-1.5-flash.'),
                        TextInput::make('skills_max_tokens')
                            ->label('Max tokens')
                            ->numeric()->minValue(256)->maxValue(50000)->required()
                            ->columnSpanFull()
                            ->helperText('Three short lists plus a 2-3 sentence profile. Default 900.'),
                        Placeholder::make('skills_prompt_preview')
                            ->label('Skills extraction prompt (read-only) — fixed in code')
                            ->columnSpanFull()
                            ->content(fn () => new HtmlString(
                                '<div class="text-xs text-gray-500 dark:text-gray-400 mb-2">This prompt defines the JSON output the report depends on, so it is not editable here. The resume text is added per request.</div>'
                                .'<pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs leading-5 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">'
                                .e(SkillProfilePrompt::system())
                                .'</pre>'
                            )),
                    ]),

                Section::make('Resume Experience Extractor AI')
                    ->description('Settings for the Resume Experience Extractor\'s judgement call — industry, seniority and the short profile. The role list and every duration are computed in code from the parsed dates, so no model is asked to do arithmetic. Reading the file into a structured resume is the separate "Resume Parsing AI" above. The prompt below is fixed in code and shown read-only.')
                    ->columns(2)
                    ->schema([
                        Select::make('experience_provider')
                            ->label('Provider')
                            ->options([
                                AiSetting::PROVIDER_OPENAI => 'OpenAI',
                                AiSetting::PROVIDER_GEMINI => 'Gemini',
                            ])
                            ->required(),
                        TextInput::make('experience_temperature')
                            ->label('Temperature')
                            ->numeric()->step(0.05)->minValue(0)->maxValue(2)->required()
                            ->helperText('Low keeps the seniority call steady between runs. Default 0.20.'),
                        TextInput::make('experience_openai_model')
                            ->label('OpenAI model')
                            ->required()
                            ->helperText('Recommended: gpt-4o-mini.'),
                        TextInput::make('experience_gemini_model')
                            ->label('Gemini model')
                            ->required()
                            ->helperText('Recommended: gemini-1.5-flash.'),
                        TextInput::make('experience_max_tokens')
                            ->label('Max tokens')
                            ->numeric()->minValue(256)->maxValue(50000)->required()
                            ->columnSpanFull()
                            ->helperText('Three short fields plus a 2-3 sentence profile. Default 900.'),
                        Placeholder::make('experience_prompt_preview')
                            ->label('Experience extraction prompt (read-only) — fixed in code')
                            ->columnSpanFull()
                            ->content(fn () => new HtmlString(
                                '<div class="text-xs text-gray-500 dark:text-gray-400 mb-2">This prompt defines the JSON output the report depends on, so it is not editable here. The seniority ladder is the same one the Job Description Analyzer uses. The resume text is added per request.</div>'
                                .'<pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs leading-5 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">'
                                .e(ExperienceProfilePrompt::system())
                                .'</pre>'
                            )),
                    ]),

                Section::make('Free / Trial Credits')
                    ->description('Credit cost charged to FREE-TRIAL grants per operation (paid subscriptions use the costs above). Keep at 1 for "1 credit = 1 operation".')
                    ->columns(3)
                    ->schema([
                        TextInput::make('trial_credit_cost_match')
                            ->label('Match score')
                            ->numeric()->minValue(1)->maxValue(100)->required()
                            ->helperText('Credits per applicant-match call.'),
                        TextInput::make('trial_credit_cost_parse_text')
                            ->label('Resume parse (text)')
                            ->numeric()->minValue(1)->maxValue(100)->required()
                            ->helperText('Credits per text-extraction parse.'),
                        TextInput::make('trial_credit_cost_parse_vision')
                            ->label('Resume parse (vision)')
                            ->numeric()->minValue(1)->maxValue(100)->required()
                            ->helperText('Credits per image/PDF (vision) parse.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // The ATS group is stored in its own JSON settings row; keep those keys
        // out of the AiSetting column update or it would try to write columns
        // that do not exist.
        AtsSettings::put('provider', (string) ($data['ats_provider'] ?? AiSetting::PROVIDER_OPENAI));
        AtsSettings::put('openai_model', trim((string) ($data['ats_openai_model'] ?? '')));
        AtsSettings::put('gemini_model', trim((string) ($data['ats_gemini_model'] ?? '')));
        AtsSettings::put('temperature', (float) ($data['ats_temperature'] ?? 0.30));
        AtsSettings::put('max_tokens', max(256, (int) ($data['ats_max_tokens'] ?? 1500)));

        JdSettings::put('provider', (string) ($data['jd_provider'] ?? AiSetting::PROVIDER_OPENAI));
        JdSettings::put('openai_model', trim((string) ($data['jd_openai_model'] ?? '')));
        JdSettings::put('gemini_model', trim((string) ($data['jd_gemini_model'] ?? '')));
        JdSettings::put('temperature', (float) ($data['jd_temperature'] ?? 0.30));
        JdSettings::put('max_tokens', max(256, (int) ($data['jd_max_tokens'] ?? 1200)));

        SkillsSettings::put('provider', (string) ($data['skills_provider'] ?? AiSetting::PROVIDER_OPENAI));
        SkillsSettings::put('openai_model', trim((string) ($data['skills_openai_model'] ?? '')));
        SkillsSettings::put('gemini_model', trim((string) ($data['skills_gemini_model'] ?? '')));
        SkillsSettings::put('temperature', (float) ($data['skills_temperature'] ?? 0.20));
        SkillsSettings::put('max_tokens', max(256, (int) ($data['skills_max_tokens'] ?? 900)));

        ExperienceSettings::put('provider', (string) ($data['experience_provider'] ?? AiSetting::PROVIDER_OPENAI));
        ExperienceSettings::put('openai_model', trim((string) ($data['experience_openai_model'] ?? '')));
        ExperienceSettings::put('gemini_model', trim((string) ($data['experience_gemini_model'] ?? '')));
        ExperienceSettings::put('temperature', (float) ($data['experience_temperature'] ?? 0.20));
        ExperienceSettings::put('max_tokens', max(256, (int) ($data['experience_max_tokens'] ?? 900)));

        AiSetting::current()->update(array_diff_key($data, array_flip([...self::ATS_KEYS, ...self::JD_KEYS, ...self::SKILLS_KEYS, ...self::EXPERIENCE_KEYS])));

        Notification::make()
            ->title('AI settings saved')
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
