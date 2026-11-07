<?php

namespace App\Http\Controllers\Trial;

use App\Http\Controllers\Controller;
use App\Models\TrialCredit;
use App\Services\Trial\TrialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /trial/verify/{trial} — the email verification link.
 *
 * The signature is validated manually (not via the `signed` middleware) so that
 * an expired/invalid link can be bounced back to the originating WordPress site
 * with a `workeera_trial_status=expired` flag (the plugin then shows a friendly
 * message) instead of dead-ending on a 403 here. On success the grant is
 * activated and the user is redirected back with a one-time token.
 */
class TrialVerifyController extends Controller
{
    public function __construct(private readonly TrialService $trials) {}

    public function __invoke(Request $request, string $trial): RedirectResponse|View
    {
        $record = TrialCredit::query()->where('uuid', $trial)->first();

        if (! $record) {
            abort(404);
        }

        $target = $record->site_url;

        // Expired or tampered link.
        if (! $request->hasValidSignature()) {
            if (! empty($target)) {
                return redirect()->away($this->appendQuery($target, [
                    'workeera_trial' => $record->uuid,
                    'workeera_trial_status' => 'expired',
                ]));
            }

            return response()->view('trial.verify-failed', ['trial' => $record], 403);
        }

        // Valid — activate (idempotent).
        if ($record->status !== TrialCredit::STATUS_VERIFIED) {
            $record = $this->trials->activate($record);
        }

        if (! empty($target)) {
            return redirect()->away($this->appendQuery($target, [
                'workeera_trial' => $record->uuid,
                'workeera_trial_token' => $record->redirect_token,
                'workeera_trial_status' => 'success',
            ]));
        }

        return view('trial.verified', ['trial' => $record]);
    }

    /**
     * Append query params to a URL, preserving any existing query string.
     *
     * @param  array<string,string>  $params
     */
    private function appendQuery(string $url, array $params): string
    {
        $sep = str_contains($url, '?') ? '&' : '?';

        return $url.$sep.http_build_query($params);
    }
}
