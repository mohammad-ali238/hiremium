<?php

namespace App\Http\Controllers\Api\V1\Trial;

use App\Http\Controllers\Controller;
use App\Mail\TrialVerification;
use App\Models\TrialCredit;
use App\Services\Trial\TrialService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * POST /api/v1/trial/request — claim a free trial for an email address.
 * Creates a pending grant (if the email is new) and emails a verification link.
 * Enforces "one trial per email" (exact + canonical) and a per-address email
 * cooldown so the verification email can't be used to flood an inbox.
 */
class TrialRequestController extends Controller
{
    public function __construct(private readonly TrialService $trials) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'site_url' => ['nullable', 'url', 'max:255'],
            'ip' => ['nullable', 'string', 'max:45'],
        ]);

        $email = strtolower(trim($data['email']));
        $canonical = $this->trials->canonicalize($email);

        $existing = TrialCredit::query()
            ->where('email', $email)
            ->orWhere('email_canonical', $canonical)
            ->first();

        if ($existing) {
            return $this->handleExisting($existing, $request, $data, $canonical);
        }

        // Anti-abuse: hard lifetime caps per site domain / per IP (0 = unlimited).
        if ($limit = $this->capExceeded($request, $data)) {
            return $limit;
        }

        // New email — create the pending grant, tolerating a race where a
        // concurrent request inserts the same address first.
        try {
            $trial = $this->trials->createPending(
                $email,
                $data['site_url'] ?? null,
                $request->ip(),
                $data['ip'] ?? null,
            );
        } catch (UniqueConstraintViolationException $e) {
            $raced = TrialCredit::query()
                ->where('email', $email)
                ->orWhere('email_canonical', $canonical)
                ->first();

            if ($raced) {
                return $this->handleExisting($raced, $request, $data, $canonical);
            }
            throw $e;
        }

        $sent = $this->sendVerification($trial, $canonical);

        return response()->json([
            'status' => 'sent',
            'uuid' => $trial->uuid,
            'message' => $sent
                ? 'Almost there! Check your inbox to confirm your email and activate your free AI credits.'
                : 'A verification email is already on its way — please check your inbox.',
        ]);
    }

    /**
     * Enforce the lifetime per-site-domain and per-IP claim caps. Returns a
     * rejection response when a cap is hit, or null when the claim may proceed.
     */
    private function capExceeded(Request $request, array $data): ?JsonResponse
    {
        $maxSite = (int) config('trial.max_per_site', 0);
        $maxIp = (int) config('trial.max_per_ip', 0);

        $domain = TrialService::domainOf($data['site_url'] ?? null);
        if ($maxSite > 0 && $domain !== '' && TrialCredit::where('site_domain', $domain)->count() >= $maxSite) {
            return $this->limitResponse();
        }

        if ($maxIp > 0 && TrialCredit::where('ip', $request->ip())->count() >= $maxIp) {
            return $this->limitResponse();
        }

        return null;
    }

    /**
     * The "too many trials" rejection (HTTP 200 so the plugin renders the message).
     */
    private function limitResponse(): JsonResponse
    {
        return response()->json([
            'status' => 'limit_reached',
            'message' => 'The free-trial limit for this site has been reached. Please contact us if you need more.',
        ], 200);
    }

    /**
     * Handle a request for an email that already has a grant.
     */
    private function handleExisting(TrialCredit $existing, Request $request, array $data, string $canonical): JsonResponse
    {
        if ($existing->status === TrialCredit::STATUS_VERIFIED) {
            return response()->json([
                'status' => 'exists',
                'uuid' => $existing->uuid,
                'message' => 'This email address has already claimed a free trial.',
            ]);
        }

        // Still pending — refresh site/ip and (subject to cooldown) re-send.
        $existing->update([
            'site_url' => $data['site_url'] ?? $existing->site_url,
            'ip' => $request->ip(),
            'reported_ip' => $data['ip'] ?? $existing->reported_ip,
        ]);
        $sent = $this->sendVerification($existing, $canonical);

        return response()->json([
            'status' => 'pending',
            'uuid' => $existing->uuid,
            'message' => $sent
                ? 'We have re-sent your verification link. Please check your inbox.'
                : 'A verification link was sent recently — please check your inbox (and spam) before requesting another.',
        ]);
    }

    /**
     * Build a signed verification URL and email it, unless the per-address
     * cooldown is still active.
     *
     * @return bool Whether an email was actually sent.
     */
    private function sendVerification(TrialCredit $trial, string $canonical): bool
    {
        $cooldown = max(0, (int) config('trial.resend_cooldown_seconds', 300));
        $key = 'trial_send:'.sha1($canonical);

        if ($cooldown > 0 && Cache::has($key)) {
            return false;
        }

        $url = URL::temporarySignedRoute(
            'trial.verify',
            now()->addMinutes((int) config('trial.verify_ttl_minutes', 120)),
            ['trial' => $trial->uuid]
        );

        Mail::to($trial->email)->send(new TrialVerification($trial, $url));

        if ($cooldown > 0) {
            Cache::put($key, 1, $cooldown);
        }

        return true;
    }
}
