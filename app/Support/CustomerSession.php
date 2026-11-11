<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Storefront customer sign-in state. Customers are signed in with a session flag (customer_id), not a Laravel guard,
 * and sessions live in the session driver (files today), so they cannot be listed or deleted per customer.
 * Revocation therefore works by fingerprint: each session stores an HMAC of the account's password_hash at sign-in;
 * when the password changes (reset, or a Google link clearing an unverified password) every session holding the old
 * fingerprint is rejected on its next request (ValidateCustomerSession). Other customers' sessions are unaffected.
 */
class CustomerSession
{
    public const ID = 'customer_id';

    public const FINGERPRINT = 'customer_pw';

    public static function fingerprint(?string $passwordHash): string
    {
        return hash_hmac('sha256', 'customer-session|'.($passwordHash ?? ''), (string) config('app.key'));
    }

    /** Sign the customer in on this request: new session id (no fixation), id + password fingerprint stored. */
    public static function start(Request $request, int $customerId, ?string $passwordHash): void
    {
        $request->session()->regenerate();
        $request->session()->put(self::ID, $customerId);
        $request->session()->put(self::FINGERPRINT, self::fingerprint($passwordHash));
    }

    /** Sign out on this request only (keeps the CSRF token so the open page keeps working). */
    public static function end(Request $request): void
    {
        $request->session()->forget([self::ID, self::FINGERPRINT]);
        $request->session()->migrate(true);
    }
}
