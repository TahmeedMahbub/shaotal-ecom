# Admin app (`admin-shaotal`): required changes for order and catalog sync

_Written 2026-11-12 (Part 5A). **Not applied and not verified**: the admin codebase was not available on the machine where the storefront work was done. Everything below is a specification for the admin developer. Storefront references are to this repository._

_Part 6 additions (2026-11-12): §7 return-request review (**proposed**: the tables are not in the shared database and the feature is off) and §8 contact messages and sourcing photos. **This integration remains a release blocker**: none of it has been built or tested against the admin app._

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

## 1b. Customer-facing delivery stages (nine, shown in this order)
Order Placed, Order Confirmed, Processing, Ready for Dispatch, Dispatched, In Transit, Arrived at Delivery Hub, Out for Delivery, Delivered.
`orders.order_status` has no enum value for four of them, and the schema is not changed. Map as follows:

| Customer stage | `orders.order_status` | extra `order_timeline.new_status` row |
|---|---|---|
| Order Placed / Order Confirmed / Processing | `pending` / `confirmed` / `processing` | none |
| Ready for Dispatch | `processing` | `ready_for_dispatch` |
| Dispatched | `shipped` | none |
| In Transit | `shipped` | `in_transit` |
| Arrived at Delivery Hub | `shipped` | `arrived_at_hub` |
| Out for Delivery | `shipped` | `out_for_delivery` |
| Delivered | `delivered` | none |

For a finer stage the admin inserts one `order_timeline` row (`old_status` = the current `order_status`, `new_status` = the key above, `created_at` = when it happened) and leaves `order_status` unchanged. The storefront shows the furthest recorded stage that belongs to the current `order_status`. Without such rows the storefront shows the five enum-backed stages; nothing breaks and existing orders keep working. Payment statuses are unaffected.

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
- Display zone: the owner confirmed **Australia/Sydney** (Part 6). The storefront shows customer dates in that zone with an AEST/AEDT label (`STOREFRONT_DISPLAY_TIMEZONE`, default `Australia/Sydney`; `App\Support\DisplayTime`). The admin should display the same zone, so staff and customers see the same time for each event, while storing UTC.

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

## 7. Return-request review (proposed; NOT active)
**Status:**
- The storefront code exists, is tested, and is **off** (`config_data` `returns_enabled` = `false`).
- It stays off until:
  - (a) the owner approves a returns policy;
  - (b) the DB owner applies `sql/proposed_changes.sql` §4 to the shared database;
  - (c) the admin screens below exist.
- The storefront also hides the form automatically while the tables are missing.
- No eligibility rules beyond the ones below have been decided. **Do not add any in either app without the owner's written policy.**

**What the storefront does (when on)** (`App\Models\CustomerReturns`, `POST /account/orders/{number}/return`):
- A signed-in customer can request a return for **their own** order whose `order_status` is `delivered`, **once** per order (`UNIQUE order_id`).
- The customer picks one of the reasons in `config_data` key `return_reasons` and can add up to 2,000 characters of details.
- If `config_data` `returns_window_days` is not null, the request must arrive within that many days of the `order_timeline` row that moved the order to `delivered`. Without that row it is refused. The window is not set (owner decision pending).
- It inserts one `order_return_requests` row (`status='requested'`) and one `order_return_request_events` row (`old_status NULL → requested`, `changed_by_admin_id NULL`), in one transaction.
- It changes **nothing else**: not `orders.order_status`, `payment_status`, `stock_qty`, or any payment row.
- The customer's order page then shows the request's status label:

  | `status` | Label shown to the customer |
  |---|---|
  | `requested` | Return Requested |
  | `approved` | Return Approved |
  | `rejected` | Return Not Approved |
  | `completed` | Return Completed |

**What the admin app needs (proposed):**
1. **List:** a "Return requests" page, newest first, filterable by `status` (index `status, created_at`). Show the order number, customer, reason, details, and the request time in Australia/Sydney.
2. **Review:** from the request (or its order page), staff can move the status only along these transitions (same table as `CustomerReturns::canTransition`):

   | From | Allowed next status |
   |---|---|
   | `requested` | `approved` or `rejected` |
   | `approved` | `completed` |
   | `rejected` | none (final) |
   | `completed` | none (final) |

