<?php

namespace App\Support;

/**
 * Customer-facing view of the shared orders.order_status / payment_status enums (sql/ecom.sql), which the admin app owns.
 * Customers see nine delivery stages. orders.order_status has only five matching values (pending, confirmed, processing,
 * shipped, delivered), so the four finer stages (SUB_STAGES) are read from order_timeline.new_status (VARCHAR) rows that the
 * admin records alongside the enum change; see docs/admin-order-integration.md. Internal values are never shown.
 * There is still no courier/tracking column, so no tracking number is displayed.
 *
 * Timeline timestamps come only from order_timeline rows (written by the storefront at placement and by the admin on each
 * status change) or orders.placed_at / cancelled_at. A step reached without a recorded row is shown as done with no date.
 */
class OrderStatus
{
    /** Normal progression, in order: stage key => customer label. Keys that are order_status values map 1:1 to the enum. */
    public const STEPS = [
        'pending' => 'Order Placed',
        'confirmed' => 'Order Confirmed',
        'processing' => 'Processing',
        'ready_for_dispatch' => 'Ready for Dispatch',
        'shipped' => 'Dispatched',
        'in_transit' => 'In Transit',
        'arrived_at_hub' => 'Arrived at Delivery Hub',
        'out_for_delivery' => 'Out for Delivery',
        'delivered' => 'Delivered',
    ];

    /** Stages with no order_status value: recorded as order_timeline.new_status while orders.order_status is the parent. */
    public const SUB_STAGES = [
        'ready_for_dispatch' => 'processing',
        'in_transit' => 'shipped',
        'arrived_at_hub' => 'shipped',
        'out_for_delivery' => 'shipped',
    ];

    /** One-line customer descriptions for each stage. */
    public const DESCRIPTIONS = [
        'pending' => 'We have received your order.',
        'confirmed' => 'Your order has been confirmed.',
        'processing' => 'We are preparing your items.',
        'ready_for_dispatch' => 'Your order is packed and ready to be handed to the courier.',
        'shipped' => 'Your order has been handed to the courier.',
        'in_transit' => 'Your order is on its way.',
        'arrived_at_hub' => 'Your order has reached the delivery hub near you.',
        'out_for_delivery' => 'Your order is out for delivery today.',
        'delivered' => 'Your order has been delivered.',
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

    public static function description(?string $stage): string
    {
        return self::DESCRIPTIONS[$stage] ?? '';
    }

    /**
     * The stage a customer sees: the order_status, refined by the furthest recorded sub-stage that belongs to it.
     *
     * @param  iterable<string>  $recorded  order_timeline.new_status values for this order (any; unknown ones are ignored)
     */
    public static function stage(?string $status, iterable $recorded = []): ?string
    {
        if (! isset(self::STEPS[$status])) {
            return $status;
        }
        $keys = array_keys(self::STEPS);
        $best = array_search($status, $keys, true);
        foreach ($recorded as $r) {
            if ((self::SUB_STAGES[$r] ?? null) === $status && ($i = array_search($r, $keys, true)) > $best) {
                $best = $i;
            }
        }

        return $keys[$best];
    }

    /** CSS modifier for .order-status-badge (existing design classes: delivered, in-transit, processing). Takes a stage or status. */
    public static function badgeClass(?string $status): string
    {
        return match ($status) {
            'delivered' => 'delivered',
            'shipped', 'in_transit', 'arrived_at_hub', 'out_for_delivery' => 'in-transit',
            'confirmed', 'processing', 'ready_for_dispatch' => 'processing',
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
    public static function paymentLabel(?string $paymentStatus, ?string $method, ?string $orderStatus, float $paid = 0.0, float $total = 0.0): string
    {
        // Money received but less than the order total (a pre-booking deposit): never "Paid".
        if ($paymentStatus === 'pending' && $paid > 0 && $paid < $total) {
            return 'Pre-booked (deposit paid)';
        }
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
     * @return array{steps: array<int, array{key:string,label:string,description:string,state:string,at:?string}>, exception: ?array{key:string,label:string,at:?string}}
     */
    public static function timeline(string $current, iterable $history, ?string $placedAt, ?string $cancelledAt): array
    {
        // First recorded time each status was entered (a status re-entered later keeps its first time).
        $firstAt = [];
        $reachedBeforeException = null;
        foreach ($history as $h) {
            $firstAt[$h->new_status] ??= $h->created_at;
            if (isset(self::EXCEPTIONS[$h->new_status]) && $h->old_status !== null && isset(self::STEPS[$h->old_status])) {
                $reachedBeforeException = self::stage($h->old_status, array_keys(array_intersect_key($firstAt, self::SUB_STAGES)));
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
            $reached = isset(self::STEPS[$current]) ? self::stage($current, array_keys(array_intersect_key($firstAt, self::SUB_STAGES))) : 'pending';
        }
        $reachedIndex = array_search($reached, $keys, true);

        $steps = [];
        foreach ($keys as $i => $key) {
            $state = $i < $reachedIndex ? 'done' : ($i === $reachedIndex ? ($exception || $key === 'delivered' ? 'done' : 'current') : 'upcoming');
            $steps[] = ['key' => $key, 'label' => self::STEPS[$key], 'description' => self::DESCRIPTIONS[$key], 'state' => $state, 'at' => $state === 'upcoming' ? null : ($firstAt[$key] ?? null)];
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
