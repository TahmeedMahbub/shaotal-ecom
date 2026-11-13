<?php

namespace App\Http\Controllers;

use App\Models\Storefront;
use App\Support\CustomerSession;
use App\Support\EmailVerification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/** /verify-email: the only way a pending (unverified) registration or sign-in becomes a signed-in customer. */
class EmailVerificationController extends Controller
{
    private const EXPIRED = 'Your verification session has expired. Please sign in again to get a new code.';

    private function pending(Request $request): ?object
    {
        $id = EmailVerification::pendingId($request);

        return $id ? DB::table('customers')->where('id', $id)->first(['id', 'email', 'first_name', 'status', 'password_hash', 'email_verified_at']) : null;
    }

    public function show(Request $request)
    {
        // Already signed in (verified): nothing to do here.
        if ($this->customerId()) {
            return redirect(EmailVerification::returnPath($request->session()->get(EmailVerification::RETURN)));
        }
        $c = $this->pending($request);
        if ($c && ($c->status !== 'active' || $c->email_verified_at)) {
            // Blocked meanwhile, or verified another way (e.g. Google): start over from sign-in.
            EmailVerification::forget($request);
            $c = null;
        }

        return response()->view('pages.verify-email', [
            'email' => $c ? EmailVerification::mask($c->email) : null,
            'cooldown' => $c ? EmailVerification::cooldown((int) $c->id) : 0,
            'minutes' => EmailVerification::EXPIRES_MINUTES,
        ])->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'private, no-store');
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^\d{4}$/']], ['code.regex' => 'Enter the 4-digit code from the email.']);
        $c = $this->pending($request);
        if (! $c) {
            // A repeated submit after this session already verified (double click, retry): same success answer.
            if ($this->customerId()) {
                return $this->done($request);
            }

            return response()->json(['message' => self::EXPIRED, 'expired' => true], 401);
        }
        if ($c->status !== 'active') {
            EmailVerification::forget($request);

            return response()->json(['message' => 'This account is not active. Please contact us.'], 403);
        }
        if ($c->email_verified_at) {
            return $this->finish($request, $c, false);
        }

