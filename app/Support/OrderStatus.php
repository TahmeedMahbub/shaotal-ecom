<?php

namespace App\Support;

/**
 * Customer-facing view of the shared orders.order_status / payment_status enums, which the admin app owns.
 * order_status has 11 values (sql/ecom.sql + the admin's ORDER STATUS PROGRESSION block): nine delivery stages plus
 * cancelled and returned. Fulfilment status and payment status are separate vocabularies and never derive from each other.
 * Orders from before the enum was extended recorded the four finer stages only in order_timeline.new_status while
 * order_status held the parent value; stage() still reads those rows so historical orders keep their progress.
 * Internal values are never shown. There is no courier/tracking column, so no tracking number is displayed.
 *
 * Timeline timestamps come only from order_timeline rows (written by the storefront at placement and by the admin on each
 * status change) or orders.placed_at / cancelled_at. A step reached without a recorded row is shown as done with no date.
 */
class OrderStatus
{
    /** Normal progression, in order: status => customer label. Every key is an orders.order_status value (same as the admin app). */
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

    /** LEGACY: before the enum had these values, they were only order_timeline.new_status rows under this parent order_status. */
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

    /** Every order_status value (all 11, identical to the admin app's OrderStatus::all()). */
    public const ALL = ['pending', 'confirmed', 'processing', 'ready_for_dispatch', 'shipped', 'in_transit', 'arrived_at_hub', 'out_for_delivery', 'delivered', 'cancelled', 'returned'];

    /**
     * Forward-only transitions, identical to the admin's OrderStatus::next() (a stage may be skipped for legacy/pickup
     * orders; cancelling is possible only before the parcel leaves, returning only from dispatch onward). The storefront
     * itself only performs pending → cancelled (customer cancellation); the admin performs the rest.
     */
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'processing', 'ready_for_dispatch', 'shipped', 'in_transit', 'arrived_at_hub', 'out_for_delivery', 'delivered', 'cancelled'],
        'confirmed' => ['processing', 'ready_for_dispatch', 'shipped', 'in_transit', 'arrived_at_hub', 'out_for_delivery', 'delivered', 'cancelled'],
        'processing' => ['ready_for_dispatch', 'shipped', 'in_transit', 'arrived_at_hub', 'out_for_delivery', 'delivered', 'cancelled'],
        'ready_for_dispatch' => ['shipped', 'in_transit', 'arrived_at_hub', 'out_for_delivery', 'delivered', 'cancelled'],
        'shipped' => ['in_transit', 'arrived_at_hub', 'out_for_delivery', 'delivered', 'returned'],
        'in_transit' => ['arrived_at_hub', 'out_for_delivery', 'delivered', 'returned'],
        'arrived_at_hub' => ['out_for_delivery', 'delivered', 'returned'],
        'out_for_delivery' => ['delivered', 'returned'],
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
            return 'Partially Paid';
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
