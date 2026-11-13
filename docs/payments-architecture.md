# Payments: current design and the path to Stripe

_Part 6 review (2026-11-12). **Stripe is future scope: nothing here is installed or active.** COD is the only payment method and works exactly as before (covered by `CustomerOrdersTest`, `CheckoutPlaceOrderTest`, `CouponRedemptionTest`)._

## What exists today
| Piece | Where | Role |
|---|---|---|
| `PaymentMethod` interface | `app/Payments/PaymentMethod.php` | `code()`, `label()`, `provider()`, `initialPaymentStatus()` |
| `CashOnDelivery` | `app/Payments/CashOnDelivery.php` | `cod`, no provider, starts `pending` |
| `PaymentMethods` registry | `app/Payments/PaymentMethods.php` | Only methods that are **configured and implemented** are offered or accepted. A configured `card` without a class is rejected (tested). |
| Order placement | `App\Models\Checkout::place` | One transaction creates the order, items, timeline and one `payments` row. `orders.payment_status` is always `pending` at placement; client-sent payment fields are ignored (tested). |

### Order vs payment lifecycle (already separate)
- **`orders.order_status`** (fulfilment): pending → confirmed → processing → shipped → delivered; cancelled/returned. Changed by staff, or by the customer cancelling while pending.
- **`orders.payment_status`**: pending, paid, failed, partially_refunded, refunded. Never set from the browser.
- **`payments`** (one row per attempt):
  - `provider` and `provider_transaction_id`. `UNIQUE(provider, provider_transaction_id)` is the idempotency anchor for an intent.
  - `status`: pending, authorized, paid, failed, cancelled, refunded, partially_refunded.
  - `amount`, `paid_at`, `response_data JSON`.

## Stripe status mapping (for the future implementation)
| Our state | `payments.status` | `orders.payment_status` | Stripe PaymentIntent |
|---|---|---|---|
| pending | `pending` | `pending` | `requires_payment_method`, `requires_confirmation`, `requires_action`, `processing` |
| succeeded | `paid` (+ `paid_at`) | `paid` | `succeeded` |
| failed | `failed` | `failed` | `payment_failed` event (intent back to `requires_payment_method`) |
| cancelled | `cancelled` | `pending` → order cancelled by staff/expiry rule (H3) | `canceled` |
| refunded | `refunded` / `partially_refunded` | same | `charge.refunded` (staff-initiated only) |

## How Stripe should plug in (design, not code)
1. **`StripeCard implements PaymentMethod`**
   - `code()` returns `card`, `provider()` returns `stripe`, `initialPaymentStatus()` returns `pending`.
   - Add a small extra interface, e.g. `OnlinePaymentMethod::startPayment(order): array`, returning the PaymentIntent `client_secret`.
   - COD does not implement it, so its path is unchanged.
2. **Placement**
   - After `Checkout::place` commits, create the PaymentIntent server-side for the **server-computed** `grand_total` in AUD cents.
   - Use the Stripe idempotency key `order:<order_number>`.
   - Store the intent id in `payments.provider_transaction_id`.
   - Return only the `client_secret` to the browser.
3. **Card entry**
   - Use Stripe Elements / Payment Element (Stripe.js). Card data goes from the browser to Stripe and never touches this server, which keeps the site in PCI SAQ A scope.
   - **Never store card numbers, CVC or full card details.**
4. **Webhook `POST /webhooks/stripe`**
   - Outside the CSRF middleware; sessionless, like the public routes in `routes/web.php`.
   - Verify `Stripe-Signature` with `STRIPE_WEBHOOK_SECRET` (timestamp tolerance ~5 minutes). Reject anything that does not verify.
   - **Idempotent:** record each event id once and skip repeats. That needs a small table (proposal below, not in `sql/proposed_changes.sql` yet because Stripe is future scope).
   - In one transaction, lock the `payments` row by `provider_transaction_id`, apply the mapping above, and update `orders.payment_status`.
   - Out-of-order events: never move `paid` back to `pending`/`failed`.
   - **The browser's return from Stripe never marks anything paid.** Only the verified webhook does.
5. **Secrets**
   - `STRIPE_KEY` (publishable), `STRIPE_SECRET` and `STRIPE_WEBHOOK_SECRET` live in the server `.env` only.
   - Add them to `storefront:deploy-check` (fail when `card` is enabled and a secret is missing). Never log the payloads' secret fields.
