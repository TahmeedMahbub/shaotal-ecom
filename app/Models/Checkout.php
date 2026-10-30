<?php

namespace App\Models;

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
    public static function totals(array $lines, ?string $code, ?int $customerId): array
    {
        $subtotal = round(array_sum(array_map(fn ($l) => $l['price'] * $l['quantity'], $lines)), 2);
        $shipping = $subtotal > config('storefront.free_shipping_over') ? 0.0 : (float) config('storefront.shipping_flat');
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

        return [
            'subtotal' => $subtotal, 'shipping' => round($shipping, 2), 'discount' => $discount, 'tax' => $tax,
            'total' => round(max(0, $subtotal + $shipping + $tax - $discount), 2),
            'coupon' => $coupon, 'couponError' => $couponError,
        ];
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
     * Create the order, items, payment and timeline atomically.
     *
     * @return array{orderNumber: string, total: float}
     */
    public static function place(array $lines, array $totals, array $address, string $method, ?int $customerId, ?string $note): array
    {
        $customerEmail = $customerId ? DB::table('customers')->where('id', $customerId)->value('email') : null;

        return DB::transaction(function () use ($lines, $totals, $address, $method, $customerId, $note, $customerEmail) {
            $number = null;
            for ($i = 0; $i < 5 && ! $number; $i++) {
                $try = 'INV-'.random_int(100000, 999999);
                if (! DB::table('orders')->where('order_number', $try)->exists()) {
                    $number = $try;
                }
            }
            abort_unless($number, 500, 'Could not generate an order number. Please try again.');

            $orderId = DB::table('orders')->insertGetId([
                'order_number' => $number, 'customer_id' => $customerId, 'customer_email' => $customerEmail, 'customer_phone' => $address['phone'],
                'shipping_recipient_name' => $address['fullName'], 'shipping_phone' => $address['phone'], 'shipping_street_address' => $address['street'],
                'shipping_suburb' => $address['suburb'], 'shipping_state' => $address['state'], 'shipping_postcode' => $address['postcode'],
                'subtotal' => $totals['subtotal'], 'discount_total' => $totals['discount'], 'shipping_total' => $totals['shipping'],
                'tax_total' => $totals['tax'], 'grand_total' => $totals['total'], 'coupon_code' => $totals['coupon']['code'] ?? null,
                'payment_method' => $method, 'payment_status' => 'pending', 'order_status' => 'pending', 'fulfillment_type' => 'delivery',
                'customer_note' => $note, 'placed_at' => now(),
            ]);

            DB::table('order_items')->insert(array_map(fn ($l) => [
                'order_id' => $orderId, 'product_id' => $l['productId'], 'variant_id' => $l['variantId'], 'product_name' => $l['name'],
                'sku' => $l['sku'], 'image_url' => $l['image'], 'variant_color' => $l['color'], 'variant_size' => $l['size'],
                'unit_price' => $l['listPrice'], 'unit_selling_price' => $l['price'], 'quantity' => $l['quantity'],
                'line_discount' => 0, 'line_total' => round($l['price'] * $l['quantity'], 2),
            ], $lines));

            DB::table('order_timeline')->insert(['order_id' => $orderId, 'old_status' => null, 'new_status' => 'pending', 'note' => 'Order placed']);
            DB::table('payments')->insert([
                'order_id' => $orderId, 'payment_method' => $method, 'amount' => $totals['total'], 'status' => 'pending',
            ]);

            if ($customerId) {
                $cart = DB::table('carts')->where('customer_id', $customerId)->where('status', 'active')->pluck('id');
                DB::table('cart_items')->whereIn('cart_id', $cart)->delete();
                DB::table('carts')->whereIn('id', $cart)->update(['status' => 'converted']);
            }

            return ['orderNumber' => $number, 'total' => $totals['total']];
        });
    }
}
