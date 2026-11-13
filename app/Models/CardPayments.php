<?php

namespace App\Models;

use App\Payments\OnlinePaymentPlan;
use App\Payments\PaymentMethods;
use App\Payments\Stripe;
use App\Payments\StripeException;
use App\Support\DisplayTime;
use App\Support\OrderEmails;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Card payments through Stripe Checkout (hosted page). One `payments` row per attempt (one Checkout Session each):
 * provider = "stripe", provider_transaction_id = the session id (UNIQUE(provider, provider_transaction_id) in
 * sql/ecom.sql is the anchor that ties every webhook to exactly one row).
 *
 * Rules (docs/payments-architecture.md):
 *  - Online payment is a pre-booking (App\Payments\OnlinePaymentPlan): the Stripe session charges the DEPOSIT only
 *    (payments.amount of the attempt row). A paid deposit leaves orders.payment_status "pending" (the shared enum has no
 *    partial value) and is shown as "Pre-booked" from the paid payment rows; the order becomes "paid" only when the
 *    sum of paid payment rows reaches grand_total. A deposit is never treated as full payment.
 *  - Nothing becomes paid except in applyEvent(), from a signature-verified webhook whose amount, currency,
 *    order number and live/test mode match. The success redirect only displays state.
 *  - Webhooks are idempotent: each is applied under row locks and transitions only move forward (paid is final here;
 *    refunds are done by staff). Processed event ids are also kept in payments.response_data and skipped on repeat.
 *  - At most one open session per order: a retry reuses the open session, or closes the old one (Stripe "expire")
 *    before creating a new one; an already-completed session blocks a new attempt.
 *  - No stock is changed anywhere (stock is out of scope until V2).
 */
class CardPayments
{
    /** Payment statuses in which the customer may (re)try paying. */
    private const PAYABLE = ['pending', 'failed'];

    /** Max event ids remembered per payment row. */
    private const EVENT_MEMORY = 25;

    /** An open session closer than this to its expiry is replaced instead of reused (seconds). */
    private const REUSE_MARGIN = 300;

    /** Card order of this customer, or null. */
    private static function order(int $customerId, string $orderNumber): ?object
    {
        return DB::table('orders')->where('order_number', $orderNumber)->where('customer_id', $customerId)
            ->first(['id', 'order_number', 'customer_id', 'customer_email', 'grand_total', 'payment_method', 'payment_status', 'order_status']);
    }

    /** Whether the customer can start/retry a card payment for this order (shown as "Pay now"). */
    public static function canPay(?string $method, ?string $paymentStatus, ?string $orderStatus, float $paidAmount = 0.0): bool
    {
        // Nothing paid yet: a received deposit must never be asked for (or charged) a second time.
        return $method === 'card' && $orderStatus === 'pending' && $paidAmount <= 0 && in_array($paymentStatus, self::PAYABLE, true) && Stripe::ready();
    }

    /** Amount of the order already received (sum of paid payment rows). */
    public static function paidTotal(int $orderId): float
    {
        return (float) DB::table('payments')->where('order_id', $orderId)->where('status', 'paid')->sum('amount');
    }

    /** @return array<int, float> order id => amount received, for the given orders (one query). */
    public static function paidTotals(array $orderIds): array
    {
        return DB::table('payments')->whereIn('order_id', $orderIds ?: [0])->where('status', 'paid')->groupBy('order_id')
            ->selectRaw('order_id, SUM(amount) as paid')->pluck('paid', 'order_id')->map(fn ($v) => (float) $v)->all();
    }

    /** What the Stripe session for this order charges: the plan's deposit, or the whole total for an order placed before pre-booking existed. */
    public static function amountDue(object $order): float
    {
        $plan = OnlinePaymentPlan::forOrder($order->id);

        return $plan ? (float) $plan['deposit'] : (float) $order->grand_total;
    }

