<?php

use App\Services\Tools\Tools\AtsScoreCheckerTool;
use App\Services\Tools\Tools\JobDescriptionAnalyzerTool;
use App\Services\Tools\Tools\ResumeExperienceExtractorTool;
use App\Services\Tools\Tools\ResumeJobMatchTool;
use App\Services\Tools\Tools\ResumeSkillsExtractorTool;

/*
|--------------------------------------------------------------------------
| Free Tools (public, email-confirmed)
|--------------------------------------------------------------------------
|
| Anonymous visitors run a single-file AI tool, confirm ownership of their
| email by opening the result link, and the result renders on a public,
| shareable page. Everything here is a DEFAULT — the admin overrides each
| value in Filament → Free Tools → Settings (App\Support\ToolSettings).
|
| Free-tool data is deliberately isolated from workspace data: its own
| `aitools_*` tables, no credits, no wallet, no usage logs.
|
*/

return [

    // Master default before the admin ever saves Free Tools settings.
    'enabled' => (bool) env('HIREMIUM_TOOLS_ENABLED', true),

    // URL prefix: /tools/{slug} and /tools/result/{uuid}.
    'route_prefix' => 'tools',

    // Registered tool definitions. Adding a tool = add its class here.
    'tools' => [
        ResumeJobMatchTool::class,
        AtsScoreCheckerTool::class,
        JobDescriptionAnalyzerTool::class,
        ResumeSkillsExtractorTool::class,
        ResumeExperienceExtractorTool::class,
    ],

    'defaults' => [
        // Runs allowed per canonical email per calendar day.
        'daily_per_email' => (int) env('HIREMIUM_TOOLS_DAILY_PER_EMAIL', 5),

        // Runs allowed per browser (device cookie) per calendar day. Same signal
        // registration uses: a second email address typed on the same browser
        // cannot reset the daily allowance.
        'daily_per_device' => (int) env('HIREMIUM_TOOLS_DAILY_PER_DEVICE', 5),

        // Submissions allowed per IP per minute (same shape as login/register).
        // A burst guard, not an allowance: it says how fast, never how many.
        'per_ip_per_minute' => (int) env('HIREMIUM_TOOLS_PER_IP_PER_MINUTE', 6),

        // Runs allowed per IP per calendar day (0 = no ceiling). The ceiling
        // that still stands when someone changes both email address and
        // browser — and the bluntest of the three, since everyone behind one
        // office or mobile-network address shares it.
        'daily_per_ip' => (int) env('HIREMIUM_TOOLS_DAILY_PER_IP', 5),

        // Site-wide ceiling on AI-processed runs per day (token spend guard).
        'site_daily_cap' => (int) env('HIREMIUM_TOOLS_SITE_DAILY_CAP', 300),

        // Result link lifetime while it has never been opened. Opening it once
        // clears the expiry — the link then lives until the retention sweep.
        'expiry_hours' => (int) env('HIREMIUM_TOOLS_EXPIRY_HOURS', 72),

        // Hard retention: every run older than this is purged, opened or not.
        'retention_days' => (int) env('HIREMIUM_TOOLS_RETENTION_DAYS', 90),

        // Upload limits for tool files (never advertised in the UI — the size
        // exists only so an oversized file gets a clear error).
        'max_file_mb' => (int) env('HIREMIUM_TOOLS_MAX_FILE_MB', 2),
        'allowed_ext' => ['pdf', 'doc', 'docx', 'txt'],

        // "Didn't get the email?" resends allowed per run.
        'resend_max' => (int) env('HIREMIUM_TOOLS_RESEND_MAX', 3),

        // Total resends allowed to one email address per day, across every run
        // it submitted. The per-run cap alone lets a fresh run buy a fresh batch
        // of resends, so this is the ceiling that actually bounds how many
        // emails one address can be made to receive.
        'resend_max_per_email' => (int) env('HIREMIUM_TOOLS_RESEND_MAX_PER_EMAIL', 5),

        // Store the raw AI request preview + response on each log event.
        'store_raw_payloads' => true,

        // Offer the uploaded CV for download on the public result page. Off:
        // the link is shareable, so the file would travel with it.
        'allow_cv_download' => false,

        // Show the optional marketing opt-in checkbox on tool forms.
        'marketing_opt_in' => true,

        // Nightly housekeeping (mirrors the two manual buttons in settings).
        'auto_cleanup_expired' => true,
        'auto_cleanup_retention' => true,
    ],

    // Text chunks sent to the AI are capped so a huge CV or job post cannot
    // blow up the token bill of a free run.
    'limits' => [
        'job_text_chars' => 12000,
        'resume_text_chars' => 24000,
    ],

    // Private disk prefix for tool uploads (same disk as recruiter CVs).
    'storage_prefix' => 'tools',

    // How long a submission waits its turn at the quota gate before being told
    // the tools are busy. The gate serialises "check the ceilings" with "write
    // the run", so a burst cannot overshoot every limit at once.
    'quota_lock_wait_seconds' => (int) env('HIREMIUM_TOOLS_QUOTA_LOCK_WAIT', 5),
];
