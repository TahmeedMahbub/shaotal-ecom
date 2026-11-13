<?php

namespace App\Models;

use App\Payments\OnlinePaymentPlan;
use App\Payments\PaymentMethod;
use App\Payments\PaymentMethods;
use App\Support\Delivery;
use App\Support\GiftWrap;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Server-side pricing for the cart and order placement. The browser only sends product ids,
 * colour/size and quantity; every price, stock check, coupon and total is recomputed here.
 */
class Checkout
{
    /**
     * @param  array  $items  [{productId,color,size,quantity}]
     * @return array{lines: array, errors: array}
     */
    public static function priceLines(array $items): array
    {
        $ids = array_values(array_unique(array_map(fn ($i) => (int) ($i['productId'] ?? 0), $items)));
        $products = [];
        foreach (Catalog::products($ids) as $p) {
            $products[$p['id']] = $p;
        }
        $categoryIds = DB::table('products')->whereIn('id', $ids ?: [0])->pluck('category_id', 'id');

        $lines = [];
        $errors = [];
        foreach ($items as $it) {
            $p = $products[(int) ($it['productId'] ?? 0)] ?? null;
            $qty = max(1, min(99, (int) ($it['quantity'] ?? 1)));
            if (! $p) {
                $errors[] = 'An item in your cart is no longer available.';

                continue;
            }
            [$variantId, $ok] = Storefront::resolveVariant($p, $it['color'] ?? null, $it['size'] ?? null);
            if (! $ok) {
                $errors[] = "{$p['name']}: the selected option is unavailable.";

                continue;
            }

            $variant = $variantId ? collect($p['variants'])->firstWhere('id', $variantId) : null;
            // Stock is not enforced for now (orderable at 0 stock); only admin-flagged unavailable products are blocked.
            $preorder = $p['availability'] === 'preorder';
            if (! $preorder && $p['soldOut']) {
                $errors[] = "{$p['name']} is out of stock.";

                continue;
            }

            $selling = $variant ? $variant['price'] : $p['price'];
            $list = $variant ? $variant['list'] : ($p['originalPrice'] ?? $p['price']);
            $colorName = ($variant && $variant['color'] !== null) ? ($p['colorNames'][$variant['color']] ?? $variant['color']) : null;

            $key = $p['id'].':'.($variantId ?? 0);
            if (isset($lines[$key])) {
                $lines[$key]['quantity'] = min(99, $lines[$key]['quantity'] + $qty);
            } else {
                $lines[$key] = [
                    'productId' => $p['id'], 'variantId' => $variantId, 'categoryId' => (int) ($categoryIds[$p['id']] ?? 0),
                    'name' => $p['name'], 'sku' => $variant ? null : $p['sku'], 'image' => $p['image'],
                    'color' => $colorName, 'size' => $variant ? $variant['size'] : null,
                    'colorKey' => $variant['color'] ?? null,
                    'listPrice' => (float) $list, 'price' => (float) $selling, 'quantity' => $qty,
                ];
            }
        }

        if ($variantSkus = array_filter(array_column($lines, 'variantId'))) {
            $skus = DB::table('product_variants')->whereIn('id', $variantSkus)->pluck('sku', 'id');
            foreach ($lines as &$l) {
                if ($l['variantId']) {
                    $l['sku'] = $skus[$l['variantId']] ?? null;
                }
            }
            unset($l);
        }

        return ['lines' => array_values($lines), 'errors' => array_values(array_unique($errors))];
    }

