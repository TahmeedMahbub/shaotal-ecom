<?php

namespace App\Support;

use App\Jobs\SendOrderConfirmationEmail;
use App\Mail\OrderConfirmationMail;
use App\Models\CardPayments;
use App\Models\CustomerOrders;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Order confirmation email: when it is queued, how duplicates are prevented, and how a failure is recovered.
 *
 *  - WHEN: COD → right after the order is committed. Card → right after the VERIFIED payment (webhook), never at
 *    placement, so the email can never claim a payment that has not been confirmed (an abandoned card order sends nothing).
 *  - OFF THE REQUEST: a queued job (storefront.order_mail_queue: "deferred" = right after the response, no worker
 *    needed; "database"/"redis" = a real queue with retries). It is dispatched only after the DB transaction commits.
 *  - DUPLICATES: one state record per order, kept in payments.response_data → emails.order_confirmation on the order's
 *    first payment row (no schema change; never shown to customers), changed under a row lock. States:
 *    queued → sending → sent | failed. A retried webhook, a double click or a re-run job finds "sent" and stops.
 *  - FAILURE: logged (order number + error class; never addresses or SMTP details), state "failed" with an attempt
 *    count. `php artisan storefront:retry-order-emails` (scheduled every 5 min) re-queues queued/failed/stuck ones up
 *    to MAX_ATTEMPTS times. If the queue itself is down, the email is still sent right after the response.
 */
class OrderEmails
{
    public const KEY = 'order_confirmation';

    public const MAX_ATTEMPTS = 6;

    /** A "sending" claim older than this is treated as a crashed worker and may be claimed again (minutes). */
    private const STALE_MINUTES = 10;

    /** Queue the confirmation for an order whose state has just been committed (safe to call inside a transaction). */
    public static function queue(string $orderNumber): void
    {
        DB::afterCommit(fn () => self::push($orderNumber, false));
    }

    private static function push(string $orderNumber, bool $retry): void
    {
        try {
            if ($retry) {
                $orderId = (int) DB::table('orders')->where('order_number', $orderNumber)->value('id');
                if (! $orderId || ! self::record($orderId, 'queued')) {
                    return;
                }
            } elseif (! self::markQueued($orderNumber)) {
                return;   // unknown order, or already queued / sending / sent
            }
            SendOrderConfirmationEmail::dispatch($orderNumber);
        } catch (Throwable $e) {
            // The queue itself is unavailable: the email must not be lost, so send it right after the response.
            report($e);
            defer(function () use ($orderNumber) {
                try {
                    self::deliver($orderNumber);
                } catch (Throwable) {
                    // already logged and recorded as failed by deliver(); the retry command picks it up
                }
            });
        }
    }

    /**
     * Send the confirmation now (called by the job / fallback). Returns "sent", "already_sent", "in_progress" or
     * "not_ready" (card order without a verified payment). Throws on a send failure so the queue can retry.
     */
    public static function deliver(string $orderNumber): string
    {
        $order = DB::table('orders')->where('order_number', $orderNumber)
            ->first(['id', 'customer_id', 'customer_email', 'payment_method', 'shipping_recipient_name']);
        if (! $order || ! $order->customer_id || ! $order->customer_email) {
            return 'not_ready';
        }
        if ($order->payment_method === 'card' && CardPayments::paidTotal($order->id) <= 0) {
            return 'not_ready';   // never confirm a card order before Stripe's verified payment
        }
        $attempt = self::claim($order->id);
        if ($attempt === null) {
            return self::state($order->id) === 'sent' ? 'already_sent' : 'in_progress';
        }

        try {
            $data = CustomerOrders::find((int) $order->customer_id, $orderNumber);
            if (! $data) {
                throw new \RuntimeException('order not found for confirmation');
            }
            $first = trim((string) DB::table('customers')->where('id', $order->customer_id)->value('first_name'));
            $first = $first !== '' ? $first : (explode(' ', trim((string) $order->shipping_recipient_name))[0] ?: 'there');
            Mail::to($order->customer_email)->send(new OrderConfirmationMail($data, $first));
        } catch (Throwable $e) {
            self::record($order->id, 'failed', $e);
            Log::warning('Order confirmation email failed', ['order' => $orderNumber, 'attempt' => $attempt, 'error' => $e::class]);
            throw $e;
        }
        self::record($order->id, 'sent');

        return 'sent';
    }

    /** The job gave up: keep the state "failed" (the retry command and the log show it). */
    public static function giveUp(string $orderNumber, ?Throwable $e): void
    {
        if ($id = DB::table('orders')->where('order_number', $orderNumber)->value('id')) {
            self::record((int) $id, 'failed', $e);
        }
        Log::error('Order confirmation email gave up', ['order' => $orderNumber, 'error' => $e ? $e::class : null]);
    }

