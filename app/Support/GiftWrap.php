<?php

namespace App\Support;

/**
 * Optional gift wrapping. THE single place that knows the price rule and how a gift selection is validated, stored
 * and read back (Blade/JS only display the numbers the server returns).
 *
 * Fee (config storefront.gift_wrap), in cents so it is exact: the first 2 wrapped items cost A$1.99 in total, each
 * further wrapped item adds A$1.00. It depends only on the number of items selected to wrap, never on the cart size.
 *
 * Storage (no schema change; see sql/proposed_changes.sql for the dedicated-columns proposal):
 *   - the fee is an extra order_items row (product_id NULL, sku GIFT_SKU) and is part of orders.subtotal, so the admin
 *     app's  subtotal - discount_total + shipping_total + tax_total = grand_total  keeps holding;
 *   - the wrapped items and the gift message are a readable block appended to orders.customer_note (GIFT_MARKER),
 *     separate from the customer's own note, which split() separates again for the storefront.
 */
final class GiftWrap
{
    public const SKU = 'GIFT-WRAP';

    public const MARKER = '--- GIFT WRAPPING ---';

    public const MESSAGE_PREFIX = 'Gift message:';

    public const MESSAGE_MAX = 300;

    /** @return array{baseFee: float, baseItems: int, extraFee: float} */
    public static function rules(): array
    {
        $c = (array) config('storefront.gift_wrap', []);

        return [
            'baseFee' => (float) ($c['base_fee'] ?? 1.99), 'baseItems' => (int) ($c['base_items'] ?? 2), 'extraFee' => (float) ($c['extra_fee'] ?? 1.00),
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
     * @return null|array{items: array, quantity: int, fee: float, message: string}
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
        $total = 0;
        foreach ($lines as $l) {
            $qty = min((int) $l['quantity'], $wanted[$l['productId'].'|'.($l['colorKey'] ?? '').'|'.($l['size'] ?? '')] ?? 0);
            if ($qty > 0) {
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

        return ['items' => $items, 'quantity' => $total, 'fee' => self::fee($total), 'message' => $message];
    }

    /** The customer's note with the gift block appended (the note alone when there is no gift). */
    public static function composeNote(?string $note, ?array $gift): ?string
    {
        $note = trim(str_replace(self::MARKER, '', (string) $note));
        if (! $gift) {
            return $note === '' ? null : $note;
        }
        $symbol = config('storefront.currency_symbol');
        $lines = [self::MARKER, "Wrapping fee: {$symbol}".number_format($gift['fee'], 2).' for '.$gift['quantity'].' '.($gift['quantity'] === 1 ? 'item' : 'items')];
        foreach ($gift['items'] as $i) {
            $variant = implode(' / ', array_filter([$i['size'], $i['color']]));
            $lines[] = '- '.$i['quantity'].' x '.$i['name'].($variant !== '' ? " ({$variant})" : '');
        }
        $lines[] = self::MESSAGE_PREFIX.($gift['message'] !== '' ? ' '.$gift['message'] : ' (none)');

        return ($note === '' ? '' : $note."\n\n").implode("\n", $lines);
    }

    /**
     * Split a stored customer_note into the customer's own note and the gift block.
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
