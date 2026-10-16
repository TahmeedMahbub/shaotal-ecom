<?php

namespace App\Http\Controllers;

use App\Models\Checkout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
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
        $rules = $this->itemRules() + [
            'paymentMethod' => ['required', Rule::in(config('storefront.payment_methods'))],
            'note' => ['nullable', 'string', 'max:1000'],
            'addressId' => ['nullable', 'integer'],
            'address' => ['nullable', 'array'],
            'address.fullName' => ['required_without:addressId', 'string', 'max:180'],
            'address.phone' => ['required_without:addressId', 'string', 'max:40'],
            'address.street' => ['required_without:addressId', 'string', 'max:255'],
            'address.suburb' => ['required_without:addressId', 'string', 'max:120'],
            'address.state' => ['required_without:addressId', Rule::in(AccountController::STATES)],
            'address.postcode' => ['required_without:addressId', 'digits:4'],
        ];
        $data = $request->validate($rules);

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

        $priced = Checkout::priceLines($data['items']);
        if ($priced['errors'] || ! $priced['lines']) {
            return response()->json(['message' => $priced['errors'][0] ?? 'Your cart is empty.', 'errors' => ['items' => $priced['errors']]], 422);
        }
        $totals = Checkout::totals($priced['lines'], $data['coupon'] ?? null, $customerId);
        if ($totals['couponError']) {
            return response()->json(['message' => $totals['couponError']], 422);
        }

        $order = Checkout::place($priced['lines'], $totals, $address, $data['paymentMethod'], $customerId, $data['note'] ?? null);

        return response()->json($order + [
            'address' => "{$address['fullName']}, {$address['street']}, {$address['suburb']} {$address['state']} {$address['postcode']}",
        ], 201);
    }
}