    /**
     * Totals for priced lines plus an optional coupon. Returns the coupon row on success.
     *
     * @return array{subtotal: float, shipping: float, discount: float, tax: float, total: float, coupon: ?array, couponError: ?string}
     */
    public static function totals(array $lines, ?string $code, ?int $customerId, ?array $gift = null): array
    {
        // Gift wrapping is a service charge on top of the goods: it never counts toward the free-shipping threshold,
        // coupon minimums or coupon eligibility (those use the goods subtotal), but it is part of the order total and
        // of the online-payment base (discount and deposit), so every figure below stays consistent.
        $giftError = GiftWrap::problem($lines, $gift);
        $gift = GiftWrap::resolve($lines, $gift);
        $giftFee = $gift['fee'] ?? 0.0;
        $subtotal = round(array_sum(array_map(fn ($l) => $l['price'] * $l['quantity'], $lines)), 2);
        // Free at or above the threshold (exactly the threshold ships free), flat fee below it.
        $shipping = Delivery::freeShipping($subtotal) ? 0.0 : (float) config('storefront.shipping_flat');
        $discount = 0.0;
        $coupon = null;
        $couponError = null;

        if ($code !== null && trim($code) !== '') {
            [$coupon, $discount, $freeShip, $couponError] = self::applyCoupon(trim($code), $lines, $subtotal, $shipping, $customerId);
            if ($coupon && $freeShip) {
                $shipping = 0.0;
            }
            if ($couponError) {
                $coupon = null;
                $discount = 0.0;
            }
        }
        $discount = round(min($discount, $subtotal), 2);
        $tax = 0.0;

        $shipping = round($shipping, 2);

        return [
            'subtotal' => $subtotal, 'shipping' => $shipping, 'discount' => $discount, 'tax' => $tax,
            'giftFee' => $giftFee, 'gift' => $gift, 'giftError' => $giftError,
            'total' => round(max(0, $subtotal + $giftFee + $shipping + $tax - $discount), 2),
            'coupon' => $coupon, 'couponError' => $couponError,
            // What the order would be if paid online (pre-booking). "total" above stays the cash-on-delivery figure.
            'online' => OnlinePaymentPlan::calculate($subtotal + $giftFee - $discount, $shipping, $tax),
        ];
    }

    /**
     * The totals an order is placed with for this method. COD: unchanged (no online discount, full amount due on
     * delivery). Card: online discount applied, total = discounted total, and only the deposit is charged now.
     */
    public static function forMethod(array $totals, string $method): array
    {
        if ($method !== 'card') {
            return $totals + ['onlineDiscount' => 0.0, 'plan' => null, 'chargeNow' => 0.0];
        }
        $plan = $totals['online'];

        return array_merge($totals, ['onlineDiscount' => $plan['discount'], 'total' => $plan['total'], 'plan' => $plan, 'chargeNow' => $plan['deposit']]);
    }

    private static function applyCoupon(string $code, array $lines, float $subtotal, float $shipping, ?int $customerId): array
    {
        $invalid = [null, 0.0, false, 'Invalid or expired coupon.'];
        $c = DB::table('coupons')->where('code', $code)->first();
        $now = now();
        if (! $c || $c->status !== 'active' || ($c->starts_at && $c->starts_at > $now) || ($c->expires_at && $c->expires_at < $now)) {
            return $invalid;
        }
        if ($c->minimum_order_amount !== null && $subtotal < (float) $c->minimum_order_amount) {
            return [null, 0.0, false, 'Minimum order of '.config('storefront.currency_symbol').number_format((float) $c->minimum_order_amount, 2).' required for this coupon.'];
        }
        if ($c->usage_limit !== null) {
            $used = DB::table('orders')->where('coupon_code', $c->code)->where('order_status', '!=', 'cancelled')->count();
            if ($used >= $c->usage_limit) {
                return $invalid;
            }
        }
        if ($c->usage_limit_per_customer !== null) {
            if (! $customerId) {
                return [null, 0.0, false, 'Please sign in to use this coupon.'];
            }
            $used = DB::table('orders')->where('coupon_code', $c->code)->where('customer_id', $customerId)->where('order_status', '!=', 'cancelled')->count();
            if ($used >= $c->usage_limit_per_customer) {
                return $invalid;
            }
        }

        // Restrict to specific products/categories when the coupon lists any.
        $productIds = DB::table('coupon_products')->where('coupon_id', $c->id)->pluck('product_id')->all();
        $categoryIds = DB::table('coupon_categories')->where('coupon_id', $c->id)->pluck('category_id')->all();
        $eligible = $subtotal;
        if ($productIds || $categoryIds) {
            $eligible = round(array_sum(array_map(
                fn ($l) => (in_array($l['productId'], $productIds) || in_array($l['categoryId'], $categoryIds)) ? $l['price'] * $l['quantity'] : 0,
                $lines
            )), 2);
            if ($eligible <= 0) {
                return [null, 0.0, false, 'This coupon does not apply to the items in your cart.'];
            }
        }

        $value = (float) $c->discount_value;
        $discount = match ($c->discount_type) {
            'percentage' => $eligible * $value / 100,
            'fixed_amount' => min($value, $eligible),
            default => 0.0,
        };
        if ($c->maximum_discount_amount !== null) {
            $discount = min($discount, (float) $c->maximum_discount_amount);
        }
        $label = match ($c->discount_type) {
            'percentage' => rtrim(rtrim(number_format($value, 2), '0'), '.').'%',
            'fixed_amount' => config('storefront.currency_symbol').number_format($value, 2),
            default => 'Free shipping',
        };

        return [['code' => $c->code, 'label' => $label], round($discount, 2), $c->discount_type === 'free_shipping', null];
    }

