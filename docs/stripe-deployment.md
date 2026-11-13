# Stripe card payments: setup, testing and going live

Card payments use **Stripe Checkout** (Stripe's hosted payment page). Card details are entered on stripe.com and never reach this server, so we stay in PCI SAQ A scope. Cash on delivery (COD) is unchanged and is always offered.

Code: `app/Payments/Stripe.php` (API client and webhook signature check), `app/Payments/StripeCard.php`, `app/Models/CardPayments.php` (sessions and the webhook state machine), `app/Http/Controllers/PaymentController.php`. Tests: `tests/Feature/StripePaymentTest.php`. Design and status mapping: [payments-architecture.md](payments-architecture.md).

## How it works
1. **Place order.** The customer picks "Credit or Debit Card" and places the order.
   - The order is created like COD, with `payment_method=card`, `payment_status=pending` and `order_status=pending`.
   - A `payments` row is created with `provider=stripe`.
   - The bag is **kept**.
2. **Create the Checkout Session.** The server creates it for the server-computed `grand_total`, converted to AUD cents with no float maths.
   - The session id goes in `payments.provider_transaction_id`.
   - The idempotency key is `shaotal-payment-{payments.id}`.
   - The browser is redirected to Stripe.
3. **Pay.** The customer pays on Stripe.
   - A declined card stays on Stripe's page, so they can try another card in the same session.
   - 3-D Secure is handled by Stripe.
4. **Webhook confirms payment.** Only `POST /webhooks/stripe`, with a valid `Stripe-Signature`, marks the payment and order `paid`. It also adds an `order_timeline` note and converts the saved cart.
   - Amount, currency, order number and test/live mode must all match.
   - Repeated events are no-ops.
5. **Return pages only display.** `/checkout/payment/success` and `/checkout/payment/cancel` never write anything.
   - If the webhook hasn't arrived yet, the success page asks Stripe (server-side) and shows "Confirming". It then polls until the webhook lands.
6. **Retry.** From the cancel page or the order page, "Pay now" reuses the open session.
   - If that session expires within 5 minutes, it is closed at Stripe and a new one is created.
   - If a session is already **complete**, any new attempt is refused, so a customer can't pay twice.
7. **Abandoned payment.** When the session expires (`STRIPE_SESSION_MINUTES`, default 60), the unpaid order is cancelled automatically, which also frees its coupon.
   - Customer cancellation closes the Stripe session first.
   - A **paid** order can't be cancelled online; staff handle refunds.
8. **Stock.** Stock is never decremented or reserved (out of scope until V2).

| Stripe event | Effect |
|---|---|
| `checkout.session.completed` (`payment_status=paid`) | payment `paid` + `paid_at`, order `payment_status=paid`, timeline note, cart converted |
| `checkout.session.completed` (`unpaid`, async methods) | stays pending |
| `checkout.session.async_payment_succeeded` | as completed/paid |
| `checkout.session.async_payment_failed` | payment `failed`, order `payment_status=failed` |
| `payment_intent.payment_failed` | payment `failed`, order `payment_status=failed` (a later success still moves it to `paid`); decline code kept |
| `checkout.session.expired` | payment `cancelled`; order cancelled if unpaid, still `pending`, and no other attempt is open |
| anything else | `200`, ignored |

**Idempotency without a new table.** `sql/ecom.sql` has no `payment_events` table (the proposal is in payments-architecture.md). Each event is applied under `SELECT … FOR UPDATE` on the payment and order rows. Transitions only move forward (`paid` is final here). The last 25 processed event ids per payment are kept in `payments.response_data.events` and skipped on repeat. `response_data` holds only ids and statuses: no card or personal data.

**Edge cases that need staff.** Both are logged at `critical` and get a timeline note:
- A payment arrives for an order that was already cancelled.
- A second session is paid for the same order.

Refund either one in the Stripe Dashboard.

## Environment
```env
STRIPE_ENABLED=true          # false = COD only
STRIPE_MODE=test             # test (Sandbox) | live: must match the key prefixes
STRIPE_KEY=pk_test_...       # publishable key (not used in the browser today; kept for Stripe.js later)
STRIPE_SECRET=sk_test_...    # secret key (or restricted rk_test_ with Checkout Sessions write)
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_CURRENCY=aud
# optional: STRIPE_SESSION_MINUTES=60 (30–1440), STRIPE_API_VERSION=2024-06-20, STRIPE_WEBHOOK_TOLERANCE=300
```

**Safety switch.** The card option appears only when every key is set **and** every key matches `STRIPE_MODE`: `pk_test_` and `sk_test_` for `test`, `pk_live_` and `sk_live_` for `live`. The webhook secret must start with `whsec_`. Otherwise checkout offers only COD, and `php artisan storefront:deploy-check` reports the reason without printing any key.

**Where keys live.** Keys exist only in the server's `.env`. They are read through `config/services.php`, so `php artisan config:cache` works. The secret key is sent only in the `Authorization` header to `api.stripe.com`, and is never logged or rendered.

## Sandbox setup (one time)
1. Sign in at <https://dashboard.stripe.com>. Use a **Sandbox**: the account switcher → *Sandboxes*, or "Test mode".
2. Go to **Developers → API keys**. Copy the *Publishable key* (`pk_test_…`) into `STRIPE_KEY` and the *Secret key* (`sk_test_…`) into `STRIPE_SECRET`.
3. Set up the webhook:
   - **Local development.** Install the Stripe CLI, then run `stripe listen --api-key sk_test_… --forward-to http://127.0.0.1:8000/webhooks/stripe`. Put the printed `whsec_…` into `STRIPE_WEBHOOK_SECRET`. The secret stays the same for that CLI login and API key.
   - **Staging or a public URL.** Go to **Developers → Webhooks → Add endpoint** and enter `https://YOUR-DOMAIN/webhooks/stripe`. Select the 5 events in the table above. Copy the endpoint's *Signing secret*.
4. Run `php artisan config:clear`, or `config:cache` on servers.
5. Check that `/checkout` shows "Credit or Debit Card" above COD.

## Test cards (Sandbox only)
Use any future expiry, any CVC and any postcode.

| Card | Result |
|---|---|
| `4242 4242 4242 4242` | success |
| `4000 0000 0000 0002` | declined (`generic_decline`) |
| `4000 0000 0000 9995` | declined (insufficient funds) |
| `4000 0027 6000 3184` | 3-D Secure: approve or fail in the challenge |
| `4000 0000 0000 3220` | 3-D Secure 2 required |

**What to check:**
- Each payment sets `orders.payment_status`.
- Each payment adds a `payments` row and an `order_timeline` note.
- **Developers → Webhooks → (endpoint) → Event deliveries** shows `200`. "Resend" a delivered event: the response is `duplicate` or `already_paid` and nothing changes.

## Switching to Live
Do this only after the owner approves live payments.
1. **Activate the Stripe account.** Complete business details, bank account, identity verification and the public business name and statement descriptor (e.g. `SHAOTAL`). Set the support email and phone. Optionally turn on email receipts under **Settings → Customer emails → Successful payments**.
2. **Check Checkout settings.** Under **Settings → Checkout and Payment Links**, check the brand name, icon and colours. Under **Settings → Payment methods**, check that Cards are on. Apple Pay and Google Pay come with cards.
3. **Create the live webhook endpoint.** In **Live mode**, go to **Developers → Webhooks → Add endpoint** and enter `https://YOUR-DOMAIN/webhooks/stripe`. Select the same 5 events. Copy its **live** signing secret: it differs from the Sandbox one.
4. **Update the production `.env`:**
   ```env
   STRIPE_ENABLED=true
   STRIPE_MODE=live
   STRIPE_KEY=pk_live_...
   STRIPE_SECRET=sk_live_...        # or a restricted rk_live_ key
   STRIPE_WEBHOOK_SECRET=whsec_...  # from the LIVE endpoint
   STRIPE_CURRENCY=aud
   ```
   `APP_URL` must be the public `https://` origin: the success and cancel URLs and the webhook depend on it.
5. **Apply the config.** Run `php artisan config:cache`, then `php artisan storefront:deploy-check`. The `STRIPE_*` row must say "live mode".
6. **Make one real low-value purchase and refund it.** Confirm the webhook delivery shows `200` in the Dashboard and the order shows "Paid". Then refund it from the Dashboard.
7. **Turn live payments off if needed.** Set `STRIPE_ENABLED=false` and run `config:cache`. Checkout then offers COD only, existing orders are untouched, and webhooks still apply to sessions already open.

## Operations
- **Refunds** are done in the Stripe Dashboard by staff. The storefront doesn't process `charge.refunded`, so the admin app records refund status (see docs/admin-order-integration.md). The admin must treat `payment_status` of `provider=stripe` orders as webhook-owned and not mark them paid by hand.
- **Missed webhooks.** Stripe retries failed deliveries for up to 3 days. A `5xx` from us (e.g. the DB is down) triggers a retry; a bad signature returns `400`. Check **Developers → Webhooks** for failures.
- **Logs.** `Stripe webhook` (info: event id, type, outcome), `Stripe webhook rejected` (warning), mismatches (error/critical). There are no payloads, keys or card data in the logs.
- **Fulfilment.** Staff should ship a card order only when `payment_status=paid`.
