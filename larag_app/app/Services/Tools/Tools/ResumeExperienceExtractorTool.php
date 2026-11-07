<?php

namespace App\Services\Tools\Tools;

use App\Services\Tools\Contracts\ToolDefinition;
use App\Services\Tools\Stages\BuildExperienceReportStage;
use App\Services\Tools\Stages\ExtractExperienceStage;
use App\Services\Tools\Stages\ExtractTextStage;
use App\Services\Tools\Stages\ParseResumeStage;
use App\Support\ExperienceSettings;
use App\Support\ToolSettings;

/**
 * Free tool #5 — Resume Experience Extractor.
 *
 * A visitor uploads one CV and gets their career at a glance: every role on one
 * line with how long it lasted, the overall total, the sector they work in, the
 * seniority they operate at, and a short read on who this candidate is. Resume
 * only — no job to describe.
 *
 * TWO AI calls. The parse turns the file into the structured resume the app
 * uses everywhere (and is what the "View resume" popup shows); the extraction
 * makes the judgement calls. The role list and every duration are computed from
 * the parsed dates in code, not asked of a model. Both stages can be pinned to
 * their own provider + model in Free Tools settings; empty inherits AI Settings
 * → Resume Parsing AI and → Resume Experience Extractor AI respectively.
 */
class ResumeExperienceExtractorTool implements ToolDefinition
{
    public function slug(): string
    {
        return 'resume-experience-extractor';
    }

    public function name(): string
    {
        return 'Resume Experience Extractor';
    }

    public function menuLabel(): string
    {
        return 'Resume Experience Extractor';
    }

    public function tagline(): string
    {
        return 'Upload a CV and get the career behind it — every role with its length, the overall total, sector and seniority.';
    }

    public function ctaLabel(): string
    {
        return 'Extract Experience';
    }

    public function iconPath(): string
    {
        // Briefcase — a career history.
        return 'M20.25 14.15v4.25c0 1.114-.848 2.062-1.96 2.153-.837.07-1.68.117-2.53.14a3 3 0 01-2.56 1.307h-2.4a3 3 0 01-2.56-1.307c-.85-.023-1.693-.07-2.53-.14A2.108 2.108 0 013.75 18.4v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z';
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
            ExperienceSettings::STAGE => 'Experience extraction',
        ];
    }

    public function stages(): array
    {
        return [
            ExtractTextStage::class,
            ParseResumeStage::class,
            ExtractExperienceStage::class,
            BuildExperienceReportStage::class,
        ];
    }

    public function resultView(): string
    {
        return 'tools.results.resume-experience-extractor';
    }

    public function emailSubject(): string
    {
        return 'Your resume experience report is ready';
    }

    public function seoDefaults(): array
    {
        return [
            'title' => 'Free Resume Experience Extractor — pull the skills out of any CV | Hiremium',
            'description' => 'Upload a resume and instantly see the candidate\'s industry, core skills, soft skills and a short profile. Free, no account needed.',
            'keywords' => 'resume skills extractor, cv skills extractor, extract skills from resume, resume parser, skills from cv, free resume analyser',
        ];
    }
}
