<?php

use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\Billing\OrdersController;
use App\Http\Controllers\Billing\SubscriptionController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CvFileController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Trial\TrialVerifyController;
use App\Http\Middleware\NoIndex;
use App\Livewire\Activity\Log;
use App\Livewire\Candidates\Compare;
use App\Livewire\Dashboard;
use App\Livewire\Jobs\Form;
use App\Livewire\Team\Members;
use App\Livewire\Tools\Index;
use App\Livewire\Tools\Result;
use App\Livewire\Tools\Show;
use App\Livewire\UiKit;
use App\Livewire\Uploads\Create;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/features/match-scoring', [PageController::class, 'featuresMatching'])->name('features.matching');
Route::get('/features/resume-parsing', [PageController::class, 'featuresParsing'])->name('features.parsing');
Route::redirect('/features', '/features/match-scoring')->name('features');
Route::get('/docs', [PageController::class, 'docs'])->name('docs');
Route::get('/how-it-works', [PageController::class, 'howItWorks'])->name('how-it-works');
Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [ContactController::class, 'send'])
    ->middleware('throttle:3,1')
    ->name('contact.send');

Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/refund-policy', [PageController::class, 'refund'])->name('refund');
Route::get('/help', [PageController::class, 'help'])->name('help');

// Literal segments first: /blog/feed.xml and /blog/category/x must not be
// swallowed by the article slug route (same discipline as the tools group).
Route::get('/blog', [ArticleController::class, 'index'])->name('blog.index');
Route::get('/blog/feed.xml', [ArticleController::class, 'feed'])->name('blog.feed');
Route::get('/blog/category/{slug}', [ArticleController::class, 'category'])->name('blog.category')
    ->where('slug', '[a-z0-9-]+');
Route::get('/blog/{slug}', [ArticleController::class, 'show'])->name('blog.show')
    ->where('slug', '[a-z0-9-]+');

