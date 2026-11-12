<?php

namespace App\Http\Controllers;

use App\Support\ImageUpload;
use App\Support\SpamGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            // http(s) only: the link is shown as a clickable link to the customer and to staff (javascript: = XSS).
            'items.*.productLink' => ['nullable', 'string', 'max:2000', 'url:http,https'],
            'items.*.variants' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.variants.*.color' => ['nullable', 'string', 'max:80'],
            'items.*.variants.*.size' => ['nullable', 'string', 'max:80'],
            'items.*.variants.*.qty' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);
        SpamGuard::check($request, 'bulk', $d['contactInfo'], [$d['notes'] ?? '', ...array_column($d['items'], 'customSpecs')]);
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
            'productLink' => ['nullable', 'string', 'max:2000', 'url:http,https'],
            'colorSize' => ['nullable', 'string', 'max:200'],
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'details' => ['nullable', 'string', 'max:3000'],
        ] + ImageUpload::rules(), ImageUpload::messages() + ['productLink.url' => 'Please enter a full product link starting with http:// or https://.']);
        $files = $request->file('images', []);
        ImageUpload::assertTotalSize($files);
        SpamGuard::check($request, 'sourcing', $d['contactInfo'], [$d['details'] ?? '']);
        $customerId = $this->customerId();
        [$email, $phone] = $this->splitContact($d['contactInfo'], $customerId);

        // No postcode / variant columns in the schema yet, so they are kept in the details text.
        $details = trim(implode("\n", array_filter([
            'Delivery postcode: '.$d['postcode'],
            ! empty($d['colorSize']) ? 'Preferred colour/size: '.$d['colorSize'] : null,
            $d['details'] ?? null,
        ])));

        // Re-encode before the transaction (a rejected image aborts the request without a half-written row).
        $urls = array_map(fn ($f) => ImageUpload::store($f, 'sourcing-requests'), $files);

        $result = DB::transaction(function () use ($d, $urls, $customerId, $email, $phone, $details) {
            $number = $this->number('sourcing_requests', 'REQ');
            $id = DB::table('sourcing_requests')->insertGetId([
                'request_number' => $number, 'customer_id' => $customerId, 'customer_name' => $d['customerName'], 'email' => $email,
                'phone' => $phone, 'product_url' => $d['productLink'] ?? null, 'product_name' => $d['itemName'],
                'requested_quantity' => $d['qty'], 'details' => $details, 'status' => 'new',
            ]);
            foreach ($urls as $url) {
                DB::table('sourcing_request_images')->insert(['sourcing_request_id' => $id, 'image_url' => $url]);
            }

            return ['requestNumber' => $number];
        });

        return response()->json($result + ['contactInfo' => $d['contactInfo']], 201);
    }

    /** Contact form (/contact) → contact_messages, which the admin app already lists. */
    public function contact(Request $request): JsonResponse
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:'.AccountController::PHONE_REGEX],
            'subject' => ['nullable', 'string', 'max:220'],
            'orderNumber' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-]+$/'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ], ['phone.regex' => AccountController::PHONE_MESSAGE]);
        SpamGuard::check($request, 'contact', $d['email'], [$d['message']]);

        $subject = trim(implode(' · ', array_filter([$d['subject'] ?? null, ! empty($d['orderNumber']) ? 'Order '.$d['orderNumber'] : null])));
        DB::table('contact_messages')->insert([
            'name' => $d['name'], 'email' => strtolower(trim($d['email'])), 'phone' => $d['phone'] ?? null,
            'subject' => $subject !== '' ? mb_substr($subject, 0, 220) : null, 'message' => $d['message'], 'status' => 'new',
        ]);

        return response()->json(['message' => 'Thank you, your message has been sent.'], 201);
    }

    public function newsletter(Request $request): JsonResponse
    {
        $d = $request->validate(['email' => ['required', 'email', 'max:190']]);
        SpamGuard::check($request, 'newsletter', null);
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
