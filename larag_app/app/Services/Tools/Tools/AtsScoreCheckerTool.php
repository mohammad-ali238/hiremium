<?php

namespace App\Services\Tools\Tools;

use App\Services\Tools\Contracts\ToolDefinition;
use App\Services\Tools\Stages\AtsScoreStage;
use App\Services\Tools\Stages\BuildAtsReportStage;
use App\Services\Tools\Stages\ExtractTextStage;
use App\Support\ToolSettings;

/**
 * Free tool #2 — ATS Score Checker.
 *
 * A visitor uploads one CV and gets an ATS-style resume score with details:
 * overall score, four sub-scores, detected context, and four feedback lists
 * (critical issues, strengths, what's lacking, how to improve). Resume only —
 * no job to describe.
 *
 * ONE AI call: the extracted text is scored directly (no separate parse); a
 * scanned/image CV is scored from the image by a vision model in that same
 * single call. The `score` stage can be pinned to its own provider + model in
 * Free Tools settings; empty inherits AI Settings → ATS Scoring AI.
 */
class AtsScoreCheckerTool implements ToolDefinition
{
    public function slug(): string
    {
        return 'ats-score-checker';
    }

    public function name(): string
    {
        return 'ATS Score Checker';
    }

    public function menuLabel(): string
    {
        return 'ATS Score Checker';
    }

    public function tagline(): string
    {
        return 'Upload your resume and see how well it reads to an applicant tracking system — score, strengths, and exactly what to fix.';
    }

    public function ctaLabel(): string
    {
        return 'Check ATS Score';
    }

    public function iconPath(): string
    {
        // Document with a magnifier — "scan / check".
        return 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z';
    }

    /** Resume only — the AI infers industry, seniority and target roles. */
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
            'score' => 'ATS scoring',
        ];
    }

    public function stages(): array
    {
        return [
            ExtractTextStage::class,
            AtsScoreStage::class,
            BuildAtsReportStage::class,
        ];
    }

    public function resultView(): string
    {
        return 'tools.results.ats-score-checker';
    }

    public function emailSubject(): string
    {
        return 'Your ATS resume score is ready';
    }

    public function seoDefaults(): array
    {
        return [
            'title' => 'Free ATS Resume Score Checker — see how your CV reads to an ATS | Hiremium',
            'description' => 'Upload your resume and get an instant ATS score with sub-scores, strengths and clear fixes. Free, no account needed.',
            'keywords' => 'ats score checker, ats resume checker, resume scanner, cv ats score, free ats checker, resume checker',
        ];
    }
}
