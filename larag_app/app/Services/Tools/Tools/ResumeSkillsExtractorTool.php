<?php

namespace App\Services\Tools\Tools;

use App\Services\Tools\Contracts\ToolDefinition;
use App\Services\Tools\Stages\BuildSkillsReportStage;
use App\Services\Tools\Stages\ExtractSkillsStage;
use App\Services\Tools\Stages\ExtractTextStage;
use App\Services\Tools\Stages\ParseResumeStage;
use App\Support\SkillsSettings;
use App\Support\ToolSettings;

/**
 * Free tool #4 — Resume Skills Extractor.
 *
 * A visitor uploads one CV and gets the skills profile behind it: the industry
 * they work in, their core and soft skills, the tools they use, and a short
 * read on who this candidate is. Resume only — no job to describe.
 *
 * TWO AI calls. The parse turns the file into the structured resume the app
 * uses everywhere (and is what the "View resume" popup shows); the extraction
 * reads the whole CV and produces the profile. Both stages can be pinned to
 * their own provider + model in Free Tools settings; empty inherits AI Settings
 * → Resume Parsing AI and → Resume Skills Extractor AI respectively.
 */
class ResumeSkillsExtractorTool implements ToolDefinition
{
    public function slug(): string
    {
        return 'resume-skills-extractor';
    }

    public function name(): string
    {
        return 'Resume Skills Extractor';
    }

    public function menuLabel(): string
    {
        return 'Resume Skills Extractor';
    }

    public function tagline(): string
    {
        return 'Upload a CV and get the skills behind it — industry, core and soft skills, and a short read on the candidate.';
    }

    public function ctaLabel(): string
    {
        return 'Extract Skills';
    }

    public function iconPath(): string
    {
        // Sparkles — "pull the signal out".
        return 'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z';
    }

    /** Resume only — everything on the report is inferred from the file. */
    public function fields(): array
    {
        return [];
    }

    public function files(): array
    {
        return [
            'resume' => [
                'label' => 'Resume / CV',
                'required' => true,
                'extensions' => (array) config('tools.defaults.allowed_ext', ['pdf', 'doc', 'docx', 'txt']),
                'max_mb' => ToolSettings::maxFileMb(),
            ],
        ];
    }

    public function aiStages(): array
    {
        return [
            'parse' => 'Resume parsing',
            SkillsSettings::STAGE => 'Skills extraction',
        ];
    }

    public function stages(): array
    {
        return [
            ExtractTextStage::class,
            ParseResumeStage::class,
            ExtractSkillsStage::class,
            BuildSkillsReportStage::class,
        ];
    }

    public function resultView(): string
    {
        return 'tools.results.resume-skills-extractor';
    }

    public function emailSubject(): string
    {
        return 'Your resume skills report is ready';
    }

    public function seoDefaults(): array
    {
        return [
            'title' => 'Free Resume Skills Extractor — pull the skills out of any CV | Hiremium',
            'description' => 'Upload a resume and instantly see the candidate\'s industry, core skills, soft skills and a short profile. Free, no account needed.',
            'keywords' => 'resume skills extractor, cv skills extractor, extract skills from resume, resume parser, skills from cv, free resume analyser',
        ];
    }
}