    /**
     * Re-check a coupon's limits while holding a row lock on it, inside the order transaction. The lock serialises every
     * checkout using this coupon until the order row is committed, so two customers cannot both take the last use
     * (the pre-check in totals() runs without a lock and only gives early feedback). Usage = non-cancelled orders with
     * the code, so a rolled-back (failed) order never uses it up, and a replayed duplicate request creates no order.
     * Only the one coupon row is locked and always first in the transaction, so this cannot deadlock with itself.
     */
    private static function claimCoupon(string $code, ?int $customerId): void
    {
        $c = DB::table('coupons')->where('code', $code)->lockForUpdate()
            ->first(['code', 'status', 'starts_at', 'expires_at', 'usage_limit', 'usage_limit_per_customer']);
        $now = now();
        abort_unless($c && $c->status === 'active' && (! $c->starts_at || $c->starts_at <= $now) && (! $c->expires_at || $c->expires_at >= $now),
            422, 'This coupon is no longer available. Please remove it and try again.');

        $used = fn () => DB::table('orders')->where('coupon_code', $c->code)->where('order_status', '!=', 'cancelled');
        if ($c->usage_limit !== null && $used()->count() >= (int) $c->usage_limit) {
            abort(422, 'This coupon has reached its usage limit. Please remove it and try again.');
        }
        if ($c->usage_limit_per_customer !== null) {
            abort_unless($customerId, 422, 'Please sign in to use this coupon.');
            if ($used()->where('customer_id', $customerId)->count() >= (int) $c->usage_limit_per_customer) {
                abort(422, 'You have already used this coupon the maximum number of times.');
            }
        }
    }

    /** The orders gift columns for a resolved gift selection (defaults for a non-gift order). */
    private static function giftOrderColumns(?array $gift): array
    {
        if (! $gift) {
            return ['gift_wrap_requested' => 0, 'gift_wrap_items_count' => 0, 'gift_wrap_fee' => 0, 'gift_message' => null];
        }

        return [
            'gift_wrap_requested' => 1, 'gift_wrap_items_count' => $gift['quantity'], 'gift_wrap_fee' => $gift['fee'],
            'gift_message' => $gift['message'] === '' ? null : $gift['message'],
        ];
    }

