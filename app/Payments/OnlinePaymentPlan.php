<?php

namespace App\Payments;

use Illuminate\Support\Facades\DB;

/**
 * Online payment = pre-booking. THE single place that knows the online-payment discount and deposit rules; Blade, JS,
 * controllers and the Stripe classes only read the numbers it returns (JS gets them from the /checkout/quote response).
 *
 * Rules (all arithmetic in integer cents, half-up rounding, so the parts always add up exactly):
 *   eligible merchandise = subtotal - coupon discount            (never below 0)
 *   online discount      = eligible x discount%                  (shipping is never discounted)
 *   order total          = eligible - online discount + shipping + tax
 *   deposit (pay now)    = order total x deposit%
 *   balance (pay later)  = order total - deposit                 (so deposit + balance == total, to the cent)
 *
 * The rates come from settings() only. They are static config today (config/storefront.php "online_payment"); to manage
 * them from the admin later, change settings() alone (e.g. read site_settings): nothing else needs to change. Each
 * order stores a snapshot of the rates it was priced with (plan()['…Percent']) in payments.response_data, so changing
 * the rates never alters existing orders.
 */
final class OnlinePaymentPlan
{
    /** @return array{discountPercent: float, depositPercent: float, balancePercent: float} */
    public static function settings(): array
    {
        $cfg = (array) config('storefront.online_payment', []);
        $discount = max(0.0, min(100.0, (float) ($cfg['discount_percent'] ?? 0)));
        $deposit = max(0.0, min(100.0, (float) ($cfg['deposit_percent'] ?? 100)));

        return ['discountPercent' => $discount, 'depositPercent' => $deposit, 'balancePercent' => round(100 - $deposit, 4)];
    }

    /**
     * @param  float  $merchandise  subtotal after any coupon discount (the eligible amount)
     * @param  float  $shipping  delivery charge, never discounted
     * @param  ?array  $rates  snapshot of the rates to use (default: current settings())
     * @return array{discountPercent: float, depositPercent: float, balancePercent: float, merchandise: float, discount: float, shipping: float, tax: float, total: float, deposit: float, balance: float}
     */
    public static function calculate(float $merchandise, float $shipping, float $tax = 0.0, ?array $rates = null): array
    {
        $rates ??= self::settings();
        $eligible = max(0, self::cents($merchandise));
        $discount = self::percentOf($eligible, $rates['discountPercent']);
        $total = $eligible - $discount + self::cents($shipping) + self::cents($tax);
        $deposit = self::percentOf($total, $rates['depositPercent']);

        return [
            'discountPercent' => (float) $rates['discountPercent'], 'depositPercent' => (float) $rates['depositPercent'],
            'balancePercent' => round(100 - (float) $rates['depositPercent'], 4),
            'merchandise' => $eligible / 100, 'discount' => $discount / 100, 'shipping' => self::cents($shipping) / 100,
            'tax' => self::cents($tax) / 100, 'total' => $total / 100, 'deposit' => $deposit / 100, 'balance' => ($total - $deposit) / 100,
        ];
    }

    /** The plan an order was placed with (stored on its payment rows), or null for COD / orders paid in full at the time. */
    public static function forOrder(int $orderId): ?array
    {
        $json = DB::table('payments')->where('order_id', $orderId)->where('provider', 'stripe')->orderBy('id')->value('response_data');
        $plan = (json_decode((string) $json, true) ?: [])['plan'] ?? null;

        return is_array($plan) && isset($plan['deposit'], $plan['total']) ? $plan : null;
    }

    public static function cents(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /** $cents x $percent / 100, half-up, exact (percent held as basis points). */
    private static function percentOf(int $cents, float $percent): int
    {
        $bp = (int) round($percent * 100);

        return intdiv($cents * $bp + 5000, 10000);
    }
}
