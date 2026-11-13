<?php

namespace App\Support;

use App\Events\VerificationCodeIssued;
use App\Http\Controllers\GoogleAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Email verification for password accounts. A customer whose email is not verified is never signed in: registration
 * (and a correct password on an unverified account) only stores a "pending verification" marker in the session, and
 * /verify-email turns it into a real CustomerSession once the emailed 4-digit code is entered. Every customer-only
 * route therefore stays closed until then, whatever URL is typed (ValidateCustomerSession also ends any signed-in
 * session whose email is not verified).
 *
 * Codes: CSPRNG, stored only as a keyed hash (HMAC with APP_KEY, bound to the customer) in customer_verification_codes
 * (purpose 'email_verification'): a 4-digit code has only 10,000 values, so a slow unkeyed hash (bcrypt) would add
 * latency without protection; the key means a database copy alone cannot reveal codes. Valid for EXPIRES_MINUTES,
 * MAX_ATTEMPTS wrong guesses per code, single use, and a new code deletes the older unused ones.
 */
class EmailVerification
{
    public const PENDING = 'verify_customer_id';

    public const RETURN = 'verify_return';

    public const GUEST = 'verify_guest';

    public const EXPIRES_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    /** Codes per customer per hour (registration/sign-in sends count). */
    public const MAX_SENDS_PER_HOUR = 5;

    /** Wrong + right guesses per customer per 15 minutes, across codes. */
    public const MAX_GUESSES = 15;

    /** Same-site path the customer asked for before signing up, or the account page. */
    public static function returnPath(mixed $path): string
    {
        $path = is_string($path) ? $path : '';
        if ($path === '' || GoogleAuthController::safeReturnPath($path) !== $path || str_starts_with($path, '/verify-email')) {
            return '/account';
        }

        return $path;
    }

    /** Pending verification on this request: new session id, no customer signed in, destination + guest bag kept. */
    public static function begin(Request $request, int $customerId, mixed $return, array $guest): void
    {
        $session = $request->session();
        $session->forget([CustomerSession::ID, CustomerSession::FINGERPRINT]);
        $session->migrate(true);
        $session->put(self::PENDING, $customerId);
        $session->put(self::RETURN, self::returnPath($return));
        $session->put(self::GUEST, [
            'cart' => array_values(array_filter((array) ($guest['cart'] ?? []), 'is_array')),
            'wishlist' => array_values((array) ($guest['wishlist'] ?? [])),
        ]);
    }

    public static function pendingId(Request $request): int
    {
        return (int) $request->session()->get(self::PENDING);
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget([self::PENDING, self::GUEST]);
    }

    public static function hash(int $customerId, string $code): string
    {
        return hash_hmac('sha256', 'email-verification|'.$customerId.'|'.$code, (string) config('app.key'));
    }

    public static function matches(int $customerId, string $code, string $storedHash): bool
    {
        return hash_equals($storedHash, self::hash($customerId, $code));
    }

    /** "ve***@example.com": enough to recognise the address on a shared screen without showing all of it. */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $keep = mb_strlen($local) <= 2 ? 1 : 2;

        return mb_substr($local, 0, $keep).'***'.($domain !== '' ? '@'.$domain : '');
    }

    public static function cooldown(int $customerId): int
    {
        $key = 'verify:resend:'.$customerId;

        return RateLimiter::tooManyAttempts($key, 1) ? max(1, RateLimiter::availableIn($key)) : 0;
    }

    /**
     * New code, then the email event. Returns ['status' => 'queued'|'cooldown'|'limit', 'retryAfter' => seconds].
     * The browser never waits for the mail server: delivery happens in the queued listener, which retries and, if it
     * finally fails, lifts the resend cooldown (deliveryFailed) so "Resend code" works at once.
     */
    public static function send(int $customerId, string $email, string $firstName, Request $request): array
    {
        if ($wait = self::cooldown($customerId)) {
            return ['status' => 'cooldown', 'retryAfter' => $wait];
        }
        $hourKey = 'verify:sends:'.$customerId;
        if (RateLimiter::tooManyAttempts($hourKey, self::MAX_SENDS_PER_HOUR)) {
            return ['status' => 'limit', 'retryAfter' => RateLimiter::availableIn($hourKey)];
        }
        RateLimiter::hit('verify:resend:'.$customerId, self::RESEND_SECONDS);
        RateLimiter::hit($hourKey, 3600);

        $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        DB::transaction(function () use ($customerId, $email, $code) {
            // A new code replaces every unused one, so only the latest email works.
            DB::table('customer_verification_codes')->where('customer_id', $customerId)->where('purpose', 'email_verification')->whereNull('verified_at')->delete();
            DB::table('customer_verification_codes')->insert([
                'customer_id' => $customerId, 'destination' => $email, 'channel' => 'email', 'purpose' => 'email_verification',
                'code_hash' => self::hash($customerId, $code), 'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
            ]);
        });
        self::log($customerId, 'email_verification_sent', $request);

        // Email off the request: queued listener (App\Listeners\SendVerificationCodeEmail) via App\Support\AuthMail.
        AuthMail::dispatch(new VerificationCodeIssued($customerId, $email, $firstName, $code, self::EXPIRES_MINUTES));

        return ['status' => 'queued', 'retryAfter' => self::RESEND_SECONDS];
    }

    /** The email did not go out: the customer may ask for a new code straight away (the hourly cap still applies). */
    public static function deliveryFailed(int $customerId): void
    {
        RateLimiter::clear('verify:resend:'.$customerId);
    }

    public static function log(int $customerId, string $event, Request $request): void
    {
        DB::table('customer_security_logs')->insert([
            'customer_id' => $customerId, 'event_type' => $event, 'source' => 'storefront',
            'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
