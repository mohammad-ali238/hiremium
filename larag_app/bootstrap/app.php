<?php

use App\Exceptions\Api\ApiException;
use App\Http\Middleware\AuthorizeAiRequest;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureDeviceCookie;
use App\Http\Middleware\EnsureUserPlan;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetPermissionsTeam;
use App\Http\Middleware\TrackDeviceSignals;
use App\Http\Middleware\VerifyTrialClient;
use App\Support\AccountSignals;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            // Scope spatie permission checks to the user's workspace on every web
            // request (incl. Livewire AJAX updates, which bypass route middleware).
            SetPermissionsTeam::class,
            EnsureDeviceCookie::class,
            TrackDeviceSignals::class,
        ]);

        // The device cookie is a random token we only ever hash — no need to encrypt,
        // and keeping it plain lets the registration guard read it reliably.
        $middleware->encryptCookies(except: [AccountSignals::DEVICE_COOKIE]);

        $middleware->alias([
            'subscription.active' => EnsureActiveSubscription::class,
            'ai.auth' => AuthorizeAiRequest::class,
            'trial.client' => VerifyTrialClient::class,
            'workspace' => SetPermissionsTeam::class,
            'user.plan' => EnsureUserPlan::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ApiException $e) {
            return $e->render();
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'unauthenticated',
                        'message' => 'Invalid or missing API key.',
                    ],
                ], 401);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'validation_failed',
                        'message' => 'The given data was invalid.',
                        'errors' => $e->errors(),
                    ],
                ], 422);
            }
        });
    })
    ->create()
    // The document root (old public/ contents: index.php, build/, images/, ...)
    // lives one level above the app directory, not in larag_app/public.
    ->usePublicPath(dirname(__DIR__, 2));
