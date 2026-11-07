<?php

namespace App\Rules;

use App\Support\SiteSettings;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifies a Google reCAPTCHA v2 checkbox response server-side.
 * Fails closed: network errors or a non-success verdict reject the submission.
 */
class Recaptcha implements ValidationRule
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail('Please confirm you are not a robot.');

            return;
        }

        $errors = [];

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post(self::VERIFY_URL, [
                    'secret' => SiteSettings::recaptchaSecretKey(),
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]);

            $verified = $response->successful() && (bool) $response->json('success');
            $errors = array_map('strval', (array) ($response->json('error-codes') ?? []));
        } catch (Throwable $e) {
            $verified = false;
            $errors = ['request-failed: '.$e->getMessage()];
        }

        if ($verified) {
            return;
        }

        // A refusal here is invisible from the outside — the visitor only ever
        // sees "verification failed", which reads the same whether the token
        // was stale or the keys are wrong. Google's own codes say which, so
        // they are logged: 'invalid-input-secret' / 'invalid-input-response'
        // mean the configured key pair is wrong, not that the visitor is.
        Log::warning('reCAPTCHA verification refused a submission.', [
            'attribute' => $attribute,
            'error_codes' => $errors,
            'ip' => request()->ip(),
        ]);

        // A token is good once, and for two minutes. Spent or stale is the
        // common case, and the visitor is looking at a ticked box while being
        // told the captcha failed — so say what to actually do about it.
        $fail(in_array('timeout-or-duplicate', $errors, true)
            ? 'Your captcha check expired. Please tick the box again.'
            : 'CAPTCHA verification failed. Please try again.');
    }
}
