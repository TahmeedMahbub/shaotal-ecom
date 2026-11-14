<?php

namespace App\Mail;

use App\Support\DisplayTime;
use App\Support\Shipping;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Order confirmation (HTML + plain text). Built only from what is STORED on the order (App\Models\CustomerOrders::find):
 * prices, gift fee and payment state are never recalculated. No payment ids, Stripe ids, tokens or error text are
 * ever put in it. Sender comes from config/mail.php (.env), the logo URL from APP_URL (BrandedCodeMail::logoUrl).
 */
class OrderConfirmationMail extends Mailable
{
    /** @param array $order CustomerOrders::find() result */
    public function __construct(public array $order, public string $firstName) {}

    public function envelope(): Envelope
    {
        $brand = (string) config('storefront.brand');
        $subject = $this->order['prebooking']['depositPaid'] ?? false
            ? "Your {$brand} order {$this->order['number']} is confirmed"
            : "We've received your {$brand} order {$this->order['number']}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-confirmation', text: 'emails.order-confirmation-text', with: $this->viewData());
    }

    /** Everything the two templates print, already formatted (one place, so HTML and text can never disagree). */
    public function viewData(): array
    {
        $o = $this->order;
        $sym = (string) config('storefront.currency_symbol');
        $money = fn (float $n) => $sym.number_format($n, 2);
        $t = $o['totals'];
        $pre = $o['prebooking'];
        $card = $pre !== null;
        $a = $o['address'];
        $delivery = $o['delivery'] ?? ['fulfillment' => 'delivery', 'label' => 'Delivery'];
        $pickup = $delivery['fulfillment'] === 'pickup';

        $rows = [['Subtotal', $money($t['subtotal']), false]];
        if ($t['giftFee'] > 0) {
            $rows[] = ['Gift wrapping', $money($t['giftFee']), false];
        }
        if ($t['discount'] > 0) {
            $rows[] = ['Discount'.($t['coupon'] ? " ({$t['coupon']})" : ''), '-'.$money($t['discount']), false];
        }
        if ($card && $pre['discount'] > 0) {
            $rows[] = ['Online payment discount ('.rtrim(rtrim(number_format($pre['discountPercent'], 2), '0'), '.').'%)', '-'.$money($pre['discount']), false];
        }
        $rows[] = ['Shipping ('.$delivery['label'].')', $t['shipping'] > 0 ? $money($t['shipping']) : 'Free', false];
        if ($t['tax'] > 0) {
            $rows[] = ['Tax', $money($t['tax']), false];
        }
        $rows[] = ['Total', $money($t['total']).' AUD', true];

        // Payment wording follows the stored, verified state only.
        if ($card && $pre['depositPaid']) {
            $payment = [
                'status' => $pre['paid'] >= $t['total'] ? 'Paid in full' : 'Deposit received',
                'lines' => $pre['paid'] >= $t['total']
                    ? ['We have received your card payment of '.$money($pre['paid']).'.']
                    : ['We have received your '.$money($pre['paid']).' pre-booking deposit ('.rtrim(rtrim(number_format($pre['depositPercent'], 2), '0'), '.').'%) by card.',
                        'Remaining balance: '.$money($pre['balance']).'. It is still outstanding on this order and has not been charged.',
                        $pre['balanceTerms']],
            ];
        } else {
            $payment = $pickup
                ? ['status' => 'Pay on pickup', 'lines' => ['Nothing is due today. The '.$money($t['total']).' total is payable when you collect your order.']]
                : ['status' => 'Pay on delivery', 'lines' => ['Nothing is due today. The '.$money($t['total']).' total is payable when your order is delivered.']];
        }

        $gift = null;
        if ($o['gift']) {
            $count = array_sum(array_map(fn ($i) => (int) (preg_match('/^(\d+) x /', $i, $m) ? $m[1] : 1), $o['gift']['items']));
            $gift = [
                'items' => $o['gift']['items'], 'count' => $count, 'fee' => $money((float) $o['gift']['fee']),
                'message' => $o['gift']['message'] ?? '',
            ];
        }

        $base = rtrim((string) config('app.url'), '/');
        if (! app()->environment('local', 'testing')) {
            $base = preg_replace('#^http://#i', 'https://', $base);
        }

        return [
            'brand' => (string) config('storefront.brand'),
            'logoUrl' => BrandedCodeMail::logoUrl(),
            'firstName' => $this->firstName,
            'number' => $o['number'],
            'placedOn' => DisplayTime::dateTime($o['placedAt']),
            'items' => array_map(fn ($i) => [
                'name' => $i['name'], 'variant' => implode(' · ', array_filter([$i['size'] ? 'Size '.$i['size'] : null, $i['color'] ? $i['color'] : null])),
                'qty' => $i['quantity'], 'unit' => $money($i['price']), 'total' => $money($i['lineTotal']),
            ], $o['items']),
            'rows' => $rows,
            'payment' => $payment,
            'paymentMethod' => $o['paymentMethod'],
            'gift' => $gift,
            'deliveryHeading' => $pickup ? 'Pickup' : 'Delivering to',
            'deliveryNote' => $pickup
                ? (Shipping::pickup()['details'] ?: 'We will contact you to confirm when and where to collect your order.').' You can follow this order at any time from My Orders.'
                : 'We deliver within Australia. You can follow this order at any time from My Orders.',
            'address' => $pickup
                ? array_filter([$delivery['label'], 'Collected by '.$a['name']])
                : array_filter([$a['name'], $a['street'], trim(($a['suburb'] ?? '').' '.($a['state'] ?? '').' '.($a['postcode'] ?? '')), 'Australia']),
            'phone' => $a['phone'],
            'orderUrl' => $base.'/account/orders/'.rawurlencode($o['number']),
            'year' => date('Y'),
        ];
    }
}
