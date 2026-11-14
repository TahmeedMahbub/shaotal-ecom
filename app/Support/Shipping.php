<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * THE shipping calculation. Checkout quote, order placement, order pages, emails and policy pages all use quote() /
 * forOrder(); nothing else computes a shipping fee. Rates: admin-managed config_data via StoreConfig (shipping_fee_adelaide,
 * shipping_fee_australia, free_shipping_threshold, pickup_enabled, pickup_location); Adelaide localities: config/adelaide_localities.php.
 *
 * Methods: "adelaide" (suburb + postcode in the Adelaide list, state SA), "australia" (any other Australian address),
 * "pickup" (free pickup at pickup_location). The browser only says delivery or pickup and which address; the method and fee
 * are always decided here.
 */
class Shipping
{
    public const ADELAIDE = 'adelaide';

    public const AUSTRALIA = 'australia';

    public const PICKUP = 'pickup';

    private static ?array $index = null;

    public static function pickupEnabled(): bool
    {
        return StoreConfig::get('pickup_enabled');
    }

    /** Case, spacing and punctuation-insensitive suburb key; "Saint"/"St" and "Mount"/"Mt" compare equal. */
    public static function normalise(?string $suburb): string
    {
        $s = strtoupper(trim((string) $suburb));
        $s = preg_replace("/[.'’`]/u", '', $s);
        $s = preg_replace('/[^A-Z0-9]+/', ' ', $s);
        $s = preg_replace(['/\bSAINT\b/', '/\bMOUNT\b/'], ['ST', 'MT'], $s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /** suburb key => [postcodes], built once from config/adelaide_localities.php. */
    private static function index(): array
    {
        if (self::$index === null) {
            self::$index = [];
            foreach ((array) config('adelaide_localities', []) as $postcode => $suburbs) {
                foreach ((array) $suburbs as $suburb) {
                    self::$index[self::normalise($suburb)][] = sprintf('%04d', (int) $postcode);
                }
            }
        }

        return self::$index;
    }

    /** Forget the cached locality index (tests that change the config). */
    public static function flush(): void
    {
        self::$index = null;
    }

    /** True only for an SA address whose suburb AND postcode are in the Adelaide list. Never "all of SA". */
    public static function isAdelaide(?string $suburb, ?string $postcode, ?string $state): bool
    {
        if (strtoupper(trim((string) $state)) !== 'SA' || ! preg_match('/^\d{4}$/', trim((string) $postcode))) {
            return false;
        }

        return in_array(trim((string) $postcode), self::index()[self::normalise($suburb)] ?? [], true);
    }

    public static function fee(string $method): float
    {
        return match ($method) {
            self::PICKUP => 0.0,
            self::ADELAIDE => round(StoreConfig::get('shipping_fee_adelaide'), 2),
            default => round(StoreConfig::get('shipping_fee_australia'), 2),
        };
    }

    public static function label(string $method): string
    {
        return match ($method) {
            self::PICKUP => self::pickup()['label'],
            self::ADELAIDE => 'Adelaide delivery',
            default => 'Delivery within Australia',
        };
    }

    /** Free-delivery threshold (goods subtotal), or null when free delivery is switched off. */
    public static function freeThreshold(): ?float
    {
        return StoreConfig::get('free_shipping_threshold');
    }

    /** The pickup point: label, suburb, state, postcode and details (null = not confirmed yet). */
    public static function pickup(): array
    {
        return StoreConfig::get('pickup_location');
    }

    /** Delivery is free at or above the threshold (null threshold = never). Pickup is always free. */
    public static function freeDelivery(float $subtotal): bool
    {
        $over = self::freeThreshold();

        return $over !== null && round($subtotal, 2) >= round($over, 2);
    }

    /**
     * The shipping for a checkout.
     *
     * @param  string  $fulfillment  "delivery" or "pickup"
     * @param  ?array  $address  ['suburb','state','postcode'] of the selected delivery address (null = none chosen yet)
     * @param  float  $subtotal  goods subtotal (before discounts; the free-delivery threshold uses it)
     * @return array{fulfillment: string, method: string, label: string, fee: float, baseFee: float, free: bool, addressNeeded: bool}
     */
    public static function quote(string $fulfillment, ?array $address, float $subtotal): array
    {
        if ($fulfillment === self::PICKUP && self::pickupEnabled()) {
            return ['fulfillment' => 'pickup', 'method' => self::PICKUP, 'label' => self::label(self::PICKUP), 'fee' => 0.0, 'baseFee' => 0.0, 'free' => true, 'addressNeeded' => false];
        }
        $method = $address && self::isAdelaide($address['suburb'] ?? null, $address['postcode'] ?? null, $address['state'] ?? null) ? self::ADELAIDE : self::AUSTRALIA;
        $base = self::fee($method);
        $free = self::freeDelivery($subtotal);

        return [
            'fulfillment' => 'delivery', 'method' => $method, 'label' => self::label($method),
            'fee' => $free ? 0.0 : $base, 'baseFee' => $base, 'free' => $free, 'addressNeeded' => ! $address,
        ];
    }

    /**
     * How a placed order was shipped, from its stored snapshot (payments.response_data → shipping, written at placement).
     * Orders placed before shipping zones existed have no snapshot: pickup orders are labelled from fulfillment_type,
     * delivery ones simply as "Delivery".
     *
     * @return array{fulfillment: string, method: ?string, label: string, fee: float}
     */
    public static function forOrder(int $orderId, ?string $fulfillmentType, float $shippingTotal): array
    {
        $json = DB::table('payments')->where('order_id', $orderId)->orderBy('id')->value('response_data');
        $snap = (json_decode((string) $json, true) ?: [])['shipping'] ?? null;
        if (is_array($snap) && isset($snap['method'], $snap['label'])) {
            return ['fulfillment' => $snap['fulfillment'] ?? 'delivery', 'method' => $snap['method'], 'label' => (string) $snap['label'], 'fee' => $shippingTotal];
        }
        $pickup = $fulfillmentType === 'pickup';

        return ['fulfillment' => $pickup ? 'pickup' : 'delivery', 'method' => $pickup ? self::PICKUP : null, 'label' => $pickup ? self::label(self::PICKUP) : 'Delivery', 'fee' => $shippingTotal];
    }

    /** Order columns for a pickup order: the pickup point stands in for the delivery address (the columns are NOT NULL). */
    public static function pickupAddress(string $name, string $phone): array
    {
        $p = self::pickup();

        return ['fullName' => $name, 'phone' => $phone, 'street' => $p['label'], 'suburb' => $p['suburb'], 'state' => $p['state'], 'postcode' => $p['postcode']];
    }
}