        $guessKey = 'verify:guess:'.$c->id;
        if (RateLimiter::tooManyAttempts($guessKey, EmailVerification::MAX_GUESSES)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($guessKey) / 60));

            return response()->json(['message' => "Too many attempts. Please try again in {$minutes} minute".($minutes > 1 ? 's' : '').'.'], 429);
        }
        RateLimiter::hit($guessKey, 900);

        // Latest live code for the current address (an email change or a resend makes older codes useless).
        $row = DB::table('customer_verification_codes')->where('customer_id', $c->id)->where('destination', $c->email)
            ->where('purpose', 'email_verification')->whereNull('verified_at')->where('expires_at', '>', now())->orderByDesc('id')->first();
        if (! $row || $row->attempts >= EmailVerification::MAX_ATTEMPTS) {
            return response()->json(['message' => 'This code has expired. Please request a new one.', 'codeExpired' => true], 422);
        }
        DB::table('customer_verification_codes')->where('id', $row->id)->increment('attempts');
        if (! EmailVerification::matches((int) $c->id, $data['code'], $row->code_hash)) {
            $left = EmailVerification::MAX_ATTEMPTS - $row->attempts - 1;

            return response()->json([
                'message' => $left > 0 ? "That code is incorrect. {$left} attempt".($left > 1 ? 's' : '').' left.' : 'That code is incorrect. Please request a new code.',
                'codeExpired' => $left <= 0,
            ], 422);
        }

        // Single use: only the request that flips verified_at wins (a parallel duplicate just signs in).
        $won = DB::transaction(function () use ($row, $c) {
            $won = DB::table('customer_verification_codes')->where('id', $row->id)->whereNull('verified_at')->update(['verified_at' => now()]);
            DB::table('customers')->where('id', $c->id)->whereNull('email_verified_at')->update(['email_verified_at' => now()]);

            return $won > 0;
        });
        RateLimiter::clear($guessKey);

        return $this->finish($request, $c, $won);
    }

    public function resend(Request $request): JsonResponse
    {
        $c = $this->pending($request);
        if (! $c || $c->status !== 'active') {
            return response()->json(['message' => self::EXPIRED, 'expired' => true], 401);
        }
        if ($c->email_verified_at) {
            return $this->finish($request, $c, false);
        }

        return $this->sendAnswer(EmailVerification::send((int) $c->id, $c->email, (string) $c->first_name, $request), $c->email);
    }

    /** Wrong address typed at sign-up: allowed only while unverified, then a code goes to the new address. */
    public function changeEmail(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:190']]);
        $c = $this->pending($request);
        if (! $c || $c->status !== 'active') {
            return response()->json(['message' => self::EXPIRED, 'expired' => true], 401);
        }
        if ($c->email_verified_at) {
            return $this->finish($request, $c, false);
        }
        $email = strtolower(trim($data['email']));
        if ($email === $c->email) {
            return response()->json(['message' => 'That is already the email address on your account.'], 422);
        }
        if ($wait = EmailVerification::cooldown((int) $c->id)) {
            return response()->json(['message' => "Please wait {$wait} seconds before requesting another code.", 'retryAfter' => $wait], 429);
        }
        try {
            $changed = DB::table('customers')->where('id', $c->id)->whereNull('email_verified_at')->update(['email' => $email]);
        } catch (UniqueConstraintViolationException) {
            $changed = -1;
        }
        if ($changed === -1 || ($changed === 0 && DB::table('customers')->where('email', $email)->where('id', '!=', $c->id)->exists())) {
            return response()->json(['message' => 'An account with this email already exists.'], 422);
        }
        EmailVerification::log((int) $c->id, 'email_changed_before_verification', $request);

        return $this->sendAnswer(EmailVerification::send((int) $c->id, $email, (string) $c->first_name, $request), $email);
    }

    private function sendAnswer(array $r, string $email): JsonResponse
    {
        return match ($r['status']) {
            'sent', 'queued' => response()->json(['message' => 'A new code has been sent to '.EmailVerification::mask($email).'.', 'email' => EmailVerification::mask($email), 'retryAfter' => $r['retryAfter']]),
            'cooldown' => response()->json(['message' => "Please wait {$r['retryAfter']} seconds before requesting another code.", 'email' => EmailVerification::mask($email), 'retryAfter' => $r['retryAfter']], 429),
            'limit' => response()->json(['message' => 'Too many codes requested. Please try again in '.max(1, (int) ceil($r['retryAfter'] / 60)).' minutes.', 'email' => EmailVerification::mask($email), 'retryAfter' => $r['retryAfter']], 429),
            default => response()->json(['message' => 'We could not send the email right now. Please try again shortly.', 'email' => EmailVerification::mask($email), 'retryAfter' => $r['retryAfter']], 503),
        };
    }

    /** Verified: sign in (new session id), bring the guest bag/wishlist over, answer with the validated destination. */
    private function finish(Request $request, object $c, bool $logEvent): JsonResponse
    {
        CustomerSession::start($request, (int) $c->id, $c->password_hash);
        $guest = (array) $request->session()->get(EmailVerification::GUEST, []);
        Storefront::mergeGuest((int) $c->id, (array) ($guest['cart'] ?? []), array_map(
            fn ($w) => is_array($w) ? ($w['productId'] ?? $w['id'] ?? 0) : $w, (array) ($guest['wishlist'] ?? [])
        ));
        EmailVerification::forget($request);
        if ($logEvent) {
            EmailVerification::log((int) $c->id, 'email_verified', $request);
        }

        return $this->done($request);
    }

    private function done(Request $request): JsonResponse
    {
        return response()->json([
            'verified' => true,
            'message' => 'Your email address is verified.',
            'redirect' => EmailVerification::returnPath($request->session()->get(EmailVerification::RETURN)),
        ]);
    }
}
