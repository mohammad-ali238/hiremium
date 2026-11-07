<?php

namespace App\Http\Controllers\Api\V1\Trial;

use App\Http\Controllers\Controller;
use App\Models\TrialCredit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/trial/status — the WordPress site polls this (by uuid) to learn
 * whether the email has been verified and to fetch its api_key. The api_key is
 * only returned to the ORIGINATING site (domain match), so submitting a uuid
 * alone never leaks a usable credential.
 */
class TrialStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => ['required', 'uuid'],
            'site_url' => ['nullable', 'url', 'max:255'],
            'redirect_token' => ['nullable', 'string', 'max:64'],
        ]);

        $trial = TrialCredit::query()->where('uuid', $data['uuid'])->first();

        if (! $trial) {
            return response()->json(['status' => 'not_found', 'verified' => false], 404);
        }

        $verified = $trial->status === TrialCredit::STATUS_VERIFIED;

        $payload = [
            'status' => $trial->status,
            'verified' => $verified,
            'uuid' => $trial->uuid,
            'credits_total' => (int) $trial->credits_total,
            'credits_used' => (int) $trial->credits_used,
            'credits_remaining' => $trial->credits_remaining,
            'expires_at' => optional($trial->expires_at)->toIso8601String(),
        ];

        // Hand back the api_key + recent usage only to the originating site, or
        // when the caller presents the one-time redirect_token from the link.
        $tokenOk = ! empty($data['redirect_token'])
            && ! empty($trial->redirect_token)
            && hash_equals((string) $trial->redirect_token, (string) $data['redirect_token']);

        if ($verified && ($this->siteMatches($trial, $request) || $tokenOk)) {
            // redirect_token is single-use: once it has released the key, burn it
            // so a leaked link (referer/logs) can't be replayed. The originating
            // site keeps working via the site-domain match.
            if ($tokenOk) {
                $trial->forceFill(['redirect_token' => null])->save();
            }

            $payload['api_key'] = $trial->api_key;
            $payload['recent_usage'] = $trial->usageLogs()
                ->latest('id')
                ->limit(20)
                ->get(['endpoint', 'credits_consumed', 'status', 'created_at'])
                ->map(fn ($row) => [
                    'endpoint' => $row->endpoint,
                    'credits' => (int) $row->credits_consumed,
                    'status' => $row->status,
                    'at' => optional($row->created_at)->toIso8601String(),
                ])
                ->all();
        }

        return response()->json($payload);
    }

    /**
     * Whether the request's reported site host matches the grant's stored host.
     */
    private function siteMatches(TrialCredit $trial, Request $request): bool
    {
        $savedHost = strtolower((string) parse_url((string) $trial->site_url, PHP_URL_HOST));
        if ($savedHost === '') {
            return true; // no site recorded at claim time — nothing to enforce.
        }

        $reqHost = strtolower((string) parse_url((string) $request->input('site_url'), PHP_URL_HOST));

        return $reqHost !== '' && $reqHost === $savedHost;
    }
}