3. **Write each change as a compare-and-set:**
   - `UPDATE order_return_requests SET status=?, status_changed_at=NOW(), changed_by_admin_id=?, admin_note=? WHERE id=? AND status=?`
   - If it affects 0 rows, refuse with "This request was changed by someone else."
   - Otherwise insert one `order_return_request_events` row (old → new, `note`, `changed_by_admin_id`), in the same transaction.
4. **Keep refunds and stock separate and manual:**
   - Approving or completing a return must not change `orders.payment_status`, refund anything or touch `stock_qty`.
   - Stock management is V2, and online refunds wait for Stripe (future).
   - Whether the order itself moves to `returned` is a staff decision made with the existing order-status action (§1/§2), not a side effect.
5. **Communicate:** today the customer only sees the label on their order page. There are no emails, because none were approved. Any customer message (email, `admin_note` shown to the customer) needs approved wording first.

**Verification (manual, local `*_test` DB only, after (b) and (c)):**
1. With `config_data` `returns_enabled` = `true` on a local storefront, request a return on a delivered test order.
2. Admin: the request appears. Approve it, then complete it. The storefront shows each label, and there are three event rows.
3. A second request on the same order is refused.
4. Admin: try `rejected → approved`. It is refused.
5. The order's payment status and the product stock are unchanged.

## 8. Other storefront inputs staff must see (Part 6)
- **Contact messages:**
  - `/contact` writes to `contact_messages` (existing table: `status` `new/in_progress/resolved/spam`, `admin_notes`).
  - The subject may end in "· Order INV-…" when the customer gave an order number.
  - The admin app needs a list with those statuses.
  - The storefront has no inbox and sends no email.
- **Sourcing-request photos:**
  - `sourcing_request_images.image_url` holds a full URL under `{APP_URL}/storage/sourcing-requests/<uuid>.<jpg|png|webp>`.
  - Each file is a re-encoded copy of the customer's upload (metadata stripped, random name, at most 5 per request).
  - They load only if `php artisan storage:link` was run on the storefront server (deployment checklist §1 step 6).
  - If the admin app runs on a different server, it must display the URL and not expect the file on its own disk.
- **Spam:**
  - Public forms have a hidden honeypot field, a limit of 3 links per message, a limit of 5 submissions per contact per hour, and per-IP rate limits.
  - Spam that gets through can be marked `spam` (contact messages) or closed by staff. Nothing is auto-deleted.

## 9. Gift wrapping on orders (dedicated columns, Admin contract)

The storefront writes gift data into the columns the Admin added (`sql/proposed_changes.sql` GIFT WRAPPING block there; the Admin only reads them):

- `orders.gift_wrap_requested` (0/1), `gift_wrap_items_count` (= `SUM(order_items.gift_wrap_qty)`), `gift_wrap_fee` (computed once at checkout, stored), `gift_message` (VARCHAR 500, max 300 accepted; separate from `customer_note`).
- `order_items.gift_wrap_qty`: wrapped units of that line (0..quantity). No `GIFT-WRAP` order line is written any more.
- **Totals:** `orders.subtotal` is goods only; `grand_total = subtotal - discount_total + shipping_total + tax_total + gift_wrap_fee` (the fee is in `grand_total` exactly once). Online (Stripe) discount/deposit are computed on goods + fee − coupon, as before.
- **Pricing rule** (`config_data` `gift_wrap_base_fee` / `gift_wrap_base_items` / `gift_wrap_extra_fee`): A$1.99 for 1-2 wrapped items, +A$1.00 per further item; never counts toward free shipping or coupon eligibility.
- **Historical orders** (before the columns): fee as a `GIFT-WRAP` order line inside `subtotal`, items + message as a block in `customer_note` (marker `--- GIFT WRAPPING ---`), gift columns at their defaults. The storefront still displays them correctly.
- **Order status:** `orders.order_status` has 11 values (pending, confirmed, processing, ready_for_dispatch, shipped, in_transit, arrived_at_hub, out_for_delivery, delivered, cancelled, returned); the storefront labels/timeline use the same table as the Admin's `OrderStatus`. Payment status is independent. Older orders that recorded the four finer stages only in `order_timeline` are still refined from there.


## Online payment = pre-booking (deposit) — what the admin app sees

No schema change. For `payment_method = 'card'` orders placed through the storefront:

