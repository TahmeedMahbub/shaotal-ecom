<?php

namespace App\Models;

use App\Payments\PaymentMethods;
use App\Support\DisplayTime;
use App\Support\OrderStatus;
use Illuminate\Support\Facades\DB;

/**
 * A signed-in customer's own orders. Every query is scoped by customer_id, so another customer's order number simply
 * does not exist for this customer (404, no enumeration). Reads the shared tables live (no cache): an admin status
 * change shows on the next page load.
 */
class CustomerOrders
{
    public const PER_PAGE = 10;

    /** Statuses a customer may cancel themselves (config storefront.customer_cancellable_statuses). */
    public static function cancellableStatuses(): array
    {
        return array_values(array_intersect((array) config('storefront.customer_cancellable_statuses', []), ['pending']));
    }

    /** @return array{orders: array, page: int, perPage: int, hasMore: bool} */
    public static function page(int $customerId, int $page): array
    {
        $page = max(1, min($page, 1000));
        $rows = DB::table('orders')->where('customer_id', $customerId)
            ->orderByDesc('created_at')->orderByDesc('id')   // index (customer_id, created_at)
            ->offset(($page - 1) * self::PER_PAGE)->limit(self::PER_PAGE + 1)
            ->get(['id', 'order_number', 'order_status', 'payment_status', 'payment_method', 'grand_total', 'placed_at', 'created_at']);
        $hasMore = $rows->count() > self::PER_PAGE;
        $rows = $rows->take(self::PER_PAGE);

        $ids = $rows->pluck('id')->all() ?: [0];
        $items = DB::table('order_items')->whereIn('order_id', $ids)->orderBy('id')
            ->get(['order_id', 'product_name', 'image_url', 'variant_color', 'variant_size', 'unit_selling_price', 'quantity'])->groupBy('order_id');

        return [
            'orders' => $rows->map(fn ($o) => [
                'id' => $o->order_number,
                'url' => self::url($o->order_number),
                'date' => DisplayTime::date($o->placed_at ?? $o->created_at),   // calendar date in Australia/Sydney
                'total' => (float) $o->grand_total,
                'status' => OrderStatus::label($o->order_status),
                'statusClass' => OrderStatus::badgeClass($o->order_status),
                'paymentMethod' => PaymentMethods::label($o->payment_method),
                'paymentStatus' => OrderStatus::paymentLabel($o->payment_status, $o->payment_method, $o->order_status),
                'itemCount' => (int) ($items[$o->id] ?? collect())->sum('quantity'),
                'items' => ($items[$o->id] ?? collect())->map(fn ($i) => [
                    'name' => $i->product_name, 'price' => (float) $i->unit_selling_price, 'quantity' => (int) $i->quantity,
                    'size' => $i->variant_size, 'color' => $i->variant_color, 'image' => $i->image_url,
                ])->values(),
            ])->values()->all(),
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'hasMore' => $hasMore,
        ];
    }

    public static function url(string $orderNumber): string
    {
        return '/account/orders/'.rawurlencode($orderNumber);
    }

