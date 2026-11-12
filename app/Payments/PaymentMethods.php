<?php

namespace App\Payments;

/** Payment methods the storefront knows how to process. Enabled ones are listed in config storefront.payment_methods. */
final class PaymentMethods
{
    /** @var array<string, class-string<PaymentMethod>> */
    private const KNOWN = [
        'cod' => CashOnDelivery::class,
    ];

    /** Enabled method codes (configured AND implemented). */
    public static function enabled(): array
    {
        return array_values(array_filter((array) config('storefront.payment_methods'), fn ($c) => isset(self::KNOWN[$c])));
    }

    public static function get(string $code): PaymentMethod
    {
        $class = self::KNOWN[$code] ?? null;
        abort_unless($class && in_array($code, self::enabled(), true), 422, 'This payment method is not available.');

        return new $class;
    }

    /** Label for a stored code (also for historic orders whose method is no longer enabled). */
    public static function label(?string $code): string
    {
        $class = self::KNOWN[$code] ?? null;

        return $class ? (new $class)->label() : ucwords(str_replace('_', ' ', (string) $code));
    }
}
