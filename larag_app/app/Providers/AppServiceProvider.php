<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Subscription;
use App\Observers\ArticleObserver;
use App\Observers\SubscriptionObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        JsonResource::withoutWrapping();

        Article::observe(ArticleObserver::class);
        Subscription::observe(SubscriptionObserver::class);

        // Stricter password policy in production; min 8 stays for local/testing.
        Password::defaults(function () {
            return $this->app->isProduction()
                ? Password::min(8)->letters()->mixedCase()->numbers()
                : Password::min(8);
        });

        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $key = $user
                ? 'user:'.$user->getKey()
                : 'ip:'.$request->ip();

            return Limit::perMinute(60)->by($key)->response(function () {
                return response()->json([
                    'error' => [
                        'code' => 'rate_limited',
                        'message' => 'Too many requests. Please slow down.',
                    ],
                ], 429);
            });
        });

        // Free-trial claim (sends email) — tight, per-IP (mirrors the addons project).
        RateLimiter::for('trial', function (Request $request) {
            return Limit::perMinute(5)->by('trial|'.$request->ip())->response(function () {
                return response()->json([
                    'error' => [
                        'code' => 'rate_limited',
                        'message' => 'Too many requests. Please try again in a minute.',
                    ],
                ], 429);
            });
        });

        // Free-trial status polling — looser (the plugin polls every ~10s).
        RateLimiter::for('trial-status', function (Request $request) {
            return Limit::perMinute(20)->by('trialstatus|'.$request->ip())->response(function () {
                return response()->json([
                    'error' => [
                        'code' => 'rate_limited',
                        'message' => 'Too many requests. Please slow down.',
                    ],
                ], 429);
            });
        });
    }
}