6. **CSP** (`App\Http\Middleware\SecurityHeaders`, checkout page only):
   - `script-src https://js.stripe.com`
   - `frame-src https://js.stripe.com https://hooks.stripe.com`
   - `connect-src https://api.stripe.com`
   - The `payment=` Permissions-Policy is already left open for this.
7. **Owner decisions before Stripe (plan H2/H3):**
   - Do unpaid card orders appear to staff before payment succeeds?
   - How long does an unpaid intent stay valid, and what cancels it?
   - Are refunds issued from the Stripe dashboard or from the admin app?

## Part 8 review (2026-11-12): Stripe readiness
_Review only. Nothing was installed, configured or switched on. COD and stock behaviour are unchanged._

**Confirmed against the code and `sql/ecom.sql`:**
- **Method allowlist:** `PaymentMethods::enabled()` = configured ∩ implemented, which today is `cod` only. Checkout validates `paymentMethod` with `Rule::in(enabled())`, and the page renders only enabled options. In the browser, the checkout offered exactly `cod` (`tests/browser/journey.mjs`).
- **Placement:**
  - `Checkout::place` writes the order, items, timeline and one `payments` row in one transaction.
  - `orders.payment_status` is always `pending`.
  - The payment row takes `provider()` and `initialPaymentStatus()` from the method, with no `paid_at`.
  - Checked in the Part 8 journey: a COD order of A$97.00 → `payments` row `provider NULL, cod, 97.00, pending, paid_at NULL`. 0 orders in the database are `paid`.
- **Schema anchors exist:**
  - `UNIQUE(provider, provider_transaction_id)` and `INDEX(order_id, status)` on `payments`;
  - the `payments.status` enum includes `authorized` and `cancelled`;
  - `orders.payment_status` has no `cancelled` (the mapping above already keeps it `pending`).
- **Headers:**
  - `Permissions-Policy` doesn't restrict `payment`;
  - `Cross-Origin-Opener-Policy: same-origin-allow-popups` allows 3-D Secure pop-ups;
  - the CSP has no Stripe hosts yet (correct while Stripe is off).

**Gaps to design before Stripe is built (none affects COD today):**
| # | Gap | Proposed rule (owner/dev to confirm) |
|---|---|---|
| P1 | Customers can cancel any `pending` order themselves (`CustomerOrders::cancel`). That check ignores `payment_status`, so a card order that is already **paid** could be cancelled with no refund. | Allow self-cancel only while `payment_status` is `pending` or `failed`. A paid order needs staff (refund) or an automatic refund. Add a test when Stripe lands. |
| P2 | An order can total **A$0.00**: a coupon can cover the whole subtotal, with free shipping at ≥ A$299. Stripe rejects charges below A$0.50. | Skip the payment step for a zero total (mark `paid` server-side with provider `none`), or block card for totals under A$0.50. |
| P3 | Totals are PHP floats (`round(…, 2)`). | Convert to cents once, from the stored `DECIMAL` `grand_total` string (e.g. `(int) bcmul($total, '100')`). Never float × 100. Webhook amounts compare in cents. |
| P4 | `site_settings.currency_code` is seeded `NULL`. | Use a fixed `aud` from config for Stripe, the same as `Seo` (`AUD`). Don't read the currency from `site_settings` for charging. |
| P5 | The customer's server cart is converted (cleared) at placement, before any card payment. | Keep the cart until payment succeeds, or rebuild it from the order on a failed or abandoned payment (H3). |
| P6 | Placement commits before the PaymentIntent is created. If Stripe is unreachable, a pending card order exists without an intent. | Create the intent right after commit with the idempotency key `order:<order_number>`. A retry endpoint re-uses the same key. Unpaid orders expire per H3. |
| P7 | A webhook route must avoid session, CSRF and the per-route throttles. | Register it beside the public sessionless routes in `routes/web.php` (no `web` session middleware). `RedirectTrailingSlash` only touches `GET`/`HEAD`, so a `POST` webhook is unaffected. |
| P8 | The admin app sets order status. It must not mark card orders `paid` by hand. | Admin doc: `payment_status` for provider `stripe` is webhook-owned and read-only in the admin. |

### Proposed table for webhook idempotency (future)
```sql
CREATE TABLE payment_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 provider VARCHAR(40) NOT NULL, event_id VARCHAR(190) NOT NULL, event_type VARCHAR(120) NOT NULL,
 payment_id BIGINT UNSIGNED NULL, processed_at DATETIME NOT NULL,
 UNIQUE(provider, event_id),
 FOREIGN KEY(payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
Store only the event id, type and our own references, not the full payload, which contains personal data.
