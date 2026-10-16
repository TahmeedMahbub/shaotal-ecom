<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RequestController extends Controller
{
    /** The forms take one "email or phone" field; the schema stores them separately and requires an email. */
    private function splitContact(string $contact, ?int $customerId): array
    {
        $contact = trim($contact);
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            return [strtolower($contact), null];
        }
        $account = $customerId ? DB::table('customers')->where('id', $customerId)->first(['email']) : null;
        abort_unless($account && $account->email, 422, 'Please include an email address so we can reach you.');

        return [$account->email, mb_substr($contact, 0, 40)];
    }

    private function number(string $table, string $prefix): string
    {
        do {
            $n = $prefix.'-'.random_int(100000, 999999);
        } while (DB::table($table)->where('request_number', $n)->exists());

        return $n;
    }

    public function bulk(Request $request): JsonResponse
    {
        $d = $request->validate([
            'customerName' => ['required', 'string', 'max:160'],
            'contactInfo' => ['required', 'string', 'max:190'],
            'postcode' => ['required', 'string', 'max:60'],
            'targetDate' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.name' => ['required', 'string', 'max:220'],
            'items.*.isCustom' => ['nullable', 'boolean'],
            'items.*.customSpecs' => ['nullable', 'string', 'max:1000'],
            'items.*.productLink' => ['nullable', 'string', 'max:2000'],
            'items.*.variants' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.variants.*.color' => ['nullable', 'string', 'max:80'],
            'items.*.variants.*.size' => ['nullable', 'string', 'max:80'],
            'items.*.variants.*.qty' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);
        $customerId = $this->customerId();
        [$email, $phone] = $this->splitContact($d['contactInfo'], $customerId);

        // The schema has no postcode / target-date columns yet, so they travel in the message text.
        $message = trim(implode("\n", array_filter([
            'Delivery postcode: '.$d['postcode'],
            ! empty($d['targetDate']) ? 'Target delivery date: '.$d['targetDate'] : null,
            ! empty($d['notes']) ? 'Notes: '.$d['notes'] : null,
        ])));

        $catalogIds = collect($d['items'])->pluck('id')->filter()->unique()->values()->all();
        $validIds = DB::table('products')->whereIn('id', $catalogIds ?: [0])->pluck('id')->all();

        $result = DB::transaction(function () use ($d, $customerId, $email, $phone, $message, $validIds) {
            $number = $this->number('bulk_order_requests', 'BULK');
            $requestId = DB::table('bulk_order_requests')->insertGetId([
                'request_number' => $number, 'customer_id' => $customerId, 'contact_name' => $d['customerName'],
                'email' => $email, 'phone' => $phone, 'message' => $message, 'status' => 'new',
            ]);
            $units = 0;
            foreach ($d['items'] as $item) {
                $itemUnits = array_sum(array_column($item['variants'], 'qty'));
                $itemId = DB::table('bulk_order_request_items')->insertGetId([
                    'request_id' => $requestId,
                    'product_id' => (! empty($item['id']) && in_array($item['id'], $validIds)) ? $item['id'] : null,
                    'product_name' => $item['name'],
                    'quantity' => $itemUnits,
                    'notes' => mb_substr(trim(($item['customSpecs'] ?? '').' '.($item['productLink'] ?? '')), 0, 500) ?: null,
                ]);
                DB::table('bulk_order_item_variants')->insert(array_map(fn ($v) => [
                    'bulk_order_request_item_id' => $itemId, 'color_name' => $v['color'] ?? null, 'size_label' => $v['size'] ?? null, 'quantity' => $v['qty'],
                ], $item['variants']));
                $units += $itemUnits;
            }

            return ['requestNumber' => $number, 'totalUnits' => $units];
        });

        return response()->json($result + ['contactInfo' => $d['contactInfo']], 201);
    }

    public function sourcing(Request $request): JsonResponse
    {
        $d = $request->validate([
            'customerName' => ['required', 'string', 'max:160'],
            'contactInfo' => ['required', 'string', 'max:190'],
            'postcode' => ['required', 'digits:4'],
            'itemName' => ['required', 'string', 'max:220'],
            'productLink' => ['nullable', 'string', 'max:2000'],
            'colorSize' => ['nullable', 'string', 'max:200'],
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'details' => ['nullable', 'string', 'max:3000'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'max:5120'],
        ]);
        $customerId = $this->customerId();
        [$email, $phone] = $this->splitContact($d['contactInfo'], $customerId);

        // No postcode / variant columns in the schema yet, so they are kept in the details text.
        $details = trim(implode("\n", array_filter([
            'Delivery postcode: '.$d['postcode'],
            ! empty($d['colorSize']) ? 'Preferred colour/size: '.$d['colorSize'] : null,
            $d['details'] ?? null,
        ])));

        $result = DB::transaction(function () use ($d, $request, $customerId, $email, $phone, $details) {
            $number = $this->number('sourcing_requests', 'REQ');
            $id = DB::table('sourcing_requests')->insertGetId([
                'request_number' => $number, 'customer_id' => $customerId, 'customer_name' => $d['customerName'], 'email' => $email,
                'phone' => $phone, 'product_url' => $d['productLink'] ?? null, 'product_name' => $d['itemName'],
                'requested_quantity' => $d['qty'], 'details' => $details, 'status' => 'new',
            ]);
            foreach ($request->file('images', []) as $file) {
                $path = $file->store('sourcing-requests', 'public');
                DB::table('sourcing_request_images')->insert(['sourcing_request_id' => $id, 'image_url' => Storage::disk('public')->url($path)]);
            }

            return ['requestNumber' => $number];
        });

        return response()->json($result + ['contactInfo' => $d['contactInfo']], 201);
    }

    public function newsletter(Request $request): JsonResponse
    {
        $d = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $email = strtolower(trim($d['email']));
        $existing = DB::table('newsletter_subscribers')->where('email', $email)->first(['id', 'status']);
        if (! $existing) {
            DB::table('newsletter_subscribers')->insert(['email' => $email, 'status' => 'subscribed', 'subscribed_at' => now(), 'source' => 'storefront']);
        } elseif ($existing->status !== 'subscribed') {
            DB::table('newsletter_subscribers')->where('id', $existing->id)->update(['status' => 'subscribed', 'subscribed_at' => now(), 'unsubscribed_at' => null]);
        }

        return response()->json(['message' => 'Thank you for subscribing to our updates!']);
    }
}
