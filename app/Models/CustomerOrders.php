<?php

namespace App\Models;

use App\Payments\OnlinePaymentPlan;
use App\Payments\PaymentMethods;
use App\Support\DisplayTime;
use App\Support\GiftWrap;
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
        $paidBy = CardPayments::paidTotals($rows->where('payment_method', 'card')->pluck('id')->all());
        $hasMore = $rows->count() > self::PER_PAGE;
        $rows = $rows->take(self::PER_PAGE);

        $ids = $rows->pluck('id')->all() ?: [0];
        $items = DB::table('order_items')->whereIn('order_id', $ids)->orderBy('id')
            ->get(['order_id', 'product_name', 'image_url', 'variant_color', 'variant_size', 'unit_selling_price', 'quantity', 'sku'])
            ->reject(fn ($i) => $i->sku === GiftWrap::SKU)   // the wrapping fee line is not a product
            ->groupBy('order_id');

        // Finer delivery stages live in order_timeline (see OrderStatus); only orders that can have them are looked up.
        $subIds = $rows->whereIn('order_status', ['processing', 'shipped'])->pluck('id')->all();
        $subs = $subIds ? DB::table('order_timeline')->whereIn('order_id', $subIds)->whereIn('new_status', array_keys(OrderStatus::SUB_STAGES))
            ->get(['order_id', 'new_status'])->groupBy('order_id') : collect();
        $stageOf = fn ($o) => OrderStatus::stage($o->order_status, ($subs[$o->id] ?? collect())->pluck('new_status'));

        return [
            'orders' => $rows->map(fn ($o) => [
                'id' => $o->order_number,
                'url' => self::url($o->order_number),
                'date' => DisplayTime::date($o->placed_at ?? $o->created_at),   // calendar date in Australia/Sydney
                'total' => (float) $o->grand_total,
                'status' => OrderStatus::label($stageOf($o)),
                'statusClass' => OrderStatus::badgeClass($stageOf($o)),
                'paymentMethod' => PaymentMethods::label($o->payment_method),
                'paymentStatus' => OrderStatus::paymentLabel($o->payment_status, $o->payment_method, $o->order_status, $paidBy[$o->id] ?? 0.0, (float) $o->grand_total),
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
            'shipping_suburb', 'shipping_state', 'shipping_postcode', 'customer_note', 'gift_wrap_requested', 'gift_wrap_items_count',
            'gift_wrap_fee', 'gift_message', 'placed_at', 'cancelled_at', 'created_at',
        ]);
        if (! $o) {
            return null;
        }

        $items = DB::table('order_items')->where('order_id', $o->id)->orderBy('id')
            ->get(['product_id', 'product_name', 'sku', 'image_url', 'variant_color', 'variant_size', 'unit_price', 'unit_selling_price', 'quantity', 'gift_wrap_qty', 'line_discount', 'line_total']);
        // Gift wrapping lives in the dedicated columns (orders.gift_*, order_items.gift_wrap_qty). LEGACY orders (placed before
        // those columns) have the fee as an order line (sku GiftWrap::SKU) inside subtotal and the wrapped items + message in
        // the note; they are read here so history keeps displaying and its subtotal is shown as goods only.
        $giftLine = $items->firstWhere('sku', GiftWrap::SKU);
        $items = $items->reject(fn ($i) => $i->sku === GiftWrap::SKU)->values();
        $noteParts = GiftWrap::split($o->customer_note);
        if ($o->gift_wrap_requested) {
            $gift = [
                'summary' => '', 'fee' => (float) $o->gift_wrap_fee, 'message' => (string) $o->gift_message,
                'items' => $items->filter(fn ($i) => (int) $i->gift_wrap_qty > 0)->map(fn ($i) => (int) $i->gift_wrap_qty.' x '.$i->product_name
                    .($i->variant_size || $i->variant_color ? ' ('.implode(' / ', array_filter([$i->variant_size, $i->variant_color])).')' : ''))->values()->all(),
            ];
            $storedGiftLine = 0.0;
        } elseif ($giftLine) {
            $gift = ($noteParts['gift'] ?? ['summary' => '', 'items' => [], 'message' => '']) + ['fee' => (float) $giftLine->line_total];
            $storedGiftLine = (float) $giftLine->line_total;
        } else {
            $gift = null;
            $storedGiftLine = 0.0;
        }
        // Links only for products that are still listed (status/visibility checked live; a delisted product shows as text).
        $slugs = DB::table('products')->whereIn('id', $items->pluck('product_id')->filter()->all() ?: [0])
            ->where('status', 'active')->where('visibility', 'visible')->pluck('slug', 'id');
        $history = DB::table('order_timeline')->where('order_id', $o->id)->orderBy('created_at')->orderBy('id')
            ->get(['old_status', 'new_status', 'created_at']);

        $stage = OrderStatus::stage($o->order_status, $history->pluck('new_status'));
        $card = $o->payment_method === 'card';
        $paid = $card ? CardPayments::paidTotal($o->id) : 0.0;
        $plan = $card ? OnlinePaymentPlan::forOrder($o->id) : null;
        $balance = round(max(0, (float) $o->grand_total - $paid), 2);

        return [
            'number' => $o->order_number,
            'status' => $o->order_status,
            'stage' => $stage,
            'statusLabel' => OrderStatus::label($stage),
            'statusDescription' => OrderStatus::description($stage),
            'statusClass' => OrderStatus::badgeClass($stage),
            'placedAt' => $o->placed_at ?? $o->created_at,
            'paymentMethod' => PaymentMethods::label($o->payment_method),
            'paymentStatus' => OrderStatus::paymentLabel($o->payment_status, $o->payment_method, $o->order_status, $paid, (float) $o->grand_total),
            // Pre-booking breakdown (online payment): what was paid, what is still owed, and the discount. null for COD.
            'prebooking' => $plan ? [
                'discount' => (float) $plan['discount'], 'discountPercent' => (float) $plan['discountPercent'], 'depositPercent' => (float) $plan['depositPercent'],
                'balancePercent' => (float) $plan['balancePercent'], 'deposit' => (float) $plan['deposit'], 'paid' => $paid,
                'balance' => $o->order_status === 'cancelled' ? 0.0 : $balance, 'depositPaid' => $paid > 0,
            ] : null,
            'timeline' => OrderStatus::timeline($o->order_status, $history, $o->placed_at ?? $o->created_at, $o->cancelled_at),
            // A paid order needs staff (refund), so it is never cancelled online (docs/payments-architecture.md P1).
            'canCancel' => in_array($o->order_status, self::cancellableStatuses(), true) && $o->payment_status !== 'paid' && $paid <= 0,
            'canPay' => CardPayments::canPay($o->payment_method, $o->payment_status, $o->order_status, $paid),
            'payments' => $card ? CardPayments::history($o->id, (float) $o->grand_total) : [],
            'returnRequest' => CustomerReturns::forOrder($o->id),
            'canRequestReturn' => CustomerReturns::enabled() && CustomerReturns::ineligibleReason($o) === null,
            'items' => $items->map(fn ($i) => [
                'name' => $i->product_name, 'sku' => $i->sku, 'image' => $i->image_url, 'color' => $i->variant_color, 'size' => $i->variant_size,
                'listPrice' => (float) $i->unit_price, 'price' => (float) $i->unit_selling_price, 'quantity' => (int) $i->quantity,
                'lineTotal' => (float) $i->line_total, 'url' => isset($slugs[$i->product_id]) ? '/products/'.rawurlencode($slugs[$i->product_id]) : null,
            ])->all(),
            'totals' => [
                // "discount" is the coupon part only; the online-payment discount is itemised separately (prebooking.discount).
                // Goods only, with the wrapping fee itemised ('giftFee'). Current orders store subtotal as goods only; legacy ones
                // had the fee inside it, so it is taken out again ($storedGiftLine).
                'subtotal' => round((float) $o->subtotal - $storedGiftLine, 2), 'giftFee' => $gift['fee'] ?? 0.0,
                'discount' => round((float) $o->discount_total - ($plan['discount'] ?? 0), 2), 'shipping' => (float) $o->shipping_total,
                'tax' => (float) $o->tax_total, 'total' => (float) $o->grand_total, 'coupon' => $o->coupon_code,
            ],
            'address' => [
                'name' => $o->shipping_recipient_name, 'phone' => $o->shipping_phone, 'street' => $o->shipping_street_address,
                'suburb' => $o->shipping_suburb, 'state' => $o->shipping_state, 'postcode' => $o->shipping_postcode,
            ],
            'note' => $noteParts['note'],
            'gift' => $gift,
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
            $o = DB::table('orders')->where('order_number', $orderNumber)->where('customer_id', $customerId)->lockForUpdate()->first(['id', 'order_status', 'payment_status']);
            if (! $o) {
                return null;
            }
            if (! in_array($o->order_status, self::cancellableStatuses(), true) || ! OrderStatus::canTransition($o->order_status, 'cancelled')
                || $o->payment_status === 'paid' || CardPayments::paidTotal($o->id) > 0) {
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
