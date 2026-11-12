<?php

namespace App\Support;

/**
 * Customer-facing view of the shared orders.order_status / payment_status enums (sql/ecom.sql), which the admin app owns.
 * Only statuses that exist in the schema are shown: there is no "out for delivery" or "failed delivery" status and no
 * courier/tracking column, so neither is displayed (see STOREFRONT_PRODUCTION_READINESS_PLAN.md, Part 3).
 *
 * Timeline timestamps come only from order_timeline rows (written by the storefront at placement and by the admin on each
 * status change) or orders.placed_at / cancelled_at. A step reached without a recorded row is shown as done with no date.
 */
class OrderStatus
{
    /** Normal progression, in order: DB status => customer label. */
    public const STEPS = [
        'pending' => 'Order Placed',
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
    ];

    /** Exception states (end the normal progression). */
    public const EXCEPTIONS = [
        'cancelled' => 'Cancelled',
        'returned' => 'Returned',
    ];

    /** Every order_status value in sql/ecom.sql. */
    public const ALL = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];

    /**
     * Forward-only transitions the storefront considers valid. The storefront itself only performs pending → cancelled
     * (customer cancellation); the admin app performs the rest and should enforce the same table (documented admin change).
     */
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'processing', 'shipped', 'delivered', 'cancelled'],
        'confirmed' => ['processing', 'shipped', 'delivered', 'cancelled'],
        'processing' => ['shipped', 'delivered', 'cancelled'],
        'shipped' => ['delivered', 'returned'],
        'delivered' => ['returned'],
        'cancelled' => [],
        'returned' => [],
    ];

    public const PAYMENT = [
        'pending' => 'Payment Pending',
        'paid' => 'Paid',
        'failed' => 'Payment Failed',
        'partially_refunded' => 'Partially Refunded',
        'refunded' => 'Refunded',
    ];

    public static function label(?string $status): string
    {
        return self::STEPS[$status] ?? self::EXCEPTIONS[$status] ?? ucwords(str_replace('_', ' ', (string) $status));
    }

    /** CSS modifier for .order-status-badge (existing design classes: delivered, in-transit, processing). */
    public static function badgeClass(?string $status): string
    {
        return match ($status) {
            'delivered' => 'delivered',
            'shipped' => 'in-transit',
            'confirmed', 'processing' => 'processing',
            'cancelled', 'returned' => 'cancelled',
            default => 'pending',
        };
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Payment wording for the customer. Delivery and payment are separate: a COD order stays "pending" until the admin
     * records the cash, and a cancelled unpaid COD order has nothing to pay (no refund is ever triggered here).
     */
    public static function paymentLabel(?string $paymentStatus, ?string $method, ?string $orderStatus): string
    {
        if ($paymentStatus === 'pending' && $orderStatus === 'cancelled') {
            return 'No payment due';
        }
        if ($paymentStatus === 'pending' && $method === 'cod') {
            return 'Pay on delivery';
        }

        return self::PAYMENT[$paymentStatus] ?? ucwords(str_replace('_', ' ', (string) $paymentStatus));
    }

    /**
     * Visual progress for the order details page.
     *
     * @param  iterable<object{old_status:?string,new_status:string,created_at:?string}>  $history  order_timeline rows, oldest first
     * @return array{steps: array<int, array{key:string,label:string,state:string,at:?string}>, exception: ?array{key:string,label:string,at:?string}}
     */
    public static function timeline(string $current, iterable $history, ?string $placedAt, ?string $cancelledAt): array
    {
        // First recorded time each status was entered (a status re-entered later keeps its first time).
        $firstAt = [];
        $reachedBeforeException = null;
        foreach ($history as $h) {
            $firstAt[$h->new_status] ??= $h->created_at;
            if (isset(self::EXCEPTIONS[$h->new_status]) && $h->old_status !== null && isset(self::STEPS[$h->old_status])) {
                $reachedBeforeException = $h->old_status;
            }
        }
        $firstAt['pending'] = $placedAt ?? ($firstAt['pending'] ?? null);

        $keys = array_keys(self::STEPS);
        $exception = null;
        if (isset(self::EXCEPTIONS[$current])) {
            // How far the order got before it was cancelled/returned: from the history when recorded; a return always
            // follows delivery or shipping; otherwise only "placed" is known.
            $reached = $reachedBeforeException
                ?? self::furthestRecorded($firstAt)
                ?? ($current === 'returned' ? 'delivered' : 'pending');
            $at = $current === 'cancelled' ? ($cancelledAt ?? $firstAt['cancelled'] ?? null) : ($firstAt[$current] ?? null);
            $exception = ['key' => $current, 'label' => self::EXCEPTIONS[$current], 'at' => $at];
        } else {
            $reached = isset(self::STEPS[$current]) ? $current : 'pending';
        }
        $reachedIndex = array_search($reached, $keys, true);

        $steps = [];
        foreach ($keys as $i => $key) {
            $state = $i < $reachedIndex ? 'done' : ($i === $reachedIndex ? ($exception || $key === 'delivered' ? 'done' : 'current') : 'upcoming');
            $steps[] = ['key' => $key, 'label' => self::STEPS[$key], 'state' => $state, 'at' => $state === 'upcoming' ? null : ($firstAt[$key] ?? null)];
        }
        if ($exception) {
            // Steps never reached before a cancellation are not "upcoming" any more.
            foreach ($steps as &$s) {
                if ($s['state'] === 'upcoming') {
                    $s['state'] = 'skipped';
                }
            }
            unset($s);
        }

        return ['steps' => $steps, 'exception' => $exception];
    }

    private static function furthestRecorded(array $firstAt): ?string
    {
        $found = null;
        foreach (array_keys(self::STEPS) as $key) {
            if ($key !== 'pending' && isset($firstAt[$key])) {
                $found = $key;
            }
        }

        return $found;
    }
}