    /**
     * Create the order, items, payment and timeline atomically.
     *
     * @return array{orderNumber: string, total: float}
     */
    public static function place(array $lines, array $totals, array $address, PaymentMethod|string $method, ?int $customerId, ?string $note, ?string $customerEmail = null): array
    {
        $method = is_string($method) ? PaymentMethods::get($method) : $method;
        $customerEmail ??= $customerId ? DB::table('customers')->where('id', $customerId)->value('email') : null;

        return DB::transaction(function () use ($lines, $totals, $address, $method, $customerId, $note, $customerEmail) {
            if ($code = $totals['coupon']['code'] ?? null) {
                self::claimCoupon($code, $customerId);
            }

            // Order number: random INV-nnnnnn, made unique by the orders.order_number UNIQUE key itself instead of a separate
            // "does it exist" query per try. MySQL rolls back only the failed statement, so a (1 in ~900k) collision is
            // re-rolled inside the same transaction.
            $orderRow = [
                'customer_id' => $customerId, 'customer_email' => $customerEmail, 'customer_phone' => $address['phone'],
                'shipping_recipient_name' => $address['fullName'], 'shipping_phone' => $address['phone'], 'shipping_street_address' => $address['street'],
                'shipping_suburb' => $address['suburb'], 'shipping_state' => $address['state'], 'shipping_postcode' => $address['postcode'],
                // discount_total = coupon + online-payment discount, so subtotal - discount_total + shipping + tax = grand_total
                // always holds for the admin app; the online part is itemised from the stored plan.
                // subtotal is goods only; the gift fee has its own column and is part of grand_total exactly once:
                // subtotal - discount_total + shipping_total + tax_total + gift_wrap_fee = grand_total (the admin's contract).
                'subtotal' => $totals['subtotal'], 'discount_total' => round($totals['discount'] + ($totals['onlineDiscount'] ?? 0), 2), 'shipping_total' => $totals['shipping'],
                'tax_total' => $totals['tax'], 'grand_total' => $totals['total'], 'coupon_code' => $totals['coupon']['code'] ?? null,
                // Never "paid" at placement: COD is collected later, a card gateway confirms server-side (webhook).
                'payment_method' => $method->code(), 'payment_status' => 'pending', 'order_status' => 'pending', 'fulfillment_type' => 'delivery',
                'customer_note' => $note, 'placed_at' => now(),
            ] + self::giftOrderColumns($totals['gift'] ?? null);
            $orderId = null;
            $number = null;
            for ($i = 0; $i < 5 && ! $orderId; $i++) {
                $number = 'INV-'.random_int(100000, 999999);
                try {
                    $orderId = DB::table('orders')->insertGetId(['order_number' => $number] + $orderRow);
                } catch (UniqueConstraintViolationException) {
                    $orderId = null;
                }
            }
            abort_unless($orderId, 500, 'Could not generate an order number. Please try again.');

            // One insert for the goods; gift_wrap_qty records how many units of each line are wrapped (0 for a non-gift line).
            $giftQty = ($totals['gift']['lineQty'] ?? []);
            $itemRows = [];
            foreach (array_values($lines) as $i => $l) {
                $itemRows[] = [
                    'order_id' => $orderId, 'product_id' => $l['productId'], 'variant_id' => $l['variantId'], 'product_name' => $l['name'],
                    'sku' => $l['sku'], 'image_url' => $l['image'], 'variant_color' => $l['color'], 'variant_size' => $l['size'],
                    'unit_price' => $l['listPrice'], 'unit_selling_price' => $l['price'], 'quantity' => $l['quantity'],
                    'gift_wrap_qty' => $giftQty[$i] ?? 0,
                    'line_discount' => 0, 'line_total' => round($l['price'] * $l['quantity'], 2),
                ];
            }
            DB::table('order_items')->insert($itemRows);

            DB::table('order_timeline')->insert(['order_id' => $orderId, 'old_status' => null, 'new_status' => 'pending', 'note' => 'Order placed']);
            // COD: one row for the full amount (collected on delivery). Card: one row for the deposit charged now; the plan
            // (rates used, discount, deposit, balance) is snapshotted so later rate changes never alter this order.
            $plan = $totals['plan'] ?? null;
            DB::table('payments')->insert([
                'order_id' => $orderId, 'provider' => $method->provider(), 'payment_method' => $method->code(),
                'amount' => $plan ? $plan['deposit'] : $totals['total'], 'status' => $method->initialPaymentStatus(),
                'response_data' => $plan ? json_encode(['plan' => $plan, 'events' => []]) : null,
            ]);
            if ($plan) {
                DB::table('order_timeline')->insert(['order_id' => $orderId, 'old_status' => 'pending', 'new_status' => 'pending',
                    'note' => 'Online payment selected: '.config('storefront.currency_symbol').number_format($plan['deposit'], 2).' deposit due now, '
                        .config('storefront.currency_symbol').number_format($plan['balance'], 2).' balance due later']);
            }

            // A card order keeps the cart until Stripe confirms payment (CardPayments converts it then), so an abandoned
            // or declined payment does not lose the customer's bag.
            if ($customerId && $method->provider() === null) {
                // Two statements (items by sub-select, then the carts), not three: no separate lookup of the cart ids.
                $active = fn ($q) => $q->select('id')->from('carts')->where('customer_id', $customerId)->where('status', 'active');
                DB::table('cart_items')->whereIn('cart_id', $active)->delete();
                DB::table('carts')->where('customer_id', $customerId)->where('status', 'active')->update(['status' => 'converted']);
            }

            return ['orderNumber' => $number, 'total' => $totals['total'], 'payNow' => $plan['deposit'] ?? 0.0, 'balance' => $plan['balance'] ?? 0.0, 'prebooked' => (bool) $plan];
        });
    }
}
