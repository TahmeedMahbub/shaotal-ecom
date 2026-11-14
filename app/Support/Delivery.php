<?php

namespace App\Support;

/** Delivery wording from the admin-managed config_data key delivery_copy (StoreConfig), with the real threshold and fees filled in (:free_over, :flat, :adelaide). */
class Delivery
{
    public static function money(float $amount): string
    {
        return config('storefront.currency_symbol').rtrim(rtrim(number_format($amount, 2), '0'), '.');
    }

    /** The configured line with :free_over / :flat replaced, or null when the line is switched off. */
    public static function text(string $key): ?string
    {
        $copy = StoreConfig::get('delivery_copy');
        if (! array_key_exists($key, $copy)) {
            throw new ConfigDataException("Unknown delivery_copy field \"{$key}\".");
        }
        $line = $copy[$key];
        if ($line === null || $line === '') {
            return null;
        }

        return strtr((string) $line, [
            ':free_over' => self::money((float) Shipping::freeThreshold()),
            ':flat' => self::money(Shipping::fee(Shipping::AUSTRALIA)),
            ':adelaide' => self::money(Shipping::fee(Shipping::ADELAIDE)),
        ]);
    }

    /** Free delivery applies at or above the threshold (owner decision, Part 6). The rule lives in Shipping. */
    public static function freeShipping(float $subtotal): bool
    {
        return Shipping::freeDelivery($subtotal);
    }
}
