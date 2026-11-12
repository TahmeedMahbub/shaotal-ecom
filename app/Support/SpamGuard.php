<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Spam protection for public forms without a CAPTCHA:
 *  - honeypot: a visually hidden field ("company_url") that people never fill but form-filling bots do;
 *  - per-contact limit: the same email/phone can submit a form at most MAX_PER_CONTACT times per hour (on top of the
 *    per-IP route throttle), so rotating IPs does not help one sender flood the inbox;
 *  - link limit: free-text fields with many links are rejected (typical link spam).
 */
class SpamGuard
{
    public const HONEYPOT = 'company_url';

    public const MAX_PER_CONTACT = 5;

    public const MAX_LINKS = 3;

    public static function check(Request $request, string $form, ?string $contact, array $freeText = []): void
    {
        if (trim((string) $request->input(self::HONEYPOT, '')) !== '') {
            abort(422, 'Your request could not be sent. Please try again.');
        }
        foreach ($freeText as $text) {
            if (preg_match_all('#(https?://|www\.)#i', (string) $text) > self::MAX_LINKS) {
                abort(422, 'Please include no more than '.self::MAX_LINKS.' links in your message.');
            }
        }
        $contact = mb_strtolower(trim((string) $contact));
        if ($contact !== '') {
            $key = 'form:'.$form.':'.hash('sha256', $contact);
            if (RateLimiter::tooManyAttempts($key, self::MAX_PER_CONTACT)) {
                abort(429, 'You have sent several requests recently. Please try again later.');
            }
            RateLimiter::hit($key, 3600);
        }
    }
}
