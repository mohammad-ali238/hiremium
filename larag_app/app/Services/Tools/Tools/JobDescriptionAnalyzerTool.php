<?php

namespace App\Services\Tools\Tools;

use App\Services\Jd\SeniorityLevels;
use App\Services\Tools\Contracts\ToolDefinition;
use App\Services\Tools\Stages\BuildJdReportStage;
use App\Services\Tools\Stages\JdAnalyzeStage;

/**
 * Free tool #3 — Job Description Analyzer.
 *
 * A visitor pastes a job title and description and picks the seniority level
 * they are hiring for. ONE AI call returns a quality score plus what the post
 * is lacking, a level-mismatch note (only when there is one), concrete
 * improvements, and the must-have and soft skills the posting should ask for.
 *
 * The first tool with NO file upload — everything is text.
 */
class JobDescriptionAnalyzerTool implements ToolDefinition
{
    public function slug(): string
    {
        return 'job-description-analyzer';
    }

    public function name(): string
    {
        return 'Job Description Analyzer';
    }

    public function menuLabel(): string
    {
        return 'Job Description Analyzer';
    }

    public function tagline(): string
    {
        return 'Paste a job title and description, pick the level, and see how strong the posting is — score, gaps, and the skills it should ask for.';
    }

    public function ctaLabel(): string
    {
        return 'Analyze Job Description';
    }

    public function iconPath(): string
    {
        // Clipboard with a checklist.
        return 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z';
    }

    public function fields(): array
    {
        return [
            'title' => [
                'label' => 'Job title',
                'type' => 'text',
                'placeholder' => 'e.g. Senior Laravel Developer',
                'rules' => ['required', 'string', 'min:3', 'max:200'],
                'width' => 'half',
            ],
            'seniority' => [
                'label' => 'Seniority level',
                'type' => 'select',
                'options' => SeniorityLevels::options(),
                'help' => 'The description is judged against this level — a mismatch lowers the score.',
                'rules' => ['required', 'string'],
                'width' => 'half',
            ],
            'description' => [
                'label' => 'Job description',
                'type' => 'textarea',
                'rows' => 12,
                'help' => 'Paste the full posting — responsibilities, requirements, everything you would publish.',
                'rules' => ['required', 'string', 'min:50', 'max:20000'],
                'width' => 'full',
            ],
        ];
    }

    /** Text only — no upload. */
    public function files(): array
    {
        return [];
    }

    public function aiStages(): array
    {
        return [
            'analyze' => 'Job description analysis',
        ];
    }

    public function stages(): array
    {
        return [
            JdAnalyzeStage::class,
            BuildJdReportStage::class,
        ];
    }

    public function resultView(): string
    {
        return 'tools.results.job-description-analyzer';
    }

    public function emailSubject(): string
    {
        return 'Your job description analysis is ready';
    }

    public function seoDefaults(): array
    {
        return [
            'title' => 'Free Job Description Analyzer — score and improve any job post | Hiremium',
            'description' => 'Paste a job description, pick the seniority level, and get an instant quality score with gaps, fixes and the skills it should ask for. Free, no account needed.',
            'keywords' => 'job description analyzer, job post checker, job ad review, jd analyzer, free job description tool',
        ];
    }
}
