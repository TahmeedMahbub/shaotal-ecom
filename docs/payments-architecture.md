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
