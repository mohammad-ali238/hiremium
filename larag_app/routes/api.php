<?php

use App\Http\Controllers\Api\V1\AsyncMatchingScoreController;
use App\Http\Controllers\Api\V1\AsyncParseResumeController;
use App\Http\Controllers\Api\V1\JobStatusController;
use App\Http\Controllers\Api\V1\MatchingScoreController;
use App\Http\Controllers\Api\V1\ResumeParseController;
use App\Http\Controllers\Api\V1\Trial\TrialRequestController;
use App\Http\Controllers\Api\V1\Trial\TrialStatusController;
use App\Http\Controllers\Billing\LemonSqueezyWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// LemonSqueezy webhook — unauthenticated + CSRF-free (verified by HMAC signature
// inside the controller). Lives outside the sanctum group on purpose.
Route::post('/webhooks/lemonsqueezy', LemonSqueezyWebhookController::class)
    ->name('webhooks.lemonsqueezy');

// Free-trial claim + status — public (guarded by the trial.client fingerprint
// middleware). The claim (which sends an email) is tightly throttled; status
// polling gets its own looser limiter since the plugin polls every ~10s.
Route::prefix('v1/trial')->middleware('trial.client')->group(function () {
    Route::post('/request', TrialRequestController::class)
        ->middleware('throttle:trial')
        ->name('api.v1.trial.request');

    Route::post('/status', TrialStatusController::class)
        ->middleware('throttle:trial-status')
        ->name('api.v1.trial.status');
});

// Sync AI endpoints — accept EITHER a Sanctum subscription token OR a free-trial
// api_key (resolved to a CreditSource by the ai.auth middleware).
Route::prefix('v1')->middleware(['ai.auth', 'throttle:api'])->group(function () {
    Route::post('/matching-score', MatchingScoreController::class)
        ->name('api.v1.matching-score');

    Route::post('/parse-resume', ResumeParseController::class)
        ->name('api.v1.parse-resume');
});

// Subscription-only endpoints (async dispatch, user, job status).
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    Route::prefix('v1')->group(function () {

        // Async dispatch endpoints — same subscription gate as before.
        Route::middleware('subscription.active')->group(function () {
            Route::post('/matching-score/async', AsyncMatchingScoreController::class)
                ->name('api.v1.matching-score.async');

            Route::post('/parse-resume/async', AsyncParseResumeController::class)
                ->name('api.v1.parse-resume.async');
        });

        // Job status polling — no subscription check (user can poll after credits are spent).
        Route::get('/jobs/{job}', JobStatusController::class)
            ->whereUuid('job')
            ->name('api.v1.jobs.show');
    });
});
