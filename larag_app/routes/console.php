<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Cron-driven queue worker (shared hosting)
|--------------------------------------------------------------------------
|
| One system cron runs `php artisan schedule:run` every minute. In 'cron'
| worker mode the scheduler launches a short-lived queue worker that drains
| the Hiremium queue and exits (no daemon). On a VPS, set HIREMIUM_QUEUE_MODE
| to 'daemon' and run a real `queue:work`/Horizon instead — this block no-ops.
|
*/
if (config('hiremium.queue.worker_mode') === 'cron') {
    Schedule::command(sprintf(
        'queue:work %s --queue=%s,default --stop-when-empty --max-time=%d --tries=%d --no-interaction',
        config('hiremium.queue.connection'),
        config('hiremium.queue.name'),
        config('hiremium.queue.worker_max_seconds'),
        config('hiremium.queue.tries'),
    ))
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();
}

// Provider Batch API poller: advances submitted batches and imports completed
// results for the async overflow lane.
Schedule::command('hiremium:poll-batches')->everyFiveMinutes()->withoutOverlapping();

// Roll over free subscriptions whose 30-day period ended (expire + re-assign
// Free), which is what refreshes their monthly AI credits.
Schedule::command('hiremium:reset-monthly-credits')->dailyAt('00:10')->withoutOverlapping();

// Resume-from-interruption: re-dispatch unfinished resume files for batches
// still processing (covers a cron worker that died mid-run).
Schedule::command('hiremium:resume-processing')->everyFiveMinutes()->withoutOverlapping();

// Retention cleanup for deleted candidates/resumes past the grace window
// (implemented in Phase 5/7).
// Schedule::command('hiremium:purge-expired-files')->hourly()->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Free Tools
|--------------------------------------------------------------------------
|
| Stages normally run inside the visitor's open result page. This picks up the
| runs that stopped moving because the tab was closed or a worker died, and
| resumes them from the stage they reached.
|
*/
Schedule::command('hiremium:tools-sweep')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Retention: unopened links die at their expiry, everything dies at 90 days.
// Each half honours its own switch in Free Tools settings.
Schedule::command('hiremium:tools-cleanup')
    ->dailyAt('03:20')
    ->withoutOverlapping()
    ->runInBackground();