Route::get('/sitemap.xml', [PageController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [PageController::class, 'robots'])->name('robots');

// Free Tools — public, email-confirmed AI tools (no account, no credits).
// `/result/{id}/{uuid}` is declared BEFORE `/{tool}` so a result link can never
// be swallowed by the tool-slug route. Both parts must match the same row: the
// id alone is guessable, and the pair is what makes a shared link specific.
Route::prefix(config('tools.route_prefix', 'tools'))->name('tools.')->group(function () {
    // The pages themselves are cheap, but they are the only public entry point
    // to an AI form — a generous per-IP ceiling costs an honest visitor nothing
    // and takes the edge off a scraper.
    Route::get('/', Index::class)->middleware('throttle:60,1')->name('index');
    // A result link is meant to be shared with a person, never indexed: the
    // header backs up the noindex meta for crawlers that never read the HTML.
    Route::get('/result/{id}/{uuid}', Result::class)
        ->whereNumber('id')
        ->middleware(['throttle:30,1', NoIndex::class])
        ->name('result');
    Route::get('/{tool}', Show::class)->middleware('throttle:60,1')->name('show');
});

// Team-invite acceptance (guest-accessible — a new invitee has no account yet).
Route::get('/invite/{token}', [InviteController::class, 'show'])->name('invite.show');
Route::post('/invite/{token}', [InviteController::class, 'accept'])
    ->middleware('throttle:10,1')->name('invite.accept');

// Legacy dashboard URL now lives in the recruiter workspace. Keep the name so
// existing route('dashboard') references (login redirect, etc.) resolve.
// Redirect via the NAMED route: Route::redirect('/dashboard', '/app') treats
// '/app' as domain-root-relative, which 404s when the app lives in a
// subdirectory (it sent fresh logins to https://dev.com/app).
Route::get('/dashboard', fn () => redirect()->route('app.dashboard'))->name('dashboard');

// Admin "login as" (impersonation). The start route consumes a single-use
// hand-off the admin-only Filament action just wrote to the session, so there is
// no shareable URL; the stop route only exists inside an impersonated session.
// Neither requires a verified email — the admin may need to inspect an account
// that has not verified yet.
Route::middleware('auth')->group(function () {
    Route::get('/impersonate/start', [ImpersonationController::class, 'start'])
        ->middleware('throttle:10,1')->name('impersonate.start');
    Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])
        ->middleware('throttle:10,1')->name('impersonate.stop');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Hiremium recruiter workspace (new).
    // The `workspace` middleware scopes spatie permission checks to the user's
    // workspace (spatie teams mode); `user.plan` guarantees the account holder
    // always has a plan (auto-assigning Free when they have none).
    Route::middleware(['workspace', 'user.plan'])->group(function () {
        Route::get('/app', Dashboard::class)->name('app.dashboard');

        Route::get('/app/jobs', App\Livewire\Jobs\Index::class)->name('app.jobs');
        Route::get('/app/jobs/create', Form::class)->name('app.jobs.create');
        Route::get('/app/jobs/{job}/edit', Form::class)->name('app.jobs.edit');

        Route::get('/app/candidates', App\Livewire\Candidates\Index::class)->name('app.candidates');
        Route::get('/app/candidates/{match}', App\Livewire\Candidates\Show::class)->name('app.candidates.show');
        Route::get('/app/shortlists', App\Livewire\Candidates\Index::class)->name('app.shortlists');
        Route::get('/app/compare', Compare::class)->name('app.compare');
        Route::get('/app/insights', App\Livewire\Insights\Index::class)->name('app.insights');
        Route::get('/app/reports', App\Livewire\Reports\Index::class)->name('app.reports');
        Route::get('/app/reports/{job}', App\Livewire\Reports\Show::class)->name('app.reports.show');

        Route::get('/app/upload', Create::class)->name('app.upload');
        // withTrashed(): a deleted batch renders a friendly "this batch was deleted" page (links from
        // notifications/bookmarks keep working) instead of a 404.
        Route::get('/app/batches/{batch}', App\Livewire\Uploads\Show::class)->name('app.batches.show')->withTrashed();
        Route::get('/app/cv/{file}', [CvFileController::class, 'show'])->name('app.cv');

        Route::get('/app/team', Members::class)->name('app.team');
        Route::get('/app/activity', Log::class)->name('app.activity');
        Route::get('/app/usage', App\Livewire\Usage\Index::class)->name('app.usage');
        Route::get('/app/settings', App\Livewire\Settings\Index::class)->name('app.settings');
        Route::get('/app/api', App\Livewire\Api\Index::class)->name('app.api');
        Route::get('/app/billing', App\Livewire\Billing\Index::class)->name('app.billing');
        Route::get('/app/avatar/{user}', [AvatarController::class, 'show'])->name('app.avatar');

        // Component gallery — dev only, never exposed in production.
        if (! app()->isProduction()) {
            Route::get('/app/ui-kit', UiKit::class)->name('app.ui-kit');
        }
    });

    // Start a LemonSqueezy checkout (new subscription) or apply an upgrade.
    Route::post('/billing/checkout', [CheckoutController::class, 'start'])
        ->middleware('throttle:10,1')
        ->name('checkout.start');

    // Self-serve subscription management (cancel keeps access until period end; resume un-cancels).
    Route::post('/billing/subscription/cancel', [SubscriptionController::class, 'cancel'])
        ->middleware('throttle:6,1')
        ->name('subscription.cancel');
    Route::post('/billing/subscription/resume', [SubscriptionController::class, 'resume'])
        ->middleware('throttle:6,1')
        ->name('subscription.resume');
    Route::post('/billing/subscription/payment-method', [SubscriptionController::class, 'updatePayment'])
        ->middleware('throttle:6,1')
        ->name('subscription.payment-method');

    Route::get('/my-orders', OrdersController::class)->name('my-orders');

    // API key management requires a verified email; unverified accounts must not mint keys.
    Route::post('/api-token', [ApiTokenController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('api-token.store');
    Route::delete('/api-token', [ApiTokenController::class, 'destroy'])
        ->middleware('throttle:5,1')
        ->name('api-token.destroy');
});

// Profile management moved into the recruiter workspace at /app/settings.

// Free-trial email verification link (signed URL). The signature is validated
// inside the controller so an expired/invalid link can bounce back to the site
// with a friendly "expired" status instead of dead-ending on a 403 here.
Route::get('/trial/verify/{trial}', TrialVerifyController::class)
    ->name('trial.verify');

require __DIR__.'/auth.php';
