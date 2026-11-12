<?php

namespace App\Support;

/** Delivery wording from config('storefront.delivery_copy'), with the real threshold and fee filled in. */
class Delivery
{
    public static function money(float $amount): string
    {
        return config('storefront.currency_symbol').rtrim(rtrim(number_format($amount, 2), '0'), '.');
    }

    /** The configured line with :free_over / :flat replaced, or null when the line is switched off. */
    public static function text(string $key): ?string
    {
        $line = config('storefront.delivery_copy.'.$key);
        if ($line === null || $line === '') {
            return null;
        }

        return strtr((string) $line, [
            ':free_over' => self::money((float) config('storefront.free_shipping_over')),
            ':flat' => self::money((float) config('storefront.shipping_flat')),
        ]);
    }

    /** Free shipping applies at or above the threshold (owner decision, Part 6). */
    public static function freeShipping(float $subtotal): bool
    {
        return round($subtotal, 2) >= round((float) config('storefront.free_shipping_over'), 2);
    }
}
