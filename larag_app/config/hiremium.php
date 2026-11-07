<?php

/*
|--------------------------------------------------------------------------
| Hiremium recruiter-SaaS switches
|--------------------------------------------------------------------------
|
| Every environment-specific choice lives here and is env-driven, so the app
| can run on shared hosting today and move to a VPS later by changing .env
| only — no code changes. Defaults below are the shared-hosting profile.
|
*/

return [

    'queue' => [
        // 'cron'   = shared hosting: the scheduler runs a short-lived worker.
        // 'daemon' = VPS: a long-running queue:work / Horizon supervises jobs.
        'worker_mode' => env('HIREMIUM_QUEUE_MODE', 'cron'),

        // Seconds a cron-triggered worker runs before exiting (frees the slot).
        'worker_max_seconds' => (int) env('HIREMIUM_QUEUE_MAX_SECONDS', 50),

        // Connection + queue name used for all Hiremium background work.
        'connection' => env('HIREMIUM_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
        'name' => env('HIREMIUM_QUEUE_NAME', 'cv'),

        'tries' => (int) env('HIREMIUM_QUEUE_TRIES', 3),
    ],

    'ai' => [
        // <= this many CVs of an upload are processed immediately (live lane);
        // anything beyond it spills onto the deferred, rate-limited batch lane.
        'batch_threshold' => (int) env('HIREMIUM_AI_BATCH_THRESHOLD', 500),

        // How many batch-lane (overflow) CVs become due per minute — staggers a
        // large import so the live lane finishes first and the provider API is
        // not hit in one burst.
        'batch_lane_per_minute' => (int) env('HIREMIUM_BATCH_LANE_PER_MINUTE', 60),

        // A provider batch pending longer than this is considered lost; its files
        // are pulled back to the live lane by hiremium:resume-processing.
        // (Gemini/OpenAI batch windows are 24h — 26h gives headroom.)
        'batch_stale_hours' => (int) env('HIREMIUM_BATCH_STALE_HOURS', 26),

        // Hard cap of CVs accepted per upload batch (Free-plan value; plans may
        // override downward via their own limit column).
        'max_cvs_per_batch' => (int) env('HIREMIUM_MAX_CVS_PER_BATCH', 25),

        // Master switch: allow account-level BYO provider keys (admin toggle).
        'byo_keys_enabled' => (bool) env('HIREMIUM_BYO_KEYS', false),

        // Credits charged per candidate match (parse folded into the match).
        'credit_cost_per_match' => (int) env('HIREMIUM_CREDIT_COST_MATCH', 1),

        // Minimum parse confidence (0-1) a CV must carry before it is scored.
        // Below it the matching-score AI call is skipped and the CV is reported
        // as unreadable instead of ranked on data we cannot vouch for.
        'min_parse_confidence' => (float) env('HIREMIUM_MIN_PARSE_CONFIDENCE', 0.75),
    ],

    'credits' => [
        // Notify the workspace owner once their balance drops to/below this.
        'low_threshold' => (int) env('HIREMIUM_CREDITS_LOW_THRESHOLD', 10),
    ],

    'billing' => [
        // Days a paid subscription keeps its plan after the period ends while we
        // wait for the provider: a renewal webhook that is simply late, or a card
        // retry during LemonSqueezy dunning. The customer is NOT downgraded to
        // Free inside this window. 0 disables the grace entirely.
        'renewal_grace_days' => (int) env('HIREMIUM_RENEWAL_GRACE_DAYS', 5),
    ],

    'storage' => [
        // Disk holding CV files. 'cv_local' now; switch to 'cv_s3' on a VPS.
        'cv_disk' => env('HIREMIUM_CV_DISK', 'cv_local'),

        // Signed-URL lifetime (seconds) for private CV downloads/views.
        'signed_url_ttl' => (int) env('HIREMIUM_SIGNED_URL_TTL', 300),

        // Grace period before purging files of a deleted candidate/resume.
        'retention_grace_hours' => (int) env('HIREMIUM_RETENTION_GRACE_HOURS', 24),
    ],

    'uploads' => [
        'max_file_mb' => (int) env('HIREMIUM_MAX_FILE_MB', 10),
        'allowed_ext' => ['pdf', 'docx', 'doc', 'txt', 'zip'],

        // Hard intake ceilings — platform-level safety limits that plans can NOT
        // raise. Enforced inside the queued extraction job; never advertised
        // to users before upload (the batches page explains any trimming).
        'zip_max_files' => (int) env('HIREMIUM_ZIP_MAX_FILES', 200),           // CVs taken from a single ZIP
        'zip_max_total_mb' => (int) env('HIREMIUM_ZIP_MAX_TOTAL_MB', 300),     // uncompressed bytes per ZIP (zip-bomb guard)
        'max_cvs_per_upload' => (int) env('HIREMIUM_MAX_CVS_PER_UPLOAD', 1000), // across ALL files + ZIPs in one upload
        'csv_max_rows' => (int) env('HIREMIUM_CSV_MAX_ROWS', 1000),            // candidate rows taken from a single CSV import
        'extract_stale_hours' => (int) env('HIREMIUM_EXTRACT_STALE_HOURS', 2),   // 'extracting' batch older than this with no result → failed (re-upload)
    ],

    'progress' => [
        // 'poll' = shared hosting (Livewire polls the batch record).
        // 'broadcast' = VPS with websockets later.
        'transport' => env('HIREMIUM_PROGRESS_TRANSPORT', 'poll'),
        'poll_interval_ms' => (int) env('HIREMIUM_PROGRESS_POLL_MS', 2500),
    ],

    // Score bands (admin-editable copy also lives on AiSetting; this is the
    // code-level fallback + default).
    'scoring' => [
        'bands' => [
            'excellent' => (int) env('HIREMIUM_BAND_EXCELLENT', 90),
            'good' => (int) env('HIREMIUM_BAND_GOOD', 75),
            'fair' => (int) env('HIREMIUM_BAND_FAIR', 60),
        ],
    ],
];
