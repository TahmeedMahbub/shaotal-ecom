<?php

namespace App\Http\Controllers;

use App\Models\Storefront;
use App\Support\CustomerSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    private const RESET_MINUTES = 15;

    private const RESET_MAX_ATTEMPTS = 5;

    /** Minimum length for NEW passwords (register, reset). Sign-in never re-validates length, so older passwords keep working. */
    public const PASSWORD_MIN = 8;

    // Failed sign-in limits (all within LOGIN_DECAY seconds). Counted on failures only; a success clears the email keys.
    // Per email+IP stops one source quickly; per email caps distributed guessing on one account (a lock-out is
    // temporary and "Forgot password" keeps working); per IP caps credential stuffing across many emails.
    private const LOGIN_DECAY = 900;

    private const LOGIN_MAX_EMAIL_IP = 5;

    private const LOGIN_MAX_EMAIL = 20;

    private const LOGIN_MAX_IP = 30;

    private const FORGOT_MAX_PER_EMAIL = 3;

    private function newPasswordRules(): array
    {
        return ['required', 'string', 'max:200', Password::min(self::PASSWORD_MIN)];
    }

    /** Fresh CSRF token for the AJAX client after a 419 (token/session expired). */
    public function csrf(Request $request): JsonResponse
    {
        return response()->json(['csrf' => $request->session()->token()])->header('Cache-Control', 'no-store, private');
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'password' => $this->newPasswordRules(),
            'cart' => ['nullable', 'array', 'max:100'],
            'wishlist' => ['nullable', 'array', 'max:200'],
        ]);
        $email = strtolower(trim($data['email']));
        if (DB::table('customers')->where('email', $email)->exists()) {
            return response()->json(['message' => 'An account with this email already exists.'], 422);
        }

        $parts = preg_split('/\s+/', trim($data['name']), 2);
        $hash = Hash::make($data['password']);
        $id = DB::table('customers')->insertGetId([
            'first_name' => mb_substr($parts[0], 0, 100),
            'last_name' => isset($parts[1]) ? mb_substr($parts[1], 0, 100) : null,
            'email' => $email,
            'password_hash' => $hash,
            'status' => 'active',
        ]);

        return $this->startSession($request, $id, $hash, 'register', $data);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
            'cart' => ['nullable', 'array', 'max:100'],
            'wishlist' => ['nullable', 'array', 'max:200'],
        ]);
        $email = strtolower(trim($data['email']));
        // Same keys and answer whether or not the email has an account, so limits never reveal which emails exist.
        $emailKey = 'login:email:'.hash('sha256', $email);
        $keys = [
            [$emailKey.'|'.$request->ip(), self::LOGIN_MAX_EMAIL_IP],
            [$emailKey, self::LOGIN_MAX_EMAIL],
            ['login:ip:'.$request->ip(), self::LOGIN_MAX_IP],
        ];
        foreach ($keys as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $wait = RateLimiter::availableIn($key);
                $minutes = max(1, (int) ceil($wait / 60));

                return response()->json([
                    'message' => "Too many sign-in attempts. Please try again in {$minutes} minute".($minutes > 1 ? 's' : '').' or reset your password.',
                ], 429)->header('Retry-After', (string) $wait);
            }
        }

        $customer = DB::table('customers')->where('email', $email)->first(['id', 'password_hash', 'status']);

        if (! $customer || ! $customer->password_hash || ! Hash::check($data['password'], $customer->password_hash)) {
            foreach ($keys as [$key]) {
                RateLimiter::hit($key, self::LOGIN_DECAY);
            }
            $this->log($customer->id ?? null, 'login_failed', $request);

            return response()->json(['message' => 'Incorrect email or password.'], 422);
        }
        if ($customer->status !== 'active') {
            return response()->json(['message' => 'This account is not active. Please contact us.'], 403);
        }
        RateLimiter::clear($keys[0][0]);
        RateLimiter::clear($keys[1][0]);

        return $this->startSession($request, (int) $customer->id, $customer->password_hash, 'login', $data);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->forget([CustomerSession::ID, CustomerSession::FINGERPRINT]);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true, 'csrf' => csrf_token()]);
    }

    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $email = strtolower(trim($data['email']));
        $customer = DB::table('customers')->where('email', $email)->where('status', 'active')->first(['id', 'first_name']);
        // Per-address cap so one inbox cannot be flooded (the route throttle is per IP). Same reply either way.
        $mailKey = 'forgot:email:'.hash('sha256', $email);
        $allowed = ! RateLimiter::tooManyAttempts($mailKey, self::FORGOT_MAX_PER_EMAIL);
        RateLimiter::hit($mailKey, self::RESET_MINUTES * 60);

        if ($customer && $allowed) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            DB::table('customer_verification_codes')->where('destination', $email)->where('purpose', 'password_reset')->whereNull('verified_at')->delete();
            DB::table('customer_verification_codes')->insert([
                'customer_id' => $customer->id, 'destination' => $email, 'channel' => 'email', 'purpose' => 'password_reset',
                'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(self::RESET_MINUTES),
            ]);
            $this->log($customer->id, 'password_reset_requested', $request);
            Mail::raw(
                "Hi {$customer->first_name},\n\nYour ".config('storefront.brand').' password reset code is '.$code.'. It expires in '.self::RESET_MINUTES." minutes.\n\nIf you did not request this, you can ignore this email.",
                fn ($m) => $m->to($email)->subject('Your password reset code')
            );
        }

        // Same answer whether or not the account exists, so emails cannot be enumerated.
        return response()->json(['message' => 'If an account exists for that email, a 6-digit reset code has been sent.']);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'code' => ['required', 'digits:6'],
            'password' => $this->newPasswordRules(),
        ]);
        $email = strtolower(trim($data['email']));
        $row = DB::table('customer_verification_codes')->where('destination', $email)->where('purpose', 'password_reset')
            ->whereNull('verified_at')->where('expires_at', '>', now())->orderByDesc('id')->first();

        if (! $row || $row->attempts >= self::RESET_MAX_ATTEMPTS) {
            return response()->json(['message' => 'This code has expired. Please request a new one.'], 422);
        }
        DB::table('customer_verification_codes')->where('id', $row->id)->increment('attempts');
        if (! Hash::check($data['code'], $row->code_hash)) {
            return response()->json(['message' => 'That code is incorrect.'], 422);
        }

        DB::transaction(function () use ($row, $data) {
            DB::table('customers')->where('id', $row->customer_id)->update(['password_hash' => Hash::make($data['password'])]);
            DB::table('customer_verification_codes')->where('id', $row->id)->update(['verified_at' => now()]);
        });
        $this->log($row->customer_id, 'password_reset', $request);

        // The new password_hash changes this customer's session fingerprint, so every session signed in before the
        // reset (any browser/device) is rejected on its next request. This browser gets a new session id; if it was
        // signed in as this customer it is signed out too (the flow asks them to sign in with the new password).
        if ((int) $request->session()->get(CustomerSession::ID) === (int) $row->customer_id) {
            CustomerSession::end($request);
        } else {
            $request->session()->migrate(true);
        }

        return response()->json(['message' => 'Password updated successfully. You can now sign in.']);
    }

    private function startSession(Request $request, int $customerId, ?string $passwordHash, string $event, array $data): JsonResponse
    {
        CustomerSession::start($request, $customerId, $passwordHash);
        $this->log($customerId, $event, $request);

        $cart = array_values(array_filter((array) ($data['cart'] ?? []), 'is_array'));
        $wishlist = array_map(fn ($w) => is_array($w) ? ($w['productId'] ?? $w['id'] ?? 0) : $w, (array) ($data['wishlist'] ?? []));
        Storefront::mergeGuest($customerId, $cart, $wishlist);

        return response()->json(Storefront::sessionPayload($customerId));
    }

    private function log(?int $customerId, string $event, Request $request): void
    {
        DB::table('customer_security_logs')->insert([
            'customer_id' => $customerId, 'event_type' => $event, 'source' => 'storefront',
            'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
