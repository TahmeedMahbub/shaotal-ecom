# config_data: admin-managed storefront configuration

The admin app owns the `config_data` table (`id`, `key` UNIQUE, `data`, `modified_by`, `modified_at`). The storefront
only reads it, through `App\Support\StoreConfig::get('<key>')` (shapes: `StoreConfig::KEYS`).

## Rules for the admin app

- **`data` is always JSON**: a number `12`, a quoted string `"Free pickup"`, `true`/`false`, `null`, a list `["cod"]`
  or an object `{"label": "..."}`. A bare word such as `Free pickup` is invalid JSON and is rejected.
- **Every key below must have a row.** To switch something off, store its documented "off" value (usually `null`),
  never delete the row. There is no built-in fallback: a missing table, row or object field, or a value of the wrong
  type, raises `App\Support\ConfigDataException` naming the key (the page that needs it shows the error page).
- Values are read once per request (one query for all keys), so a change applies on the next page load.
- Rate changes never alter placed orders: each order stores the shipping label, online discount/deposit rates and
  gift-wrap fee it was priced with.
- `php artisan storefront:deploy-check` reports every missing or invalid key (`config_data` line).
- Text values may contain placeholders the storefront fills in: `:free_over`, `:flat`, `:adelaide` (amounts, in
  `delivery_copy`) and `:balance` (balance percentage, in `online_payment_balance_terms`).

## Keys

Shapes: *number* = JSON number ≥ 0; *percent* = number 0–100; *int* = whole number ≥ 0; *string* = non-empty
JSON string; *?x* = x or `null`; objects need every listed field (extra fields are ignored).

### Shipping and pickup

| Key | Name | Shape | Example `data` | Meaning | Used in |
|---|---|---|---|---|---|
| `shipping_fee_adelaide` | Adelaide delivery fee | number (A$) | `5.00` | Fee for SA addresses whose suburb + postcode are in the Adelaide list | `App\Support\Shipping::fee()` → checkout quote, order placement, `Delivery::text()` (`:adelaide`), shipping/terms/payments pages |
| `shipping_fee_australia` | Rest-of-Australia delivery fee | number (A$) | `12.00` | Fee for every other Australian address | `Shipping::fee()`, `Seo` product shipping rate, `Delivery::text()` (`:flat`), shipping/terms pages |
| `free_shipping_threshold` | Free delivery threshold | ?number (A$) | `299.00` | Delivery is free when the goods subtotal (before coupons) is at or above it. `null` = no free delivery | `Shipping::freeDelivery()` / `freeThreshold()`, `Delivery::text()` (`:free_over`), shipping page |
| `pickup_enabled` | Free pickup offered | bool | `true` | Shows the free pickup option at checkout | `Shipping::pickupEnabled()`, checkout, shipping/terms pages |
| `pickup_location` | Pickup point | object `{label: string, suburb: string, state: string, postcode: string, details: ?string}` | `{"label": "Free Pickup – West Croydon", "suburb": "West Croydon", "state": "SA", "postcode": "5008", "details": null}` | Label shown at checkout and stored on pickup orders; suburb/state/postcode fill the order's address columns. `details` = exact address/hours for customers; `null` = "we will contact you to confirm" | `Shipping::pickup()` / `label()` / `pickupAddress()`, checkout, order page, confirmation email, shipping/terms pages |

### Payments

| Key | Name | Shape | Example `data` | Meaning | Used in |
|---|---|---|---|---|---|
| `payment_methods` | Payment methods offered | list of codes (`card`, `cod`) | `["card", "cod"]` | Methods at checkout, in display order. `card` is still hidden until the Stripe keys are complete (`STRIPE_ENABLED` and keys in `.env`). Unknown codes are ignored | `App\Payments\PaymentMethods::enabled()`, page boot data |
| `online_payment_discount_percent` | Online payment discount | percent | `2` | Extra discount on the merchandise (after coupon) for card orders; shipping never discounted | `App\Payments\OnlinePaymentPlan::settings()` |
| `online_payment_deposit_percent` | Pre-booking deposit | percent | `30` | Share of the card-order total charged now; the balance is 100 − this | `OnlinePaymentPlan::settings()` |
| `online_payment_balance_terms` | Balance payment terms | string | `"The remaining :balance is payable later and is never charged automatically."` | How the remaining balance is collected; shown at checkout, order page, email, policy pages | `OnlinePaymentPlan::balanceTerms()` |

