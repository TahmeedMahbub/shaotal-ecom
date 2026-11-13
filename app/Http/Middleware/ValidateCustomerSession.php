<?php

namespace App\Http\Middleware;

use App\Support\CustomerSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every web request that carries a signed-in customer (one primary-key lookup). Ends the customer session when
 * the account was deleted, blocked/deactivated in the admin app, its password changed since this session signed in, or
 * its email is not verified (only /verify-email signs such accounts in, so this closes sessions from before the rule).
 * Pages then render as a guest and customer-only routes ("customer" middleware) answer 401 / redirect to sign-in.
 */
class ValidateCustomerSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = (int) $request->session()->get(CustomerSession::ID);
        if ($id > 0) {
            $row = DB::table('customers')->where('id', $id)->first(['status', 'password_hash', 'email_verified_at']);
            $current = $row ? CustomerSession::fingerprint($row->password_hash) : null;
            $stored = $request->session()->get(CustomerSession::FINGERPRINT);

            if (! $row || $row->status !== 'active' || ! $row->email_verified_at || ($stored !== null && ! hash_equals($current, (string) $stored))) {
                CustomerSession::end($request);
            } elseif ($stored === null) {
                // Session signed in before fingerprints existed: adopt the current one (no forced sign-out on deploy).
                $request->session()->put(CustomerSession::FINGERPRINT, $current);
            }
        }

        return $next($request);
    }
}