    /**
     * Get a Stripe Checkout URL for the customer's order: the current open session when it still has time left,
     * otherwise a new session (older open sessions are expired first so only one can ever be paid).
     *
     * @return array{redirectUrl: string, sessionId: string}
     *
     * @throws HttpException 404 unknown order, 409 already paid/being processed, 422 not payable, 503 Stripe unavailable
     */
    public static function start(int $customerId, string $orderNumber): array
    {
        abort_unless(Stripe::ready(), 422, 'Card payments are not available right now. Please choose another payment method.');
        $order = self::order($customerId, $orderNumber);
        abort_unless($order, 404);

        $lock = Cache::store('file')->lock('stripe:start:'.$order->id, 30);
        try {
            $lock->block(10);
        } catch (LockTimeoutException) {
            abort(429, 'Your payment is already being prepared. Please wait a moment.');
        }
        try {
            $order = self::order($customerId, $orderNumber);   // re-read under the lock
            self::assertPayable($order);

            // 1. Reuse or close existing sessions.
            $open = DB::table('payments')->where('order_id', $order->id)->where('provider', 'stripe')
                ->whereNotNull('provider_transaction_id')->where('status', 'pending')->orderByDesc('id')->get(['id', 'provider_transaction_id']);
            $reuse = null;
            foreach ($open as $row) {
                $session = Stripe::retrieveCheckoutSession($row->provider_transaction_id);
                if (($session['status'] ?? null) === 'complete') {
                    abort(409, 'We have received a payment for this order and are confirming it. Please refresh in a moment.');
                }
                if (! $reuse && ($session['status'] ?? null) === 'open' && ($session['expires_at'] ?? 0) > time() + self::REUSE_MARGIN
                    && (int) ($session['amount_total'] ?? -1) === Stripe::cents(self::amountDue($order))) {
                    $reuse = $session;
                }
            }
            if ($reuse) {
                return ['redirectUrl' => (string) $reuse['url'], 'sessionId' => (string) $reuse['id']];
            }

            // 2. New attempt row FIRST, so an "expired" webhook for the old session never sees the order as abandoned.
            $paymentId = self::attemptRow($order);
            if (! self::closeSessions($order->id, except: $paymentId)) {
                abort(409, 'We have received a payment for this order and are confirming it. Please refresh in a moment.');
            }

            // 3. Create the session (idempotency key per attempt row: a network retry can't create a second session).
            // Idempotency key = attempt row + a hash of the exact request. Stripe remembers a key for 24 h and answers a
            // reused key with DIFFERENT parameters (here: a new expires_at on every try) with HTTP 400 idempotency_error.
            // A key built from the row id alone therefore made every retry of a row whose first create call failed
            // (timeout, 5xx, response lost) fail for a day ("payment server failure"), and made two databases that share
            // one Stripe account (local/dev) collide on row ids. Same request → same key (a true retry still can't make a
            // second session); different request → fresh key.
            $params = self::sessionParams($order, $paymentId);
            $session = Stripe::createCheckoutSession($params, 'shaotal-pay-'.$paymentId.'-'.substr(hash('sha256', json_encode($params)), 0, 16));
            DB::table('payments')->where('id', $paymentId)->whereNull('provider_transaction_id')->update([
                'provider_transaction_id' => $session['id'],
                // The plan snapshot (rates, discount, deposit, balance) stays with the order's payment rows.
                'response_data' => json_encode(array_filter(['mode' => Stripe::mode(), 'session_status' => $session['status'] ?? null, 'events' => [],
                    'plan' => OnlinePaymentPlan::forOrder($order->id)], fn ($v) => $v !== null)),
                'updated_at' => now(),
            ]);

            return ['redirectUrl' => (string) $session['url'], 'sessionId' => (string) $session['id']];
        } catch (StripeException $e) {
            Log::warning('Stripe checkout session not created', ['order' => $orderNumber, 'error' => $e->getMessage()]);
            abort(503, 'We could not reach our payment provider. Your order is saved: please try paying again in a moment.');
        } finally {
            $lock->release();
        }
    }

    private static function assertPayable(?object $order): void
    {
        abort_unless($order, 404);
        abort_unless($order->payment_method === 'card', 422, 'This order is not paid by card.');
        abort_if($order->payment_status === 'paid' || self::paidTotal($order->id) > 0, 409, 'We have already received your payment for this order.');
        abort_unless($order->order_status === 'pending' && in_array($order->payment_status, self::PAYABLE, true), 422,
            'This order can no longer be paid online. Please contact us for help.');
        abort_if(Stripe::cents(self::amountDue($order)) < Stripe::MIN_AMOUNT_CENTS, 422, 'This payment is too small for a card payment.');
    }