### Gift wrapping

| Key | Name | Shape | Example `data` | Meaning | Used in |
|---|---|---|---|---|---|
| `gift_wrap_base_fee` | Gift wrap base fee | number (A$) | `1.99` | Total fee for the first `gift_wrap_base_items` wrapped items | `App\Support\GiftWrap::rules()` / `fee()`, cart drawer preview (`SITE.giftWrap`) |
| `gift_wrap_base_items` | Items covered by base fee | int | `2` | Wrapped items included in the base fee | same |
| `gift_wrap_extra_fee` | Fee per extra wrapped item | number (A$) | `1.00` | Added for each wrapped item beyond the base items | same |

### Orders, returns and policy pages

| Key | Name | Shape | Example `data` | Meaning | Used in |
|---|---|---|---|---|---|
| `customer_cancellable_statuses` | Self-cancellation statuses | list of strings | `["pending"]` | Order statuses in which customers may cancel their own order. Only `"pending"` is supported; `[]` turns it off | `App\Models\CustomerOrders::cancellableStatuses()` |
| `returns_enabled` | Online return requests | bool | `false` | Lets customers request a return on a delivered order (also needs the return tables from `sql/proposed_changes.sql` §4) | `App\Models\CustomerReturns::enabled()`, deploy check |
| `returns_window_days` | Return request window | ?int (days) | `null` | Days after delivery a request may be sent; `null` = no limit. Must be > 0 when set | `CustomerReturns::ineligibleReason()`, deploy check |
| `return_reasons` | Return reasons | list of strings | `["Damaged or faulty", "Wrong item received", "Item not as described", "Other"]` | Reasons offered (and accepted) on the return form | `OrderController` validation, order page |
| `approved_info_pages` | Approved policy pages | list of page slugs (`contact`, `shipping`, `returns`, `payments`, `terms`, `privacy`) | `[]` | Pages whose wording is approved: indexable and in the sitemap; others show a "being finalised" notice | `PageController::approvedInfoPages()`, deploy check |
| `contact_details` | Public contact details | object `{email: ?string, phone: ?string, hours: ?string}` | `{"email": null, "phone": null, "hours": null}` | Shown on /contact and in the Organization structured data; `null` hides a line | contact page, `Seo` |

### Delivery wording

| Key | Name | Shape | Example `data` | Used in |
|---|---|---|---|---|
| `delivery_copy` | Delivery and returns wording | object, every field ?string (`null` hides that line) | see below | `App\Support\Delivery::text()` |

```json
{
  "announcement": "Delivery across Australia · Free shipping on orders of :free_over or more",
  "checkout_subtitle": "Secure checkout with delivery within Australia.",
  "checkout_promise": "Secure checkout · Delivery within Australia",
  "estimate": null,
  "dispatch_status": "In Stock",
  "timeframe_title": "Delivery Within Australia",
  "timeframe": "We currently deliver to addresses within Australia only. You can follow your order’s progress in My Orders.",
  "rates_title": "Shipping Rates",
  "rates": "Adelaide delivery :adelaide, rest of Australia :flat, free pickup from West Croydon. Free delivery on orders of :free_over or more.",
  "returns_title": "Returns",
  "returns_text": "Our returns policy is being finalised. If there is a problem with your order, please contact us."
}
```

Where each field shows: `announcement` (announcement bar, every page), `checkout_subtitle` / `checkout_promise` /
`estimate` (checkout; `estimate` = delivery time row in the success dialog), `dispatch_status` (product page stock
line), `timeframe*` / `rates*` / `returns_*` (product page delivery tabs).

## Still in code / .env (not in config_data)

Store identity and infrastructure stay in `config/storefront.php` / `.env`: brand, tagline, currency symbol, country
code, display time zone, trusted proxies, mail queues, home-image `site_settings` keys, Stripe keys and
`STRIPE_ENABLED`, the Adelaide locality list (`config/adelaide_localities.php`), and Stripe's A$0.50 minimum charge.

Test suites have no `config_data` table: they serve the same example values through `StoreConfig::fake()`
(`tests/Support/config-data.php`).
