<?php

namespace App\Http\Controllers;

use App\Models\CardPayments;
use App\Models\Checkout;
use App\Models\CustomerOrders;
use App\Payments\PaymentMethods;
use App\Payments\Stripe;
use App\Support\GiftWrap;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckoutController extends Controller
{
    /** How long an identical repeat of a successful place-order request returns the same order. */
    private const REPLAY_SECONDS = 60;

    private function itemRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.productId' => ['required', 'integer'],
            'items.*.color' => ['nullable', 'string', 'max:80'],
            'items.*.size' => ['nullable', 'string', 'max:80'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'coupon' => ['nullable', 'string', 'max:100'],
            // Optional gift wrapping: which cart lines (and how many of each) to wrap, plus a message. Validated and
            // priced server-side by GiftWrap/Checkout::totals; the browser never sends a fee.
            'gift' => ['nullable', 'array'],
            'gift.enabled' => ['nullable', 'boolean'],
            'gift.message' => ['nullable', 'string', 'max:'.GiftWrap::MESSAGE_MAX],
            'gift.items' => ['nullable', 'array', 'max:100'],
            'gift.items.*.productId' => ['required_with:gift.items', 'integer'],
            'gift.items.*.color' => ['nullable', 'string', 'max:80'],
            'gift.items.*.size' => ['nullable', 'string', 'max:80'],
            'gift.items.*.quantity' => ['required_with:gift.items', 'integer', 'min:1', 'max:99'],
        ];
    }

    /** Gift wrapping adds the fee to every total; "on but nothing selected" is refused when placing (quote just shows no fee). */
    private function giftTotals(array $data, array $lines): array
    {
        return Checkout::totals($lines, $data['coupon'] ?? null, $this->customerId(), $data['gift'] ?? null);
    }

    /** Authoritative prices and totals for the checkout summary. */
    public function quote(Request $request): JsonResponse
    {
        abort_unless($this->customerId(), 401, 'Please sign in to checkout.');
        $data = $request->validate($this->itemRules());
        $priced = Checkout::priceLines($data['items']);
        $totals = $this->giftTotals($data, $priced['lines']);

        // "total" = cash-on-delivery figure; "online" = the pre-booking breakdown (all numbers computed by OnlinePaymentPlan).
        return response()->json($totals + [
            'lines' => array_map(fn ($l) => [
                'productId' => $l['productId'], 'name' => $l['name'], 'image' => $l['image'], 'size' => $l['size'], 'color' => $l['color'],
                'price' => $l['price'], 'quantity' => $l['quantity'],
            ], $priced['lines']),
            'errors' => $priced['errors'],
        ]);
    }

    public function place(Request $request): JsonResponse
    {
        $customerId = $this->customerId();
        abort_unless($customerId, 401, 'Please sign in to checkout.');
        $rules = $this->itemRules() + [
            'paymentMethod' => ['required', Rule::in(PaymentMethods::enabled())],
            'note' => ['nullable', 'string', 'max:1000'],
            'addressId' => ['nullable', 'integer'],
            'address' => ['nullable', 'array'],
            'address.fullName' => ['required_without:addressId', 'string', 'max:180'],
            'address.phone' => ['required_without:addressId', 'string', 'max:40', 'regex:'.AccountController::PHONE_REGEX],
            'address.street' => ['required_without:addressId', 'string', 'max:255'],
            'address.suburb' => ['required_without:addressId', 'string', 'max:120'],
            'address.state' => ['required_without:addressId', Rule::in(AccountController::STATES)],
            'address.postcode' => ['required_without:addressId', 'digits:4'],
        ];
        $data = $request->validate($rules, ['address.phone.regex' => AccountController::PHONE_MESSAGE]);

        // Duplicate-submission guard: one placement per customer at a time, and the same request repeated within
        // REPLAY_SECONDS (double click, network retry, second tab) gets the order already created, not a second one.
        $cache = Cache::store('file');
        $replayKey = 'checkout:placed:'.hash('sha256', json_encode([
            $customerId, $data['items'], $data['addressId'] ?? null, $data['address'] ?? null, $data['coupon'] ?? null, $data['paymentMethod'], $data['note'] ?? null,
            $data['gift'] ?? null,
        ]));
        $lock = $cache->lock('checkout:place:'.$customerId, 30);
        try {
            $lock->block(10);
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'Your order is already being placed. Please wait a moment.'], 429);
        }

        try {
            if ($previous = $cache->get($replayKey)) {
                return response()->json($previous + ['duplicate' => true], 200);
            }

            return $this->createOrder($data, $customerId, $cache, $replayKey);
        } finally {
            $lock->release();
        }
    }

    private function createOrder(array $data, int $customerId, Repository $cache, string $replayKey): JsonResponse
    {
        $address = $data['address'] ?? null;
        if (! empty($data['addressId'])) {
            abort_unless($customerId, 401, 'Please sign in to use saved addresses.');
            $row = DB::table('customer_addresses')->where('id', $data['addressId'])->where('customer_id', $customerId)->first();
            abort_unless($row, 422, 'Please choose a valid delivery address.');
            $address = [
                'fullName' => $row->recipient_name, 'phone' => $row->phone, 'street' => $row->street_address,
                'suburb' => $row->suburb, 'state' => $row->state, 'postcode' => $row->postcode,
            ];
        }
        // Saved addresses can be incomplete (e.g. created from the Profile form, which has no suburb/state fields).
        if ($problem = AccountController::addressProblem((array) $address)) {
            return response()->json(['message' => $problem, 'errors' => ['addressId' => [$problem]]], 422);
        }

        $priced = Checkout::priceLines($data['items']);
        if ($priced['errors'] || ! $priced['lines']) {
            return response()->json(['message' => $priced['errors'][0] ?? 'Your cart is empty.', 'errors' => ['items' => $priced['errors']]], 422);
        }
        $totals = $this->giftTotals($data, $priced['lines']);
        if ($totals['couponError']) {
            return response()->json(['message' => $totals['couponError']], 422);
        }
        if (! empty($data['gift']['enabled']) && ! $totals['gift']) {
            return response()->json(['message' => 'Choose at least one item to gift wrap, or turn off "Send as a gift".'], 422);
        }

        $card = $data['paymentMethod'] === 'card';
        $totals = Checkout::forMethod($totals, $data['paymentMethod']);
        // Stripe charges the deposit only, so the deposit is what must reach Stripe's minimum.
        if ($card && Stripe::cents(number_format($totals['chargeNow'], 2, '.', '')) < Stripe::MIN_AMOUNT_CENTS) {
            return response()->json(['message' => 'The online payment deposit must be at least '.config('storefront.currency_symbol').'0.50. Please choose another payment method.'], 422);
        }

        $order = Checkout::place($priced['lines'], $totals, $address, PaymentMethods::get($data['paymentMethod']), $customerId, GiftWrap::composeNote($data['note'] ?? null, $totals['gift']));
        $payload = $order + [
            'address' => "{$address['fullName']}, {$address['street']}, {$address['suburb']} {$address['state']} {$address['postcode']}",
        ];

        // Card: the order exists (pending, unpaid); send the customer to Stripe's hosted page. If Stripe can't be reached
        // the order is kept and the customer can pay from the order page ("Pay now"), never a second order.
        if ($card) {
            $payload['orderUrl'] = CustomerOrders::url($order['orderNumber']);
            try {
                $payload['redirectUrl'] = CardPayments::start($customerId, $order['orderNumber'])['redirectUrl'];
            } catch (HttpException $e) {
                $payload['paymentError'] = $e->getMessage();
            }
        }
        $cache->put($replayKey, $payload, self::REPLAY_SECONDS);

        return response()->json($payload, 201);
    }
}
