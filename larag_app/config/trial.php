<?php

return [

    /*
    | Default free-trial allowance.
    */
    'credits' => (int) env('TRIAL_CREDITS', 50),
    'days' => (int) env('TRIAL_DAYS', 30),

    /*
    | The verification link is valid for this many minutes.
    */
    'verify_ttl_minutes' => (int) env('TRIAL_VERIFY_TTL', 120),

    /*
    | Minimum seconds between verification emails to the same address, enforced
    | independently of the per-IP throttle (anti mail-bomb).
    */
    'resend_cooldown_seconds' => (int) env('TRIAL_RESEND_COOLDOWN', 300),

    /*
    | Lifetime hard caps on how many trial grants a single site domain / IP may
    | ever create (a NEW email beyond the cap is rejected). 0 = unlimited.
    */
    'max_per_site' => (int) env('TRIAL_MAX_PER_SITE', 3),
    'max_per_ip' => (int) env('TRIAL_MAX_PER_IP', 5),

    /*
    | Soft blocklists (mirrors the addons project). Comma-separated env values.
    | Requests whose domain / IP appear here are rejected by the trial.client guard.
    */
    'blocked' => [
        'domains' => array_filter(explode(',', (string) env('TRIAL_BLOCKED_DOMAINS', ''))),
        'ips' => array_filter(explode(',', (string) env('TRIAL_BLOCKED_IPS', ''))),
    ],

    /*
    | Require the "WordPress" User-Agent heuristic on claim requests (deters
    | naive scripted abuse). Disable for local testing via env.
    */
    'require_wordpress_ua' => (bool) env('TRIAL_REQUIRE_WP_UA', true),

];
