<?php

namespace App\Services\Tools\Tools;

use App\Models\Job;
use App\Services\Tools\Contracts\ToolDefinition;
use App\Services\Tools\Stages\BuildReportStage;
use App\Services\Tools\Stages\ExtractTextStage;
use App\Services\Tools\Stages\ParseResumeStage;
use App\Services\Tools\Stages\ScoreMatchStage;
use App\Support\ToolSettings;

/**
 * Free tool #1 — Resume Job Match Checker.
 *
 * A visitor describes a job (the same structured fields recruiters fill in
 * /app/jobs), uploads one CV, and gets the recruiter-grade report: match score,
 * reasoning, strengths, concerns, matched and missing skills.
 *
 * Its two AI stages (`parse`, `match`) can each be pinned to their own provider
 * + model in Free Tools settings; empty inherits the global AI Settings.
 */
class ResumeJobMatchTool implements ToolDefinition
{
    public function slug(): string
    {
        return 'resume-job-match-checker';
    }

    public function name(): string
    {
        return 'Resume Job Match Checker';
    }

    public function menuLabel(): string
    {
        return 'Resume Job Match Checker';
    }

    public function tagline(): string
    {
        return 'Paste the job, upload one resume, and see how well it matches — score, reasons, matched and missing skills.';
    }

    public function ctaLabel(): string
    {
        return 'Check the match';
    }

    public function iconPath(): string
    {
        // Document with a check badge.
        return 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z';
    }

    /**
     * The recruiter job form minus `status` — a free-tool visitor is describing
     * a role, not managing a pipeline.
     */
    public function fields(): array
    {
        return [
            'title' => [
                'label' => 'Job title',
                'type' => 'text',
                'placeholder' => 'e.g. Senior Laravel Developer',
                'rules' => ['required', 'string', 'min:3', 'max:200'],
                'width' => 'full',
            ],
            'description' => [
                'label' => 'Job description',
                'type' => 'textarea',
                'rows' => 10,
                'help' => 'Responsibilities, requirements, tech stack — the more detail, the more accurate the match.',
                'rules' => ['required', 'string', 'min:50', 'max:20000'],
                'width' => 'full',
            ],
            'employment_type' => [
                'label' => 'Employment type',
                'type' => 'select',
                'options' => Job::employmentTypes(),
                'rules' => ['required', 'string'],
                'width' => 'half',
            ],
            'location_type' => [
                'label' => 'Work mode',
                'type' => 'select',
                'options' => Job::locationTypes(),
                'rules' => ['required', 'string'],
                'width' => 'half',
            ],
            'location' => [
                'label' => 'Location',
                'type' => 'text',
                'placeholder' => 'e.g. Dubai, UAE',
                'rules' => ['nullable', 'string', 'max:200'],
                'width' => 'half',
            ],
            'min_experience_years' => [
                'label' => 'Minimum experience (years)',
                'type' => 'number',
                'min' => 0,
                'max' => 60,
                'rules' => ['nullable', 'integer', 'min:0', 'max:60'],
                'width' => 'half',
            ],
            'education' => [
                'label' => 'Education',
                'type' => 'text',
                'placeholder' => "e.g. Bachelor's in Computer Science",
                'rules' => ['nullable', 'string', 'max:200'],
                'width' => 'full',
            ],
            'must_have_skills' => [
                'label' => 'Must-have skills',
                'type' => 'tags',
                'help' => 'Press Enter after each skill.',
                'rules' => ['array', 'max:20'],
                'item_rules' => ['string', 'max:60'],
                'width' => 'full',
            ],
            'nice_to_have_skills' => [
                'label' => 'Nice-to-have skills',
                'type' => 'tags',
                'rules' => ['array', 'max:20'],
                'item_rules' => ['string', 'max:60'],
                'width' => 'full',
            ],
        ];
    }

    public function files(): array
    {
        return [
            'resume' => [
                'label' => 'Resume / CV',
                'required' => true,
                'extensions' => (array) config('tools.defaults.allowed_ext', ['pdf', 'doc', 'docx', 'txt']),
                // Never advertised in the UI — it only shapes the error message.
                'max_mb' => ToolSettings::maxFileMb(),
            ],
        ];
    }

    public function aiStages(): array
    {
        return [
            'parse' => 'Resume parsing',
            'match' => 'Match scoring',
        ];
    }

    public function stages(): array
    {
        return [
            ExtractTextStage::class,
            ParseResumeStage::class,
            ScoreMatchStage::class,
            BuildReportStage::class,
        ];
    }

    public function resultView(): string
    {
        return 'tools.results.resume-job-match';
    }

    public function emailSubject(): string
    {
        return 'Your resume match result is ready';
    }

    public function seoDefaults(): array
    {
        return [
            'title' => 'Free Resume Job Match Checker — score any resume against a job | Hiremium',
            'description' => 'Upload a resume, describe the job, and get an AI match score with reasons, matched and missing skills. Free, no account needed.',
            'keywords' => 'resume match checker, cv job match, ats resume score, free resume checker, ai resume screening',
        ];
    }
}
