<?php

namespace App\Http\Controllers;

use App\Models\Checkout;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        ];
    }

    /** Authoritative prices and totals for the checkout summary. */
    public function quote(Request $request): JsonResponse
    {
        abort_unless($this->customerId(), 401, 'Please sign in to checkout.');
        $data = $request->validate($this->itemRules());
        $priced = Checkout::priceLines($data['items']);
        $totals = Checkout::totals($priced['lines'], $data['coupon'] ?? null, $this->customerId());

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
            'paymentMethod' => ['required', Rule::in(config('storefront.payment_methods'))],
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
            $customerId, $data['items'], $data['addressId'] ?? null, $data['address'] ?? null, $data['coupon'] ?? null, $data['paymentMethod'],
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
        $totals = Checkout::totals($priced['lines'], $data['coupon'] ?? null, $customerId);
        if ($totals['couponError']) {
            return response()->json(['message' => $totals['couponError']], 422);
        }

        $order = Checkout::place($priced['lines'], $totals, $address, $data['paymentMethod'], $customerId, $data['note'] ?? null);
        $payload = $order + [
            'address' => "{$address['fullName']}, {$address['street']}, {$address['suburb']} {$address['state']} {$address['postcode']}",
        ];
        $cache->put($replayKey, $payload, self::REPLAY_SECONDS);

        return response()->json($payload, 201);
    }
}
