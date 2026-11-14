<?php

namespace App\Http\Controllers;

use App\Models\Locations;
use App\Models\Storefront;
use App\Support\DisplayTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public const STATES = ['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA'];

    /** Phone: digits with optional +, spaces, dashes, brackets; 8-15 digits in total (AU landline/mobile, +61). */
    public const PHONE_MESSAGE = 'Please enter a valid phone number (8 to 15 digits).';

    public const PHONE_REGEX = '/^(?=(?:\D*\d){8,15}\D*$)\+?[0-9 ()\-]{8,24}$/';

    /** Delivery address problem for a saved/inline address in checkout shape, or null when it is complete. */
    public static function addressProblem(array $a): ?string
    {
        foreach (['fullName' => 'recipient name', 'street' => 'street address', 'suburb' => 'suburb', 'state' => 'state', 'postcode' => 'postcode', 'phone' => 'phone number'] as $k => $label) {
            if (trim((string) ($a[$k] ?? '')) === '') {
                return "This delivery address is missing a {$label}. Please edit the address and try again.";
            }
        }
        if (! in_array($a['state'], self::STATES, true)) {
            return 'This delivery address has an invalid state. Please edit the address and try again.';
        }
        if (! preg_match('/^\d{4}$/', (string) $a['postcode'])) {
            return 'This delivery address needs a 4-digit postcode. Please edit the address and try again.';
        }
        if (! preg_match(self::PHONE_REGEX, (string) $a['phone'])) {
            return 'This delivery address has an invalid phone number. Please edit the address and try again.';
        }

        return null;
    }

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

        // The form shows the default address as "street, suburb, state". Saving it back unchanged used to copy that whole
        // text into street_address (duplicating suburb/state and growing on every save), so only write when it changed.
        $shown = Storefront::customer($id);
        $address = trim((string) ($data['address'] ?? ''));
        $addressChanged = $address !== '' && ($address !== ($shown['address'] ?? '') || (string) ($data['postcode'] ?? '') !== (string) ($shown['postcode'] ?? ''));

        if ($addressChanged && ! $phone) {
            return response()->json(['message' => 'Please add a phone number to save an address.'], 422);
        }

        DB::transaction(function () use ($id, $parts, $phone, $data, $address, $addressChanged) {
            DB::table('customers')->where('id', $id)->update([
                'first_name' => mb_substr($parts[0], 0, 100), 'last_name' => isset($parts[1]) ? mb_substr($parts[1], 0, 100) : null, 'phone' => $phone,
            ]);
            if ($addressChanged) {
                $default = DB::table('customer_addresses')->where('customer_id', $id)->orderByDesc('is_default')->orderBy('id')->first(['id']);
                // Free text cannot be split reliably, so suburb/state are cleared rather than left pointing at the old
                // address; checkout then asks the customer to complete the address before it can be used.
                $row = ['recipient_name' => trim($data['name']), 'phone' => $phone, 'street_address' => $address, 'postcode' => $data['postcode'] ?? null, 'suburb' => null, 'state' => null];
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
                'details' => $r->details, 'status' => $label($r->status), 'date' => DisplayTime::date($r->created_at),
            ])->values(),
            'bulk' => $bulk->map(fn ($r) => [
                'requestNumber' => $r->request_number, 'status' => $label($r->status), 'createdAt' => $r->created_at, 'date' => DisplayTime::date($r->created_at),
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

    /** Built-in location labels ("Office" is shown as "Work"); any other stored label is a customer's custom "Other" label. */
    public const ADDRESS_TYPES = ['Home', 'Office', 'Other'];

    /**
     * customer_addresses has no address-line-2 column, so the optional second line is kept after a newline in
     * street_address (see sql/proposed_changes.sql). Outside the My Locations form it is shown as one line.
     */
    public static function streetLine(?string $street): string
    {
        return implode(', ', array_filter(array_map('trim', explode("\n", (string) $street)), 'strlen'));
    }

    public static function addressList(int $customerId): array
    {
        return DB::table('customer_addresses')->where('customer_id', $customerId)->orderByDesc('is_default')->orderBy('id')
            ->get(['id', 'label', 'recipient_name', 'phone', 'street_address', 'suburb', 'state', 'postcode', 'is_default'])
            ->map(function ($a) {
                $label = trim((string) $a->label) ?: 'Home';
                $type = in_array($label, self::ADDRESS_TYPES, true) ? $label : 'Other';
                [$line1, $line2] = array_pad(explode("\n", (string) $a->street_address, 2), 2, '');

                return [
                    'id' => (int) $a->id, 'addressType' => $type, 'customLabel' => $type === 'Other' && $label !== 'Other' ? $label : '',
                    'fullName' => $a->recipient_name, 'phone' => $a->phone,
                    'street' => self::streetLine($a->street_address), 'line1' => trim($line1), 'line2' => trim($line2),
                    'suburb' => $a->suburb ?? '', 'state' => $a->state ?? '', 'postcode' => $a->postcode ?? '', 'country' => 'Australia',
                    'isDefault' => (bool) $a->is_default,
                ];
            })->all();
    }

    private function addressRules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:180'],
            'phone' => ['required', 'string', 'max:40', 'regex:'.self::PHONE_REGEX],
            // Checkout sends one "street" line; My Locations sends line1 (+ optional line2).
            'street' => ['required_without:line1', 'nullable', 'string', 'max:255'],
            'line1' => ['required_without:street', 'nullable', 'string', 'max:160', 'not_regex:/[\r\n]/'],
            'line2' => ['nullable', 'string', 'max:90', 'not_regex:/[\r\n]/'],
            'suburb' => ['required', 'string', 'max:120'],
            'state' => ['required', Rule::in(self::STATES)],
            'postcode' => ['required', 'digits:4', 'regex:/^(0[289]\d{2}|[1-9]\d{3})$/'],
            'country' => ['nullable', Rule::in(['Australia', 'AU'])],
            'addressType' => ['required', Rule::in(self::ADDRESS_TYPES)],
            'customLabel' => ['nullable', 'string', 'max:40', 'not_regex:/[\r\n]/'],
            'isDefault' => ['nullable', 'boolean'],
        ];
    }

    /** Australia Post postcode ranges per state/territory (inclusive). */
    public const STATE_POSTCODES = [
        'NSW' => [[1000, 2599], [2619, 2899], [2921, 2999]],
        'ACT' => [[200, 299], [2600, 2618], [2900, 2920]],
        'VIC' => [[3000, 3999], [8000, 8999]],
        'QLD' => [[4000, 4999], [9000, 9999]],
        'SA' => [[5000, 5999]],
        'WA' => [[6000, 6999]],
        'TAS' => [[7000, 7999]],
        'NT' => [[800, 999]],
    ];

    public static function postcodeMatchesState(string $postcode, string $state): bool
    {
        $n = (int) $postcode;
        foreach (self::STATE_POSTCODES[$state] ?? [] as [$from, $to]) {
            if ($n >= $from && $n <= $to) {
                return true;
            }
        }

        return false;
    }

    private function validateAddress(Request $request): array
    {
        $rules = $this->addressRules();
        $rules['postcode'][] = function (string $attr, mixed $value, \Closure $fail) use ($request) {
            $state = (string) $request->input('state');
            if (! in_array($state, self::STATES, true) || ! preg_match('/^\d{4}$/', (string) $value)) {
                return;
            }
            // The central location dataset decides when it is loaded; otherwise the old state/postcode ranges apply.
            if (! self::postcodeMatchesState((string) $value, $state)) {
                $fail("This postcode is not in {$state}. Please check the postcode and state.");
            } elseif ($problem = Locations::problem($state, (string) $request->input('suburb'), (string) $value)) {
                $fail($problem);
            }
        };

        return $request->validate($rules, [
            'phone.regex' => self::PHONE_MESSAGE,
            'postcode.digits' => 'Please enter a valid 4-digit Australian postcode.',
            'postcode.regex' => 'Please enter a valid 4-digit Australian postcode.',
            'state.in' => 'Please choose an Australian state or territory.',
            'country.in' => 'We currently deliver within Australia only.',
            'line1.required_without' => 'Please enter the street address.',
            'street.required_without' => 'Please enter the street address.',
        ]);
    }

    /** @param  string|null  $currentLabel  stored label, kept when a form without a custom-label field (checkout) saves an "Other" address */
    private function addressRow(array $d, ?string $currentLabel = null): array
    {
        $street = isset($d['line1']) && trim((string) $d['line1']) !== ''
            ? trim(trim($d['line1'])."\n".trim((string) ($d['line2'] ?? '')))
            : trim((string) $d['street']);
        $label = $d['addressType'];
        if ($label === 'Other') {
            $custom = trim((string) ($d['customLabel'] ?? ''));
            if (! array_key_exists('customLabel', $d) && $currentLabel !== null && ! in_array($currentLabel, ['Home', 'Office'], true)) {
                $custom = $currentLabel;
            }
            // A custom label equal to a built-in one is stored as that type.
            $label = ['home' => 'Home', 'work' => 'Office', 'office' => 'Office'][strtolower($custom)] ?? ($custom !== '' ? $custom : 'Other');
        }

        return [
            'label' => $label, 'recipient_name' => trim($d['fullName']), 'phone' => trim($d['phone']), 'street_address' => $street,
            'suburb' => trim($d['suburb']), 'state' => $d['state'], 'postcode' => $d['postcode'],
        ];
    }

    /** Make one of the customer's addresses the only default (caller holds a transaction). */
    private static function makeDefault(int $customerId, int $addressId): void
    {
        DB::table('customer_addresses')->where('customer_id', $customerId)->where('id', '!=', $addressId)->where('is_default', 1)->update(['is_default' => 0]);
        DB::table('customer_addresses')->where('customer_id', $customerId)->where('id', $addressId)->update(['is_default' => 1]);
    }

    public function storeAddress(Request $request): JsonResponse
    {
        $id = $this->require();
        $d = $this->validateAddress($request);
        $newId = DB::transaction(function () use ($id, $d) {
            // Lock the customer's rows so two concurrent saves cannot both become the default.
            $hasAny = DB::table('customer_addresses')->where('customer_id', $id)->lockForUpdate()->exists();
            $newId = DB::table('customer_addresses')->insertGetId($this->addressRow($d) + ['customer_id' => $id, 'is_default' => 0]);
            if (! $hasAny || ! empty($d['isDefault'])) {
                self::makeDefault($id, $newId);
            }

            return $newId;
        });

        return response()->json(['id' => $newId, 'addresses' => self::addressList($id)]);
    }

    public function updateAddress(Request $request, int $addressId): JsonResponse
    {
        $id = $this->require();
        $d = $this->validateAddress($request);
        DB::transaction(function () use ($id, $addressId, $d) {
            $current = DB::table('customer_addresses')->where('id', $addressId)->where('customer_id', $id)->lockForUpdate()->first(['label']);
            abort_unless($current, 404);
            DB::table('customer_addresses')->where('id', $addressId)->update($this->addressRow($d, $current->label));
            if (! empty($d['isDefault'])) {
                self::makeDefault($id, $addressId);
            }
        });

        return response()->json(['id' => $addressId, 'addresses' => self::addressList($id)]);
    }

    public function defaultAddress(int $addressId): JsonResponse
    {
        $id = $this->require();
        DB::transaction(function () use ($id, $addressId) {
            $exists = DB::table('customer_addresses')->where('customer_id', $id)->lockForUpdate()->pluck('id')->contains($addressId);
            abort_unless($exists, 404);
            self::makeDefault($id, $addressId);
        });

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