    /** The placement row (no session yet) or a new pending attempt row. */
    private static function attemptRow(object $order): int
    {
        $unused = DB::table('payments')->where('order_id', $order->id)->where('provider', 'stripe')
            ->whereNull('provider_transaction_id')->where('status', 'pending')->orderByDesc('id')->value('id');
        if ($unused) {
            // Amount always follows the order (the authoritative server figure: the deposit).
            DB::table('payments')->where('id', $unused)->update(['amount' => self::amountDue($order), 'updated_at' => now()]);

            return (int) $unused;
        }

        return (int) DB::table('payments')->insertGetId([
            'order_id' => $order->id, 'provider' => 'stripe', 'payment_method' => 'card', 'amount' => self::amountDue($order), 'status' => 'pending',
        ]);
    }

    private static function sessionParams(object $order, int $paymentId): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $meta = ['order_number' => $order->order_number, 'payment_id' => (string) $paymentId];
        $minutes = max(30, min(1440, (int) config('services.stripe.session_minutes', 60)));
        $plan = OnlinePaymentPlan::forOrder($order->id);
        $due = self::amountDue($order);

        return array_filter([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'client_reference_id' => $order->order_number,
            'customer_email' => $order->customer_email ?: null,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => config('services.stripe.currency'),
                    // One line: what Stripe charges is exactly the server-computed amount due now (the pre-booking deposit;
                    // for an order placed without a plan, the whole total).
                    'unit_amount' => Stripe::cents($due),
                    'product_data' => ['name' => config('storefront.brand').($plan ? ' pre-booking deposit, order ' : ' order ').$order->order_number],
                ],
            ]],
            'metadata' => $meta,
            'payment_intent_data' => ['metadata' => $meta, 'description' => config('storefront.brand').($plan ? ' pre-booking deposit, order ' : ' order ').$order->order_number],
            'expires_at' => time() + $minutes * 60,
            'success_url' => $base.'/checkout/payment/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $base.'/checkout/payment/cancel?order='.rawurlencode($order->order_number),
        ], fn ($v) => $v !== null);
    }

    /**
     * Expire every open Stripe session of the order (except one attempt row) and mark those rows cancelled.
     * Returns false when a session is already complete (paid or payment processing): the caller must not start
     * another payment or cancel the order.
     */
    public static function closeSessions(int $orderId, ?int $except = null): bool
    {
        $rows = DB::table('payments')->where('order_id', $orderId)->where('provider', 'stripe')->where('status', 'pending')
            ->whereNotNull('provider_transaction_id')->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->get(['id', 'provider_transaction_id']);
        foreach ($rows as $row) {
            $session = Stripe::expireCheckoutSession($row->provider_transaction_id);
            if (($session['status'] ?? null) === 'complete') {
                return false;
            }
            DB::table('payments')->where('id', $row->id)->where('status', 'pending')->update(['status' => 'cancelled', 'updated_at' => now()]);
        }

        return true;
    }

    /**
     * Customer cancellation of a card order: close its sessions at Stripe first. False when a payment already went
     * through (the order must then not be cancelled online). Throws 503 when Stripe can't be reached.
     */
    public static function prepareCancel(int $customerId, string $orderNumber): bool
    {
        $order = self::order($customerId, $orderNumber);
        if (! $order || $order->payment_method !== 'card') {
            return true;
        }
        if ($order->payment_status === 'paid' || self::paidTotal($order->id) > 0) {
            return false;   // money (a deposit or the full amount) was received: staff handle any refund
        }
        try {
            return self::closeSessions($order->id);
        } catch (StripeException $e) {
            Log::warning('Stripe session not closed before cancel', ['order' => $orderNumber, 'error' => $e->getMessage()]);
            abort(503, 'We could not reach our payment provider. Please try again in a moment.');
        }
    }

    /**
     * Apply a verified webhook event. Returns a short outcome code (logged; also used by tests). Throws only on
     * infrastructure errors so Stripe retries the delivery.
     */
    public static function applyEvent(array $event): string
    {
        $type = (string) $event['type'];
        $object = (array) $event['data']['object'];

        $target = match ($type) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => 'paid',
            'checkout.session.async_payment_failed' => 'failed',
            'checkout.session.expired' => 'expired',
            'payment_intent.payment_failed' => 'declined',
            default => null,
        };
        if (! $target) {
            return 'ignored';
        }
        if ((bool) ($event['livemode'] ?? false) !== (Stripe::mode() === 'live')) {
            Log::error('Stripe event mode does not match STRIPE_MODE', ['event' => $event['id'], 'type' => $type]);

            return 'mode_mismatch';
        }

        $isSession = str_starts_with($type, 'checkout.session.');
        $meta = (array) ($object['metadata'] ?? []);

        return DB::transaction(function () use ($event, $type, $object, $target, $isSession, $meta) {
            $q = DB::table('payments')->where('provider', 'stripe')->lockForUpdate();
            $payment = $isSession
                ? (clone $q)->where('provider_transaction_id', (string) ($object['id'] ?? ''))->first()
                : null;
            // Session id not stored yet (crash between Stripe and our update) or a PaymentIntent event: our metadata.
            if (! $payment && ctype_digit((string) ($meta['payment_id'] ?? ''))) {
                $payment = (clone $q)->where('id', (int) $meta['payment_id'])->first();
                if ($payment && $isSession && $payment->provider_transaction_id && $payment->provider_transaction_id !== ($object['id'] ?? null)) {
                    $payment = null;
                }
            }
            if (! $payment) {
                Log::info('Stripe event for unknown payment ignored', ['event' => $event['id'], 'type' => $type]);

                return 'unknown_payment';
            }
            $order = DB::table('orders')->where('id', $payment->order_id)->lockForUpdate()
                ->first(['id', 'order_number', 'customer_id', 'grand_total', 'payment_status', 'order_status']);
            if (($meta['order_number'] ?? null) !== $order->order_number) {
                Log::error('Stripe event order mismatch', ['event' => $event['id'], 'payment' => $payment->id]);

                return 'order_mismatch';
            }

            $data = json_decode((string) $payment->response_data, true) ?: [];
            $seen = (array) ($data['events'] ?? []);
            if (in_array($event['id'], $seen, true)) {
                return 'duplicate';
            }
            $data['events'] = array_slice(array_merge($seen, [$event['id']]), -self::EVENT_MEMORY);
            if ($isSession) {
                $data['session_status'] = $object['status'] ?? null;
                $data['payment_status'] = $object['payment_status'] ?? null;
                $data['payment_intent'] = is_string($object['payment_intent'] ?? null) ? $object['payment_intent'] : ($data['payment_intent'] ?? null);
            } else {
                $data['payment_intent'] = $object['id'] ?? null;
                // Decline reason only (no card details exist in this payload's fields we keep).
                $data['last_error'] = $object['last_payment_error']['decline_code'] ?? $object['last_payment_error']['code'] ?? null;
            }
            $save = fn (array $changes) => DB::table('payments')->where('id', $payment->id)
                ->update($changes + ['response_data' => json_encode($data), 'updated_at' => now()]);

            // Checkout completed but the payment method is asynchronous and not settled yet: stays pending.
            if ($type === 'checkout.session.completed' && ($object['payment_status'] ?? null) !== 'paid') {
                $save([]);

                return 'awaiting_async';
            }

            return match ($target) {
                'paid' => self::markPaid($payment, $order, $object, $save),
                'failed', 'declined' => self::markFailed($payment, $order, $save, $target),
                'expired' => self::markExpired($payment, $order, $save),
            };
        });
    }

    /**
     * Safety net for a webhook that never arrives (endpoint down, local development where Stripe cannot reach the
     * machine, delivery delayed past the customer's return). For card attempts still waiting, ask Stripe (server to
     * server, secret key) for the session and feed it through the SAME idempotent applyEvent() a webhook uses: the
     * amount/currency/order checks, forward-only transitions, cart clearing and email dedupe all apply unchanged.
     * The browser redirect is never trusted; only Stripe's own API answer is.
     *
     * @return array<string, int> outcome => count
     */
    public static function reconcilePending(int $olderThanMinutes = 2, int $limit = 50): array
    {
        $rows = DB::table('payments')->where('provider', 'stripe')->whereIn('status', ['pending', 'failed'])
            ->whereNotNull('provider_transaction_id')
            ->where('created_at', '<=', now()->subMinutes($olderThanMinutes))->where('created_at', '>=', now()->subDays(3))
            ->orderBy('id')->limit($limit)->pluck('provider_transaction_id');
        $outcomes = [];
        foreach ($rows as $sessionId) {
            try {
                $session = Stripe::retrieveCheckoutSession($sessionId);
                $type = match (true) {
                    ($session['status'] ?? null) === 'complete' && ($session['payment_status'] ?? null) === 'paid' => 'checkout.session.completed',
                    ($session['status'] ?? null) === 'expired' => 'checkout.session.expired',
                    default => null,
                };
                if (! $type) {
                    $outcomes['still_open'] = ($outcomes['still_open'] ?? 0) + 1;

                    continue;
                }
                $result = self::applyEvent([
                    'id' => 'recon_'.$type.'_'.$sessionId, 'type' => $type, 'livemode' => Stripe::mode() === 'live', 'data' => ['object' => $session],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Stripe reconcile failed for a session', ['error' => $e->getMessage()]);
                $result = 'error';
            }
            $outcomes[$result] = ($outcomes[$result] ?? 0) + 1;
        }

        return $outcomes;
    }

    private static function markPaid(object $payment, object $order, array $session, callable $save): string
    {
        if ($payment->status === 'paid') {
            $save([]);

            return 'already_paid';
        }
        // The charged amount and currency must be exactly what this order row asked for.
        $amountOk = (int) ($session['amount_total'] ?? -1) === Stripe::cents($payment->amount)
            && strtolower((string) ($session['currency'] ?? '')) === config('services.stripe.currency');
        if (! $amountOk) {
            Log::critical('Stripe paid amount/currency does not match the order', ['order' => $order->order_number, 'payment' => $payment->id]);
            $save([]);
            DB::table('order_timeline')->insert([
                'order_id' => $order->id, 'old_status' => $order->order_status, 'new_status' => $order->order_status,
                'note' => 'Card payment amount did not match the order total: staff review needed (Stripe)', 'created_at' => now(),
            ]);

            return 'amount_mismatch';
        }

        $save(['status' => 'paid', 'paid_at' => now()]);

        // Money received for this order, this payment included. A deposit (less than grand_total) is a pre-booking: the order
        // stays payment_status "pending" and is NOT paid; only a total that reaches grand_total makes it "paid".
        $grandCents = Stripe::cents($order->grand_total);
        $paidCents = Stripe::cents(self::paidTotal($order->id));
        $fullyPaid = $paidCents >= $grandCents;
        $firstPayment = ! DB::table('payments')->where('order_id', $order->id)->where('status', 'paid')->where('id', '!=', $payment->id)->exists();
        $symbol = config('storefront.currency_symbol');
        $fmt = fn (int $c) => $symbol.number_format($c / 100, 2);

        if ($fullyPaid) {
            $note = 'Card payment received (Stripe)';
        } else {
            $note = 'Pre-booking deposit of '.$fmt(Stripe::cents($payment->amount)).' received (Stripe). Remaining balance of '.$fmt($grandCents - $paidCents).' is due later';
        }
        if (! $firstPayment) {
            // A second session was paid (should be impossible: sessions are expired before a new one is made, and a paid order is never re-offered).
            $note = 'Second card payment received for this order: refund one in Stripe (staff)';
            Log::critical('Duplicate Stripe payment for an order', ['order' => $order->order_number, 'payment' => $payment->id]);
        } elseif ($order->order_status === 'cancelled') {
            $note = 'Card payment received after the order was cancelled: refund needed (staff)';
            Log::critical('Stripe payment for a cancelled order', ['order' => $order->order_number, 'payment' => $payment->id]);
        }
        // 'failed' (an earlier declined attempt) goes back to 'pending' once money has arrived; never 'paid' for a deposit.
        $orderStatus = $fullyPaid ? 'paid' : ($order->payment_status === 'failed' ? 'pending' : $order->payment_status);
        DB::table('orders')->where('id', $order->id)->update(['payment_status' => $orderStatus, 'updated_at' => now()]);
        DB::table('order_timeline')->insert([
            'order_id' => $order->id, 'old_status' => $order->order_status, 'new_status' => $order->order_status, 'note' => $note, 'created_at' => now(),
        ]);

        // Confirmed by Stripe (webhook): the customer's saved bag has become this order (COD does this at placement).
        if ($order->customer_id && $firstPayment) {
            $carts = DB::table('carts')->where('customer_id', $order->customer_id)->where('status', 'active')->pluck('id');
            DB::table('cart_items')->whereIn('cart_id', $carts)->delete();
            DB::table('carts')->whereIn('id', $carts)->update(['status' => 'converted']);
        }
        // Verified payment: now (and only now) the order confirmation email may go out. Deduplicated per order, so a repeated
        // or reconciled event never sends a second one; a cancelled order gets none.
        if ($firstPayment && $order->order_status !== 'cancelled') {
            OrderEmails::queue($order->order_number);
        }

        return 'paid';
    }

    private static function markFailed(object $payment, object $order, callable $save, string $why): string
    {
        if ($payment->status !== 'pending') {
            $save([]);

            return 'no_change';
        }
        // A declined card on the hosted page leaves the session open (the customer can try another card), so the row
        // stays usable; it is "failed" until a later success moves it to paid.
        $save(['status' => 'failed']);
        if ($order->payment_status === 'pending' && self::paidTotal($order->id) <= 0) {
            DB::table('orders')->where('id', $order->id)->update(['payment_status' => 'failed', 'updated_at' => now()]);
        }

        return $why;
    }

    private static function markExpired(object $payment, object $order, callable $save): string
    {
        if (! in_array($payment->status, ['pending', 'failed'], true)) {
            $save([]);

            return 'no_change';
        }
        $save(['status' => 'cancelled']);

        // Unpaid order whose last payment window has closed: cancel it, so it does not wait for staff and the coupon is
        // released. Not when another attempt is in progress or staff have already moved the order on.
        $otherAttempt = DB::table('payments')->where('order_id', $order->id)->where('id', '!=', $payment->id)
            ->whereIn('status', ['pending', 'paid'])->exists();
        if ($otherAttempt || $order->payment_status === 'paid' || $order->order_status !== 'pending') {
            return 'expired';
        }
        DB::table('orders')->where('id', $order->id)->update([
            'order_status' => 'cancelled', 'cancelled_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('order_timeline')->insert([
            'order_id' => $order->id, 'old_status' => 'pending', 'new_status' => 'cancelled', 'note' => 'Card payment not completed in time', 'created_at' => now(),
        ]);

        return 'expired_order_cancelled';
    }

    /** Payment attempts for the order page (newest first). No provider ids or payloads are shown. */
    public static function history(int $orderId, ?float $grandTotal = null): array
    {
        return DB::table('payments')->where('order_id', $orderId)->orderByDesc('id')
            ->get(['payment_method', 'provider', 'amount', 'status', 'paid_at', 'created_at'])
            ->map(fn ($p) => [
                'method' => $p->provider === 'stripe' ? 'Card (Stripe)' : PaymentMethods::label($p->payment_method),
                'amount' => (float) $p->amount,
                'status' => match ($p->status) {
                    'pending' => 'Awaiting payment', 'failed' => 'Declined', 'cancelled' => 'Not completed',
                    'paid' => ($grandTotal !== null && Stripe::cents($p->amount) < Stripe::cents($grandTotal)) ? 'Deposit paid' : 'Paid',
                    default => ucwords(str_replace('_', ' ', $p->status)),
                },
                'statusKey' => $p->status,
                'at' => DisplayTime::dateTime($p->paid_at ?? $p->created_at),
            ])->all();
    }

    /** Order + latest attempt for the Stripe return pages, scoped to the customer; null when not theirs. */
    public static function forSession(int $customerId, string $sessionId): ?object
    {
        return DB::table('payments as p')->join('orders as o', 'o.id', '=', 'p.order_id')
            ->where('p.provider', 'stripe')->where('p.provider_transaction_id', $sessionId)->where('o.customer_id', $customerId)
            ->first(['o.id', 'o.order_number', 'o.grand_total', 'o.payment_status', 'o.order_status', 'o.payment_method', 'p.status as attempt_status', 'p.amount as attempt_amount']);
    }
}
