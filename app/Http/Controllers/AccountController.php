<?php

namespace App\Http\Controllers;

use App\Models\Storefront;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public const STATES = ['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA'];

    private function require(): int
    {
        $id = $this->customerId();
        abort_unless($id, 401, 'Please sign in to continue.');

        return $id;
    }

    public function profile(Request $request): JsonResponse
    {
        $id = $this->require();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:40', Rule::unique('customers', 'phone')->ignore($id)],
            'postcode' => ['nullable', 'digits:4'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        $parts = preg_split('/\s+/', trim($data['name']), 2);
        $phone = trim((string) ($data['phone'] ?? '')) ?: null;

        if (! empty($data['address']) && ! $phone) {
            return response()->json(['message' => 'Please add a phone number to save an address.'], 422);
        }

        DB::transaction(function () use ($id, $parts, $phone, $data) {
            DB::table('customers')->where('id', $id)->update([
                'first_name' => mb_substr($parts[0], 0, 100), 'last_name' => isset($parts[1]) ? mb_substr($parts[1], 0, 100) : null, 'phone' => $phone,
            ]);
            if (! empty($data['address'])) {
                $default = DB::table('customer_addresses')->where('customer_id', $id)->orderByDesc('is_default')->orderBy('id')->first(['id']);
                $row = ['recipient_name' => trim($data['name']), 'phone' => $phone, 'street_address' => $data['address'], 'postcode' => $data['postcode'] ?? null];
                if ($default) {
                    DB::table('customer_addresses')->where('id', $default->id)->update($row);
                } else {
                    DB::table('customer_addresses')->insert($row + ['customer_id' => $id, 'label' => 'Home', 'is_default' => 1]);
                }
            }
        });

        return response()->json(['user' => Storefront::customer($id)]);
    }

    /** Merge a guest's local cart/wishlist into the signed-in customer's (used after Google sign-in). */
    public function merge(Request $request): JsonResponse
    {
        $id = $this->require();
        $data = $request->validate(['cart' => ['nullable', 'array', 'max:100'], 'wishlist' => ['nullable', 'array', 'max:200']]);
        Storefront::mergeGuest($id, array_values(array_filter((array) ($data['cart'] ?? []), 'is_array')), array_map('intval', (array) ($data['wishlist'] ?? [])));

        return response()->json(Storefront::sessionPayload($id));
    }

    public function orders(): JsonResponse
    {
        $id = $this->require();
        $orders = DB::table('orders')->where('customer_id', $id)->orderByDesc('id')->limit(100)
            ->get(['id', 'order_number', 'order_status', 'payment_status', 'grand_total', 'created_at']);
        $items = DB::table('order_items')->whereIn('order_id', $orders->pluck('id')->all() ?: [0])->orderBy('id')
            ->get(['order_id', 'product_name', 'image_url', 'variant_color', 'variant_size', 'unit_selling_price', 'quantity'])->groupBy('order_id');

        return response()->json($orders->map(fn ($o) => [
            'id' => $o->order_number,
            'date' => substr((string) $o->created_at, 0, 10),
            'total' => (float) $o->grand_total,
            'status' => ucwords(str_replace('_', ' ', $o->order_status)),
            'items' => ($items[$o->id] ?? collect())->map(fn ($i) => [
                'name' => $i->product_name, 'price' => (float) $i->unit_selling_price, 'quantity' => (int) $i->quantity,
                'size' => $i->variant_size, 'color' => $i->variant_color, 'image' => $i->image_url,
            ])->values(),
        ])->values());
    }

    public function requests(): JsonResponse
    {
        $id = $this->require();
        $label = fn ($s) => ucwords(str_replace('_', ' ', $s));

        $sourcing = DB::table('sourcing_requests')->where('customer_id', $id)->orderByDesc('id')->limit(100)
            ->get(['request_number', 'product_name', 'product_url', 'requested_quantity', 'details', 'status', 'created_at']);
        $bulk = DB::table('bulk_order_requests')->where('customer_id', $id)->orderByDesc('id')->limit(100)
            ->get(['id', 'request_number', 'status', 'created_at']);
        $bulkItems = DB::table('bulk_order_request_items')->whereIn('request_id', $bulk->pluck('id')->all() ?: [0])->get(['id', 'request_id', 'product_name', 'quantity'])->groupBy('request_id');
        $variants = DB::table('bulk_order_item_variants')
            ->whereIn('bulk_order_request_item_id', $bulkItems->flatten(1)->pluck('id')->all() ?: [0])
            ->get(['bulk_order_request_item_id', 'color_name', 'size_label', 'quantity'])->groupBy('bulk_order_request_item_id');

        return response()->json([
            'sourcing' => $sourcing->map(fn ($r) => [
                'id' => $r->request_number, 'title' => $r->product_name, 'link' => $r->product_url, 'qty' => (int) $r->requested_quantity,
                'details' => $r->details, 'status' => $label($r->status), 'date' => substr((string) $r->created_at, 0, 10),
            ])->values(),
            'bulk' => $bulk->map(fn ($r) => [
                'requestNumber' => $r->request_number, 'status' => $label($r->status), 'createdAt' => $r->created_at,
                'totalUnits' => (int) ($bulkItems[$r->id] ?? collect())->sum('quantity'),
                'items' => ($bulkItems[$r->id] ?? collect())->map(fn ($i) => [
                    'name' => $i->product_name,
                    'variants' => ($variants[$i->id] ?? collect())->map(fn ($v) => ['color' => $v->color_name ?? '-', 'size' => $v->size_label ?? '-', 'qty' => (int) $v->quantity])->values(),
                ])->values(),
            ])->values(),
        ]);
    }

    public function saveCart(Request $request): JsonResponse
    {
        $id = $this->require();
        $data = $request->validate([
            'items' => ['present', 'array', 'max:100'],
            'items.*.productId' => ['required', 'integer'],
            'items.*.color' => ['nullable', 'string', 'max:80'],
            'items.*.size' => ['nullable', 'string', 'max:80'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);
        Storefront::replaceCart($id, $data['items']);

        return response()->json(['ok' => true]);
    }

    public function saveWishlist(Request $request): JsonResponse
    {
        $id = $this->require();
        $data = $request->validate(['ids' => ['present', 'array', 'max:200'], 'ids.*' => ['integer']]);
        Storefront::replaceWishlist($id, $data['ids']);

        return response()->json(['ok' => true]);
    }

    /* ── Saved delivery addresses ── */

    public function addresses(): JsonResponse
    {
        return response()->json(self::addressList($this->require()));
    }

    public static function addressList(int $customerId): array
    {
        return DB::table('customer_addresses')->where('customer_id', $customerId)->orderByDesc('is_default')->orderBy('id')
            ->get(['id', 'label', 'recipient_name', 'phone', 'street_address', 'suburb', 'state', 'postcode', 'is_default'])
            ->map(fn ($a) => [
                'id' => (int) $a->id, 'addressType' => $a->label ?: 'Home', 'fullName' => $a->recipient_name, 'phone' => $a->phone,
                'street' => $a->street_address, 'suburb' => $a->suburb ?? '', 'state' => $a->state ?? '', 'postcode' => $a->postcode ?? '',
                'isDefault' => (bool) $a->is_default,
            ])->all();
    }

    private function addressRules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:180'],
            'phone' => ['required', 'string', 'max:40'],
            'street' => ['required', 'string', 'max:255'],
            'suburb' => ['required', 'string', 'max:120'],
            'state' => ['required', Rule::in(self::STATES)],
            'postcode' => ['required', 'digits:4'],
            'addressType' => ['required', Rule::in(['Home', 'Office', 'Other'])],
        ];
    }

    private function addressRow(array $d): array
    {
        return [
            'label' => $d['addressType'], 'recipient_name' => $d['fullName'], 'phone' => $d['phone'], 'street_address' => $d['street'],
            'suburb' => $d['suburb'], 'state' => $d['state'], 'postcode' => $d['postcode'],
        ];
    }

    public function storeAddress(Request $request): JsonResponse
    {
        $id = $this->require();
        $d = $request->validate($this->addressRules());
        $isFirst = ! DB::table('customer_addresses')->where('customer_id', $id)->exists();
        $newId = DB::table('customer_addresses')->insertGetId($this->addressRow($d) + ['customer_id' => $id, 'is_default' => $isFirst ? 1 : 0]);

        return response()->json(['id' => $newId, 'addresses' => self::addressList($id)]);
    }

    public function updateAddress(Request $request, int $addressId): JsonResponse
    {
        $id = $this->require();
        $d = $request->validate($this->addressRules());
        $n = DB::table('customer_addresses')->where('id', $addressId)->where('customer_id', $id)->update($this->addressRow($d));
        abort_unless($n || DB::table('customer_addresses')->where('id', $addressId)->where('customer_id', $id)->exists(), 404);

        return response()->json(['id' => $addressId, 'addresses' => self::addressList($id)]);
    }

    public function deleteAddress(int $addressId): JsonResponse
    {
        $id = $this->require();
        DB::transaction(function () use ($id, $addressId) {
            $addr = DB::table('customer_addresses')->where('id', $addressId)->where('customer_id', $id)->first(['is_default']);
            abort_unless($addr, 404);
            DB::table('customer_addresses')->where('id', $addressId)->delete();
            if ($addr->is_default) {
                $next = DB::table('customer_addresses')->where('customer_id', $id)->orderBy('id')->first(['id']);
                if ($next) {
                    DB::table('customer_addresses')->where('id', $next->id)->update(['is_default' => 1]);
                }
            }
        });

        return response()->json(['addresses' => self::addressList($id)]);
    }
}
