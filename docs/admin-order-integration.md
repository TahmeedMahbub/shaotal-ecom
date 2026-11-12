# Admin app (`admin-shaotal`): required changes for order and catalog sync

_Written 2026-11-12 (Part 5A). **Not applied and not verified**: the admin codebase was not available on the machine where the storefront work was done. Everything below is a specification for the admin developer. Storefront references are to this repository._

## Why
- The storefront shows the order's status and timeline live from the shared tables (`orders`, `order_timeline`). See `App\Models\CustomerOrders` and `App\Support\OrderStatus`.
- Customers can cancel their own order while it is `pending`. The storefront does this with a row lock plus a compare-and-set: `UPDATE … WHERE order_status = 'pending'`.
- If the admin writes `order_status` blindly, a staff update made at the same moment can overwrite a customer's cancellation, or record a backward move such as `shipped → pending`. Either way the timeline contradicts the order.

## 1. One transition table (same as the storefront)
Copy `App\Support\OrderStatus::TRANSITIONS` into the admin app. Keep the two in sync.

```php
public const TRANSITIONS = [
    'pending'    => ['confirmed', 'processing', 'shipped', 'delivered', 'cancelled'],
    'confirmed'  => ['processing', 'shipped', 'delivered', 'cancelled'],
    'processing' => ['shipped', 'delivered', 'cancelled'],
    'shipped'    => ['delivered', 'returned'],
    'delivered'  => ['returned'],
    'cancelled'  => [],
    'returned'   => [],
];
```
Forward-only. `cancelled` and `returned` are final. A shipped order cannot be cancelled; it can only be returned. Show only the allowed next statuses in the admin status dropdown.

## 2. `OrderController::updateStatus`: compare-and-set, one timeline row, cancellation time
```php
public function updateStatus(Request $request, int $orderId)
{
    $data = $request->validate([
        'status' => ['required', Rule::in(array_keys(OrderStatus::TRANSITIONS))],
        'expected_status' => ['required', Rule::in(array_keys(OrderStatus::TRANSITIONS))], // status shown on the admin's screen
        'note' => ['nullable', 'string', 'max:500'],
    ]);

    return DB::transaction(function () use ($data, $orderId) {
        $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first(['id', 'order_status']);
        abort_unless($order, 404);

        // Someone (a customer cancelling, another admin) changed it since the page was loaded: do not overwrite.
        if ($order->order_status !== $data['expected_status']) {
            return back()->withErrors(['status' => "This order is now \"{$order->order_status}\". Reload and try again."]);
        }
        if ($order->order_status === $data['status']) {
            return back();   // no-op: no duplicate timeline row
        }
        abort_unless(in_array($data['status'], OrderStatus::TRANSITIONS[$order->order_status], true), 422, 'Invalid status change.');

        $changes = ['order_status' => $data['status'], 'updated_by' => auth()->id(), 'updated_at' => now()];
        if ($data['status'] === 'cancelled') {
            $changes['cancelled_at'] = now();   // the storefront shows this time
        }
        $n = DB::table('orders')->where('id', $order->id)->where('order_status', $order->order_status)->update($changes);
        abort_unless($n === 1, 409);

        DB::table('order_timeline')->insert([
            'order_id' => $order->id, 'old_status' => $order->order_status, 'new_status' => $data['status'],
            'note' => $data['note'] ?? null, 'changed_by_admin_id' => auth()->id(), 'created_at' => now(),
        ]);

        return back();
    });
}
```
Rules:
- **Never** change `stock_qty` here (V1 policy: no decrement, reservation or restoration).
- **Never** change `payment_status` here (see §3).
- The same order of operations applies to any bulk or batch status action.

## 3. Payment status is a separate action
- Recording COD cash: `payment_status = 'paid'` on `orders`. On the `payments` row, `status = 'paid'` and `paid_at = now()`.
- Do this in its own transaction and its own button, never as a side effect of `delivered`.
- Refunds (`refunded` / `partially_refunded`) are recorded only by staff. The storefront never refunds automatically.

## 4. Time zone
- The storefront now opens MySQL sessions with `time_zone = '+00:00'` (`config/database.php`, `DB_TIMEZONE`), and `APP_TIMEZONE` is UTC.
- The admin app should do the same, so `TIMESTAMP` columns (`order_timeline.created_at`) and `DATETIME` columns (`cancelled_at`, `placed_at`, `paid_at`) mean the same instant in both apps.
- Display zone: the storefront uses `STOREFRONT_DISPLAY_TIMEZONE`. The admin should use the same business zone once the owner confirms it.

## 5. Catalog cache version (makes admin edits appear within ~10 s instead of ~1–6 min)
After **any** save that changes what customers see, bump one `site_settings` row:
- products, variants, images, colours, sizes, categories, subcategories, brands, hero slides, home images, social links

```php
DB::table('site_settings')->updateOrInsert(
    ['setting_key' => 'catalog_version'],
    ['setting_value' => json_encode((string) now()->getTimestampMs()), 'updated_at' => now()]
);
```
- The storefront reads it at most every 10 s (`Storefront::version()`) and rebuilds its catalog cache when it changes.
- Without the admin change, `php artisan storefront:refresh-catalog` on the storefront server does the same by hand.

## 6. Verification once the admin app is available (manual, local DBs only)
1. Load both apps against the same local `*_test` database (never `hishabiz_ecom`).
2. Storefront: place a COD order. Admin: confirm it.
3. Storefront `/account/orders/{number}`: "Confirmed" is current, with the admin's time; there is one timeline row per change.
4. Admin: try `confirmed → pending` and `shipped → cancelled`. Both are refused.
5. Two tabs: the customer cancels a `pending` order while the admin page still shows "pending". Then the admin clicks "Confirm". The admin is refused ("now cancelled"). Storefront and admin both show "Cancelled", with one timeline row.
6. Admin: mark the order paid. The storefront order page shows "Paid"; the delivery status is unchanged.
7. Admin: edit a product price. Within ~10 s the storefront product page and checkout show the new price (product page and checkout already read live; listings follow the cache version).
8. Stock: after all of the above, every `stock_qty` touched by the order is unchanged.
