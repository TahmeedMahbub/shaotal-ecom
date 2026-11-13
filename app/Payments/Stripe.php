<?php

namespace App\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Stripe REST client (Checkout Sessions only) and webhook signature check. Uses Laravel's HTTP client, so no SDK
 * dependency and tests use Http::fake(). The secret key is only ever sent in the Authorization header to api.stripe.com
 * and is never logged, rendered or returned.
 */
final class Stripe
{
    public const API = 'https://api.stripe.com/v1';

    /** Smallest AUD charge Stripe accepts (cents). */
    public const MIN_AMOUNT_CENTS = 50;

    /** Keys present and matching STRIPE_MODE (test keys in test mode, live keys in live mode). */
    public static function ready(): bool
    {
        return self::problem() === null;
    }

    /** Why card payments can't run, or null. Never includes key values. */
    public static function problem(): ?string
    {
        $c = config('services.stripe');
        if (! $c['enabled']) {
            return 'STRIPE_ENABLED is false';
        }
        $mode = $c['mode'];
        if (! in_array($mode, ['test', 'live'], true)) {
            return 'STRIPE_MODE must be test or live';
        }
        foreach (['key' => "pk_{$mode}_", 'secret' => "sk_{$mode}_", 'webhook_secret' => 'whsec_'] as $name => $prefix) {
            $value = (string) $c[$name];
            // Restricted keys (rk_) are fine for the secret.
            $ok = str_starts_with($value, $prefix) || ($name === 'secret' && str_starts_with($value, "rk_{$mode}_"));
            if (! $ok) {
                return 'STRIPE_'.strtoupper($name).($value === '' ? ' is missing' : " does not match STRIPE_MODE={$mode}");
            }
        }
        if ($c['currency'] !== 'aud') {
            return 'STRIPE_CURRENCY must be aud (prices are A$)';
        }

        return null;
    }

    public static function mode(): string
    {
        return (string) config('services.stripe.mode');
    }

    /** DECIMAL(12,2) string from the DB → integer cents, without float arithmetic ("97.5" → 9750). */
    public static function cents(string|int|float $amount): int
    {
        $s = is_string($amount) ? trim($amount) : number_format((float) $amount, 2, '.', '');
        if (! preg_match('/^(\d+)(?:\.(\d{0,2})\d*)?$/', $s, $m)) {
            throw new RuntimeException('Invalid amount.');
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private static function http(): PendingRequest
    {
        return Http::withToken((string) config('services.stripe.secret'))
            ->withHeaders(['Stripe-Version' => (string) config('services.stripe.api_version')])
            ->asForm()->acceptJson()->timeout(20)->connectTimeout(10);
    }

    /** @return array<string, mixed> */
    private static function json(Response $res, string $what): array
    {
        if (! $res->successful()) {
            // Stripe's error type/code only: never the request (it would contain customer data) or headers (the key).
            $err = (array) $res->json('error');
            throw new StripeException("Stripe {$what} failed ({$res->status()} ".($err['type'] ?? '').' '.($err['code'] ?? '').')', $res->status(), $err['code'] ?? null);
        }

        return (array) $res->json();
    }

    /**
     * Create a hosted Checkout Session. $idempotencyKey makes a network retry return the same session instead of a
     * second one.
     *
     * @return array<string, mixed>
     */
    public static function createCheckoutSession(array $params, string $idempotencyKey): array
    {
        return self::json(self::http()->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post(self::API.'/checkout/sessions', $params), 'create session');
    }

    /** @return array<string, mixed> */
    public static function retrieveCheckoutSession(string $id): array
    {
        return self::json(self::http()->get(self::API.'/checkout/sessions/'.rawurlencode($id)), 'retrieve session');
    }

    /**
     * Expire an open session so it can no longer be paid. Returns the session after the call; when Stripe refuses
     * because the session is already complete/expired, the current session is fetched and returned instead.
     *
     * @return array<string, mixed>
     */
    public static function expireCheckoutSession(string $id): array
    {
        $res = self::http()->post(self::API.'/checkout/sessions/'.rawurlencode($id).'/expire');
        if ($res->status() === 400) {
            return self::retrieveCheckoutSession($id);
        }

        return self::json($res, 'expire session');
    }

    /**
     * Verify a webhook's Stripe-Signature header (HMAC-SHA256 of "timestamp.payload" with the endpoint secret, any of
     * the v1 signatures may match, timestamp within the tolerance) and return the decoded event.
     *
     * @return array<string, mixed>
     *
     * @throws StripeException when the signature or payload is invalid
     */
    public static function verifyWebhook(string $payload, ?string $header, ?int $now = null): array
    {
        $secret = (string) config('services.stripe.webhook_secret');
        if ($secret === '' || ! $header) {
            throw new StripeException('Missing webhook secret or signature.', 400);
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't' && ctype_digit($v)) {
                $timestamp = (int) $v;
            } elseif ($k === 'v1' && $v !== '') {
                $signatures[] = $v;
            }
        }
        if ($timestamp === null || ! $signatures) {
            throw new StripeException('Malformed signature header.', 400);
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $match = false;
        foreach ($signatures as $sig) {
            $match = $match || hash_equals($expected, $sig);
        }
        if (! $match) {
            throw new StripeException('Signature mismatch.', 400);
        }
        if (abs(($now ?? time()) - $timestamp) > (int) config('services.stripe.webhook_tolerance', 300)) {
            throw new StripeException('Signature timestamp outside tolerance.', 400);
        }
        $event = json_decode($payload, true);
        if (! is_array($event) || ! isset($event['id'], $event['type'], $event['data']['object'])) {
            throw new StripeException('Invalid event payload.', 400);
        }

        return $event;
    }

    /** Build a valid signature header (tests and local tooling only). */
    public static function signatureHeader(string $payload, string $secret, ?int $timestamp = null): string
    {
        $t = $timestamp ?? time();

        return "t={$t},v1=".hash_hmac('sha256', $t.'.'.$payload, $secret);
    }
}
