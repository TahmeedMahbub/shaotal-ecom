<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * "Continue with Google". Identity is the Google account id stored in customer_social_accounts
 * (sql/google_login.sql); email is only used to find an existing account when Google vouches for it.
 */
class GoogleAuthController extends Controller
{
    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (! self::enabled()) {
            return $this->fail('Google sign-in is not available right now.');
        }
        $return = (string) $request->query('return', '/');
        // Only same-site relative paths, never an external URL.
        $request->session()->put('google_return', (str_starts_with($return, '/') && ! str_starts_with($return, '//')) ? $return : '/');

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! self::enabled()) {
            return $this->fail('Google sign-in is not available right now.');
        }
        if ($request->filled('error')) {
            return $this->fail($request->query('error') === 'access_denied' ? 'Google sign-in was cancelled.' : 'Google sign-in failed. Please try again.');
        }

        try {
            $g = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Google sign-in failed. Please try again.');
        }

        $raw = $g->getRaw();
        $email = strtolower(trim((string) $g->getEmail()));
        // Google must vouch for the address, otherwise it cannot be trusted at all.
        if (! $g->getId() || $email === '' || ! filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return $this->fail('Your Google account does not have a verified email address.');
        }

        try {
            $customerId = $this->resolveCustomer((string) $g->getId(), $email, (string) $g->getName(), $request);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('We could not complete Google sign-in. Please try again or use your email and password.');
        }
        if (! $customerId) {
            return $this->fail('This account is not active. Please contact us.');
        }

        $request->session()->regenerate();
        $request->session()->put('customer_id', $customerId);
        $return = $request->session()->pull('google_return', '/');

        return redirect($return)->with('auth_notice', 'Signed in with Google.');
    }

    /** @return int|null customer id, or null when the matching account is not active */
    private function resolveCustomer(string $googleId, string $email, string $name, Request $request): ?int
    {
        return DB::transaction(function () use ($googleId, $email, $name, $request) {
            $link = DB::table('customer_social_accounts')->where('provider', 'google')->where('provider_user_id', $googleId)->first();
            if ($link) {
                $c = DB::table('customers')->where('id', $link->customer_id)->first(['id', 'status']);

                return ($c && $c->status === 'active') ? (int) $c->id : null;
            }

            $existing = DB::table('customers')->where('email', $email)->lockForUpdate()->first(['id', 'status', 'email_verified_at']);
            if ($existing) {
                if ($existing->status !== 'active') {
                    return null;
                }
                $update = ['email_verified_at' => $existing->email_verified_at ?? now()];
                if (! $existing->email_verified_at) {
                    // The local account's email was never verified, so whoever created it may not own the address.
                    // Google just proved ownership: drop any password set before that proof (use "Forgot password" to set one).
                    $update['password_hash'] = null;
                }
                DB::table('customers')->where('id', $existing->id)->update($update);
                $this->link((int) $existing->id, $googleId, $email);
                $this->log((int) $existing->id, 'google_link', $request);

                return (int) $existing->id;
            }

            $parts = preg_split('/\s+/', trim($name !== '' ? $name : strstr($email, '@', true)), 2);
            $id = DB::table('customers')->insertGetId([
                'first_name' => mb_substr($parts[0], 0, 100),
                'last_name' => isset($parts[1]) ? mb_substr($parts[1], 0, 100) : null,
                'email' => $email, 'password_hash' => null, 'status' => 'active', 'email_verified_at' => now(),
            ]);
            $this->link($id, $googleId, $email);
            $this->log($id, 'google_register', $request);

            return $id;
        });
    }

    private function link(int $customerId, string $googleId, string $email): void
    {
        DB::table('customer_social_accounts')->insert([
            'customer_id' => $customerId, 'provider' => 'google', 'provider_user_id' => $googleId, 'provider_email' => $email,
        ]);
    }

    private function log(int $customerId, string $event, Request $request): void
    {
        DB::table('customer_security_logs')->insert([
            'customer_id' => $customerId, 'event_type' => $event, 'source' => 'storefront',
            'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect('/')->with('auth_error', $message);
    }
}
