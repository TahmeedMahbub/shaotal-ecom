<?php

namespace App\Support;

/**
 * Optional gift wrapping. THE single place that knows the price rule and how a gift selection is validated, stored
 * and read back (Blade/JS only display the numbers the server returns).
 *
 * Fee (config_data gift_wrap_base_fee / gift_wrap_base_items / gift_wrap_extra_fee), in cents so it is exact: the first
 * base_items wrapped items cost base_fee in total, each further wrapped item adds extra_fee. It depends only on the number of items selected to wrap, never on the cart size.
 *
 * Storage (the admin's dedicated columns; no extra order line, nothing in customer_note):
 *   - orders.gift_wrap_requested / gift_wrap_items_count / gift_wrap_fee / gift_message;
 *   - order_items.gift_wrap_qty = wrapped units of that line (0..quantity), so SUM = orders.gift_wrap_items_count;
 *   - orders.subtotal stays goods only; orders.grand_total = subtotal - discount_total + shipping_total + tax_total + gift_wrap_fee.
 * LEGACY (read only): orders placed before those columns carry the fee as an order_items row (sku SKU) inside subtotal and the
 * wrapped items + message as a block in customer_note (MARKER); split() still reads them so history keeps displaying.
 */
final class GiftWrap
{
    public const SKU = 'GIFT-WRAP';

    public const MARKER = '--- GIFT WRAPPING ---';

    public const MESSAGE_PREFIX = 'Gift message:';

    public const MESSAGE_MAX = 300;

    /** Identifies a priced cart line / a gift selection entry (same product, colour and size). */
    public static function lineKey(int $productId, ?string $color, ?string $size): string
    {
        return $productId.'|'.($color ?? '').'|'.($size ?? '');
    }

    /** @return array{baseFee: float, baseItems: int, extraFee: float} */
    public static function rules(): array
    {
        return [
            'baseFee' => StoreConfig::get('gift_wrap_base_fee'), 'baseItems' => StoreConfig::get('gift_wrap_base_items'),
            'extraFee' => StoreConfig::get('gift_wrap_extra_fee'),
        ];
    }

    /** Wrapping fee for $quantity wrapped items (0 items = no wrapping = 0). */
    public static function fee(int $quantity): float
    {
        if ($quantity <= 0) {
            return 0.0;
        }
        $r = self::rules();
        $cents = (int) round($r['baseFee'] * 100) + max(0, $quantity - $r['baseItems']) * (int) round($r['extraFee'] * 100);

        return $cents / 100;
    }

    /**
     * Validate a gift selection against the priced cart lines. Quantities are clamped to what the cart holds, repeated
     * entries are merged, unknown lines are ignored. Returns null when gift wrapping is off or nothing valid is selected.
     *
     * @param  array  $lines  Checkout::priceLines()['lines']
     * @return null|array{items: array, quantity: int, fee: float, message: string, lineQty: array<int,int>} lineQty: wrapped units per $lines index
     */
    public static function resolve(array $lines, ?array $gift): ?array
    {
        if (! $gift || empty($gift['enabled'])) {
            return null;
        }
        $wanted = [];
        foreach ((array) ($gift['items'] ?? []) as $it) {
            $key = (int) ($it['productId'] ?? 0).'|'.($it['color'] ?? '').'|'.($it['size'] ?? '');
            $wanted[$key] = ($wanted[$key] ?? 0) + max(0, (int) ($it['quantity'] ?? 0));
        }

        $items = [];
        $lineQty = [];
        $total = 0;
        foreach ($lines as $i => $l) {
            $qty = min((int) $l['quantity'], $wanted[$l['productId'].'|'.($l['colorKey'] ?? '').'|'.($l['size'] ?? '')] ?? 0);
            if ($qty > 0) {
                $lineQty[$i] = $qty;
                $items[] = ['productId' => $l['productId'], 'name' => $l['name'], 'color' => $l['color'], 'size' => $l['size'], 'quantity' => $qty];
                $total += $qty;
            }
        }
        if (! $total) {
            return null;
        }
        $message = trim(str_replace(["\r\n", "\r"], "\n", (string) ($gift['message'] ?? '')));
        // The marker is how a stored note is split again; a customer cannot forge it from either text box.
        $message = trim(str_replace(self::MARKER, '', mb_substr($message, 0, self::MESSAGE_MAX)));

        return ['items' => $items, 'quantity' => $total, 'fee' => self::fee($total), 'message' => $message, 'lineQty' => $lineQty];
    }

    /**
     * Why a gift selection cannot be accepted as sent, or null when every selected line and quantity really is in the
     * cart. resolve() quietly clamps (so a quote can still show something); placing an order must not change what the
     * customer agreed to, so it refuses a selection that does not match the cart.
     *
     * @param  array  $lines  Checkout::priceLines()['lines']
     */
    public static function problem(array $lines, ?array $gift): ?string
    {
        if (! $gift || empty($gift['enabled'])) {
            return null;
        }
        $have = [];
        foreach ($lines as $l) {
            $have[$l['productId'].'|'.($l['colorKey'] ?? '').'|'.($l['size'] ?? '')] = (int) $l['quantity'];
        }
        $want = [];
        foreach ((array) ($gift['items'] ?? []) as $it) {
            $key = (int) ($it['productId'] ?? 0).'|'.($it['color'] ?? '').'|'.($it['size'] ?? '');
            $want[$key] = ($want[$key] ?? 0) + max(0, (int) ($it['quantity'] ?? 0));
        }
        foreach ($want as $key => $qty) {
            if ($qty > 0 && (! isset($have[$key]) || $qty > $have[$key])) {
                return 'Your gift wrapping selection no longer matches your cart. Please review it in your cart.';
            }
        }

        return null;
    }

    /**
     * LEGACY: split a stored customer_note into the customer's own note and the gift block.
     *
     * @return array{note: ?string, gift: ?array{summary: string, items: array<string>, message: string}}
     */
    public static function split(?string $stored): array
    {
        $stored = (string) $stored;
        $pos = strpos($stored, self::MARKER);
        if ($pos === false) {
            return ['note' => trim($stored) === '' ? null : $stored, 'gift' => null];
        }
        $note = trim(substr($stored, 0, $pos));
        $block = explode("\n", trim(substr($stored, $pos + strlen(self::MARKER))));
        $summary = '';
        $items = [];
        $message = '';
        foreach ($block as $n => $line) {
            if (str_starts_with($line, self::MESSAGE_PREFIX)) {
                // The message is the rest of the block (it may span several lines).
                $message = trim(implode("\n", array_merge([substr($line, strlen(self::MESSAGE_PREFIX))], array_slice($block, $n + 1))));
                break;
            }
            if (str_starts_with($line, 'Wrapping fee:')) {
                $summary = trim(substr($line, strlen('Wrapping fee:')));
            } elseif (str_starts_with($line, '- ')) {
                $items[] = substr($line, 2);
            }
        }

        return ['note' => $note === '' ? null : $note, 'gift' => ['summary' => $summary, 'items' => $items, 'message' => $message === '(none)' ? '' : $message]];
    }
}