- `orders.grand_total` is the **discounted** total (merchandise − coupon − online discount + shipping). `orders.discount_total` = coupon discount + online-payment discount (so `subtotal − discount_total + shipping_total + tax_total = grand_total` still holds). The online part is itemised in `payments.response_data` → `plan` (`discountPercent`, `depositPercent`, `discount`, `deposit`, `balance`, `total`, rates frozen at placement).
- The Stripe payment row has `amount` = the **deposit** (**30%** of `grand_total` since 2026-11-14; older orders keep the rate stored in their own `plan.depositPercent`), `provider = 'stripe'`, `status = 'paid'` after the verified webhook. **`orders.payment_status` stays `pending`**: the shared enum has no partial value, so a deposit is never shown as paid. Amount received = `SUM(payments.amount WHERE status = 'paid')`; balance due = `grand_total − received`. The order is set to `paid` only when received ≥ `grand_total` (e.g. when the balance is recorded as a paid Stripe payment row for the rest).
- Admin should therefore show a card order with `payment_status = 'pending'` and a paid payment row as **Pre-booked / deposit paid, balance due**, and must not mark it paid until the balance is collected. Collecting the balance (and any refund of a deposit) is a staff action; the storefront does not cancel an order online once a deposit is received.
- COD orders are unchanged (one `pending` payment row for the full total; admin records the cash).
- A proposed optional `partially_paid` status / `amount_paid` column is in `sql/proposed_changes.sql` §7. No Admin code was changed in this work.

## Shipping zones, pickup and the 30% deposit (2026-11-14) — what the admin app must show

Owner decisions: Adelaide delivery A$5, rest of Australia A$12, free pickup from West Croydon (A$0); every card order pays a
30% deposit now and 70% later (2% online discount kept); COD unchanged (full amount on delivery or collection). Delivery
stays free at or above A$299 (`config_data` `free_shipping_threshold`, existing rule). No schema change was needed.

Where each figure lives (all written by the storefront at placement, never recalculated):

| Admin field | Source |
|---|---|
| Total order amount | `orders.grand_total` |
| Shipping fee | `orders.shipping_total` |
| Shipping method | `orders.fulfillment_type` (`delivery` / `pickup`) + `payments.response_data->shipping` on the order's FIRST payment row: `{fulfillment, method: adelaide\|australia\|pickup, label, fee}`. Also a timeline row `Delivery method: Adelaide delivery (A$5.00)`. Orders placed before this change have no snapshot (show "Delivery"). |
| Pickup contact | pickup orders store the collector in `shipping_recipient_name` / `shipping_phone`, and the pickup point (`Free Pickup – West Croydon`, West Croydon SA 5008) in the address columns. |
| Deposit required (30%) | card orders: `payments.response_data->plan.deposit` (+ `depositPercent`, `balancePercent`, `balance`); COD: none. |
| Deposit paid | `SUM(payments.amount) WHERE order_id = ? AND status = 'paid'` |
| Remaining balance | `grand_total - deposit paid` |
| Payment status | `orders.payment_status` stays `pending` while only the deposit is paid (the shared enum has no partial value). Show **"Partially paid (deposit received)"** when `payment_status = 'pending'` AND deposit paid > 0. It becomes `paid` only when the paid rows reach `grand_total`. |

Example query for an order list:

```sql
SELECT o.order_number, o.grand_total, o.shipping_total, o.fulfillment_type,
       JSON_UNQUOTE(JSON_EXTRACT(fp.response_data, '$.shipping.label')) AS shipping_method,
       CAST(JSON_EXTRACT(fp.response_data, '$.plan.deposit') AS DECIMAL(12,2)) AS deposit_required,
       COALESCE(pp.paid, 0) AS deposit_paid, o.grand_total - COALESCE(pp.paid, 0) AS balance_due, o.payment_status
FROM orders o
JOIN payments fp ON fp.id = (SELECT MIN(id) FROM payments WHERE order_id = o.id)
LEFT JOIN (SELECT order_id, SUM(amount) AS paid FROM payments WHERE status = 'paid' GROUP BY order_id) pp ON pp.order_id = o.id;
```

Rules for staff actions: record the balance as a **new** `payments` row (never edit or overwrite the deposit row); a
deposit is never refunded or forfeited automatically (no policy yet). A first-class `partially_paid` status and
`shipping_method` / `amount_paid` columns are proposed in `sql/proposed_changes.sql` §10 (not executed).