    /**
     * Re-queue confirmations that are stuck: queued / failed / stale "sending", fewer than MAX_ATTEMPTS tries, orders of the
     * last 3 days. Orders that never had a confirmation queued (placed before this feature) are NOT touched.
     *
     * @return int how many were re-queued
     */
    public static function retryStuck(): int
    {
        $rows = DB::table('payments as p')->join('orders as o', 'o.id', '=', 'p.order_id')
            ->whereRaw('p.id = (select min(p2.id) from payments p2 where p2.order_id = o.id)')
            ->where('o.placed_at', '>=', now()->subDays(3))->where('o.order_status', '!=', 'cancelled')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(p.response_data, '$.emails.".self::KEY.".state')) IN ('queued', 'sending', 'failed')")
            ->orderBy('p.id')->limit(100)->get(['o.order_number', 'p.response_data']);
        $n = 0;
        foreach ($rows as $r) {
            $e = (json_decode((string) $r->response_data, true)['emails'][self::KEY] ?? []);
            $waitMinutes = ($e['state'] ?? '') === 'sending' ? self::STALE_MINUTES : 5;
            $ageMinutes = isset($e['at']) ? now()->diffInMinutes($e['at'], true) : 999;
            if ((int) ($e['attempts'] ?? 0) >= self::MAX_ATTEMPTS || $ageMinutes < $waitMinutes) {
                continue;
            }
            self::push($r->order_number, true);
            $n++;
        }

        return $n;
    }

    /**
     * "Queue once" guard in ONE statement (this runs inside the customer's request, so round trips count): writes state
     * "queued" on the order's first payment row only if no email state exists yet. True when this call wrote it.
     */
    private static function markQueued(string $orderNumber): bool
    {
        $sql = <<<'SQL'
UPDATE payments
SET response_data = JSON_SET(COALESCE(response_data, JSON_OBJECT()), '$.emails', JSON_MERGE_PATCH(COALESCE(JSON_EXTRACT(response_data, '$.emails'), JSON_OBJECT()),
      JSON_OBJECT('order_confirmation', JSON_OBJECT('state', 'queued', 'attempts', 0, 'at', ?))))
WHERE id = (SELECT id FROM (SELECT MIN(p.id) AS id FROM payments p JOIN orders o ON o.id = p.order_id WHERE o.order_number = ?) first_payment)
  AND JSON_EXTRACT(COALESCE(response_data, JSON_OBJECT()), '$.emails.order_confirmation') IS NULL
SQL;

        return DB::update($sql, [now()->toDateTimeString(), $orderNumber]) === 1;
    }

    /** Recorded state: queued | sending | sent | failed, or null (never queued). */
    public static function state(int $orderId): ?string
    {
        return self::read($orderId)['state'] ?? null;
    }

    private static function firstPaymentId(int $orderId): ?int
    {
        $id = DB::table('payments')->where('order_id', $orderId)->orderBy('id')->value('id');

        return $id ? (int) $id : null;
    }

    private static function read(int $orderId): array
    {
        $json = DB::table('payments')->where('id', self::firstPaymentId($orderId))->value('response_data');

        return json_decode((string) $json, true)['emails'][self::KEY] ?? [];
    }

    /**
     * Write the state under a row lock. With $onlyIfEmpty nothing is written when a record already exists (the "queue
     * once" guard). "sent" is never downgraded. The attempt count is kept. Returns whether anything was written.
     */
    private static function record(int $orderId, string $state, ?Throwable $e = null, bool $onlyIfEmpty = false): bool
    {
        $pid = self::firstPaymentId($orderId);
        if (! $pid) {
            return true;   // no payment row to hold the record (not a storefront order): nothing to guard
        }

        return DB::transaction(function () use ($pid, $state, $e, $onlyIfEmpty) {
            $row = DB::table('payments')->where('id', $pid)->lockForUpdate()->first(['response_data']);
            $data = json_decode((string) $row->response_data, true) ?: [];
            $cur = $data['emails'][self::KEY] ?? null;
            if (($onlyIfEmpty && $cur !== null) || (($cur['state'] ?? null) === 'sent' && $state !== 'sent')) {
                return false;
            }
            $data['emails'][self::KEY] = array_filter([
                'state' => $state, 'attempts' => (int) ($cur['attempts'] ?? 0), 'at' => now()->toDateTimeString(), 'error' => $e ? $e::class : null,
            ], fn ($v) => $v !== null);
            DB::table('payments')->where('id', $pid)->update(['response_data' => json_encode($data)]);

            return true;
        });
    }

    /** Take the right to send: null when already sent or another worker holds a fresh claim; otherwise the attempt number. */
    private static function claim(int $orderId): ?int
    {
        $pid = self::firstPaymentId($orderId);
        if (! $pid) {
            return 1;
        }

        return DB::transaction(function () use ($pid) {
            $row = DB::table('payments')->where('id', $pid)->lockForUpdate()->first(['response_data']);
            $data = json_decode((string) $row->response_data, true) ?: [];
            $cur = $data['emails'][self::KEY] ?? [];
            if (($cur['state'] ?? null) === 'sent'
                || (($cur['state'] ?? null) === 'sending' && isset($cur['at']) && now()->diffInMinutes($cur['at'], true) < self::STALE_MINUTES)) {
                return null;
            }
            $attempt = (int) ($cur['attempts'] ?? 0) + 1;
            $data['emails'][self::KEY] = ['state' => 'sending', 'attempts' => $attempt, 'at' => now()->toDateTimeString()];
            DB::table('payments')->where('id', $pid)->update(['response_data' => json_encode($data)]);

            return $attempt;
        });
    }
}