    /** Full order for the details page, or null when it is not this customer's order. */
    public static function find(int $customerId, string $orderNumber): ?array
    {
        $o = DB::table('orders')->where('order_number', $orderNumber)->where('customer_id', $customerId)->first([
            'id', 'order_number', 'order_status', 'payment_status', 'payment_method', 'subtotal', 'discount_total', 'shipping_total',
            'tax_total', 'grand_total', 'coupon_code', 'shipping_recipient_name', 'shipping_phone', 'shipping_street_address',
            'shipping_suburb', 'shipping_state', 'shipping_postcode', 'customer_note', 'placed_at', 'cancelled_at', 'created_at',
        ]);
        if (! $o) {
            return null;
        }

        $items = DB::table('order_items')->where('order_id', $o->id)->orderBy('id')
            ->get(['product_id', 'product_name', 'sku', 'image_url', 'variant_color', 'variant_size', 'unit_price', 'unit_selling_price', 'quantity', 'line_discount', 'line_total']);
        // Links only for products that are still listed (status/visibility checked live; a delisted product shows as text).
        $slugs = DB::table('products')->whereIn('id', $items->pluck('product_id')->filter()->all() ?: [0])
            ->where('status', 'active')->where('visibility', 'visible')->pluck('slug', 'id');
        $history = DB::table('order_timeline')->where('order_id', $o->id)->orderBy('created_at')->orderBy('id')
            ->get(['old_status', 'new_status', 'created_at']);

        return [
            'number' => $o->order_number,
            'status' => $o->order_status,
            'statusLabel' => OrderStatus::label($o->order_status),
            'statusClass' => OrderStatus::badgeClass($o->order_status),
            'placedAt' => $o->placed_at ?? $o->created_at,
            'paymentMethod' => PaymentMethods::label($o->payment_method),
            'paymentStatus' => OrderStatus::paymentLabel($o->payment_status, $o->payment_method, $o->order_status),
            'timeline' => OrderStatus::timeline($o->order_status, $history, $o->placed_at ?? $o->created_at, $o->cancelled_at),
            'canCancel' => in_array($o->order_status, self::cancellableStatuses(), true),
            'returnRequest' => CustomerReturns::forOrder($o->id),
            'canRequestReturn' => CustomerReturns::enabled() && CustomerReturns::ineligibleReason($o) === null,
            'items' => $items->map(fn ($i) => [
                'name' => $i->product_name, 'sku' => $i->sku, 'image' => $i->image_url, 'color' => $i->variant_color, 'size' => $i->variant_size,
                'listPrice' => (float) $i->unit_price, 'price' => (float) $i->unit_selling_price, 'quantity' => (int) $i->quantity,
                'lineTotal' => (float) $i->line_total, 'url' => isset($slugs[$i->product_id]) ? '/products/'.rawurlencode($slugs[$i->product_id]) : null,
            ])->all(),
            'totals' => [
                'subtotal' => (float) $o->subtotal, 'discount' => (float) $o->discount_total, 'shipping' => (float) $o->shipping_total,
                'tax' => (float) $o->tax_total, 'total' => (float) $o->grand_total, 'coupon' => $o->coupon_code,
            ],
            'address' => [
                'name' => $o->shipping_recipient_name, 'phone' => $o->shipping_phone, 'street' => $o->shipping_street_address,
                'suburb' => $o->shipping_suburb, 'state' => $o->shipping_state, 'postcode' => $o->shipping_postcode,
            ],
            'note' => $o->customer_note,
        ];
    }

    /**
     * Customer cancellation. Atomic compare-and-set on the current status, so a concurrent admin change (e.g. to
     * "confirmed") wins and the customer gets a clear refusal instead of overwriting it. Never touches stock or payments:
     * a COD order has nothing to refund, and the admin records any refund for other methods.
     *
     * @return bool false when the order exists but is no longer cancellable
     */
    public static function cancel(int $customerId, string $orderNumber): ?bool
    {
        return DB::transaction(function () use ($customerId, $orderNumber) {
            $o = DB::table('orders')->where('order_number', $orderNumber)->where('customer_id', $customerId)->lockForUpdate()->first(['id', 'order_status']);
            if (! $o) {
                return null;
            }
            if (! in_array($o->order_status, self::cancellableStatuses(), true) || ! OrderStatus::canTransition($o->order_status, 'cancelled')) {
                return false;
            }
            $n = DB::table('orders')->where('id', $o->id)->where('order_status', $o->order_status)
                ->update(['order_status' => 'cancelled', 'cancelled_at' => now(), 'updated_at' => now()]);
            if ($n !== 1) {
                return false;
            }
            DB::table('order_timeline')->insert([
                'order_id' => $o->id, 'old_status' => $o->order_status, 'new_status' => 'cancelled', 'note' => 'Cancelled by customer', 'created_at' => now(),
            ]);

            return true;
        });
    }
}
