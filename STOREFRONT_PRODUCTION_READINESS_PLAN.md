# Shaotal Storefront — Production Readiness Plan

_Created 2026-11-11 (Part 1 of 8), updated after Part 2 (security hardening and checkout reliability). Living checklist: update **Status** as items close._

Status values:
- **Fixed**: code plus regression test, verified.
- **Fixed (browser)**: also verified in a real browser.
- **Open**: not started.
- **Decision**: needs an owner/business decision first.
- **Deploy**: server or env config, not code.
- **Blocked**: needs credentials or access.

Scope: `shaotal` storefront (Laravel 13.36, PHP 8.4). The admin app (`admin-shaotal`) shares MySQL `hishabiz_ecom`; it is read-only reference here. Source audit: `STOREFRONT_PAGE_AND_FLOW_AUDIT.md` (checked against the code in Part 1).

**Data safety (Parts 1–2):** no query was run against the remote `hishabiz_ecom` database. `shaotal_local` was only read (a schema-only `mysqldump --no-data` in Part 1); none of its data was changed. Tests and browser checks used only the local isolated DBs `shaotal_storefront_test` and `shaotal_ci_test`.

---

## 0. Test environment

### Baseline (before Part 1)
| Check | Result |
|---|---|
| `php artisan test` / `composer test` | Suite could not load: `SizeGuideUnitTest::seed()` fatal, exit 255, no output |
| PHP | `pdo_sqlite` **not enabled** in Laragon `php.ini` (the DLL exists) |
| Per file | Unit 8/8 · `ExampleTest` fails on SQLite · `GoogleLoginTest` 9 skipped (needs MySQL) |
| JS | `size-guide-units.test.js` 9/9 |
| Pint | Fails on CRLF line endings (the repo stores CRLF blobs; Pint wants LF) plus 2 style rules in `CheckoutController`/`PageController` |
| Static analysis | None configured |

### How to run (after Part 2)
| Command | What it does |
|---|---|
| `composer test` | **Full suite on the local MySQL test DB** (`phpunit.mysql.xml`). Without `pdo_sqlite`, 8 SQLite-only size-guide tests are skipped. Verified as a developer would run it: 115 passed, 8 skipped, exit 0. |
| `composer test:sqlite` | No schema needed. DB tests are skipped before any connection is opened; the rest run. Exit 0. |
| `php -d extension=pdo_sqlite vendor/bin/phpunit -c phpunit.mysql.xml` | Everything: **123/123**. |
| `node --test tests/js/api-client.test.js tests/js/size-guide-units.test.js` | JS: **17/17**. List the files explicitly; the directory form fails on Windows. |

**Optional (Laragon) SQLite support:** in `C:\laragon\bin\php\php-8-4-5\php.ini`, uncomment `extension=pdo_sqlite`. This is not required: the project relies on MySQL behaviour (schema, row locks).

### Database isolation (verified)
1. **`phpunit.mysql.xml` forces the local test DB.** Host `127.0.0.1` and DB `shaotal_storefront_test` are forced via both `<env force>` and `<server force>`. Part 2 found that a forced `<env>` alone loses to a shell variable, because Laravel reads `$_SERVER` first. Verified: a run with hostile `DB_HOST`, `DB_DATABASE` and `DB_URL` shell variables still used the local test DB.
2. **`tests/TestCase.php` guard runs before any connection.** The guard was moved into `setUpTraits()`, so it runs before `DatabaseTransactions` opens a connection. It refuses any MySQL database that is not local or not named `*_test`. Verified against a fake remote host and against `shaotal_local`: both were refused.
3. **The multi-process test child never reads `.env` DB settings.** It receives the parent's exact connection config and repeats the guard. On Windows an empty `DB_PASSWORD=` cannot be passed to a child process, so it fell back to `.env`. That attempt went only to `127.0.0.1` and was refused.
4. **Tests use an isolated file-cache directory.** The `DatabaseTransactions` tests roll back. `CouponConcurrencyTest` commits rows and deletes exactly those rows in `tearDown`. Verified: no residue, even after deliberately failing runs.

### Test databases
- **`shaotal_storefront_test`**: schema cloned (`--no-data`) from `shaotal_local`, plus `sql/local_test_seed.sql`.
- **`shaotal_ci_test`** (new in Part 2): built exactly as CI builds it: `sql/ecom.sql` → `sql/google_login.sql` → `tests/Support/ci-schema-drift.sql` → `sql/local_test_seed.sql`. The full suite passed **123/123** against it. That validates the CI recipe.
- **Recreate:** create the DB (utf8mb4_unicode_ci) and load the four files above in order.

### CI
`.github/workflows/tests.yml`:
- Spins up a MySQL 8 service and builds the test DB from `sql/`.
- Runs `composer test`, then the JS tests.
- Runs Pint on changed files for pull requests.

**It has not been executed on GitHub** (nothing was pushed). The recipe and both composer scripts were validated locally.

---

## 1. Results

| Run | Part 1 | Part 2 |
|---|---|---|
| Full PHP suite, local MySQL | 52/52 | **123/123** (692 assertions) |
| Default `phpunit.xml` (SQLite) | 16 pass / 36 skip / 0 fail | 47 pass / 76 skip / 0 fail |
| JS | 9/9 | **17/17** |
| Real browser (headless Chrome over CDP, local test DB) | not done | **48/48** checks (§6) |

Mutation checks (fix removed, test fails; fix restored, test passes):
- Part 1: the customer middleware and the duplicate-order guard.
- Part 2: coupon locking. Without it, two simultaneous processes placed **2 orders** for a 1-use coupon, and the lock-wait test failed.

---

## 2. Issues by workstream

### A. Security and authorization
| ID | Sev | Issue → fix | Verification | Status |
|---|---|---|---|---|
| A1 | C | Blocked or deleted customers kept access. Fixed with the global `ValidateCustomerSession` plus the `customer` route middleware. | `CustomerAccessTest`, `SessionRevocationTest`, browser | **Fixed** |
| A2 | H | No route-level guard. Fixed: `Route::middleware('customer')` group. | 12-endpoint 401 test | **Fixed** |
| A3 | M | Customer name/email were HTML-injected in the header, menu and toasts. Fixed: `escHtml()`. | Browser: hostile last name renders as text | **Fixed (browser)** |
| A4 | H | No security headers. Added `SecurityHeaders` (global): CSP, `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, COOP, and HSTS only over HTTPS in production. CSP exceptions are in §4. | `ErrorPagesAndHeadersTest`; browser: 0 CSP violations on 10 pages incl. bulk-order CDNs and checkout | **Fixed (browser)**. Follow-up A12. |
| A5 | M | Admin rich text was rendered as raw HTML. Fixed: `App\Support\SafeHtml` allowlist sanitizer (DOM-based, no new package), applied to `desc`/`fullDesc` when the catalog is built. | `SafeHtmlTest` (16 payloads, formatting, idempotency), `CatalogOutputSafetyTest`; browser: `<strong>` kept, script/handler/`javascript:` gone | **Fixed (browser)** |
| A6 | M | Catalog text in `innerHTML` (§5 lists all sinks). The browser run found one sink missed by review: the **footer Shop column is built from DB categories**. | Browser: `__xss=0` on every page and in cart/search/wishlist | **Fixed (browser)** |
| A7 | M | Guest sourcing uploads/spam: 5×5 MB per request, 10/min/IP. | — | Open |
| A8 | L | Dead guest-checkout code (inline address). | — | Decision |
| A9 | H | `APP_DEBUG=true`/`LOG_LEVEL=debug` in `.env`; dev points at the production DB. | — | Deploy |
| A10 | M | `/bulk-order` loads jQuery and Select2 from CDNs **without SRI**. | — | Open |
| A11 | M | Admin URLs (hero buttons, mega-menu, social, footer) could be `javascript:`. Fixed: `safeUrl()` (URL-parser allowlist). | Browser: hero `javascript:` CTA renders `#` | **Fixed (browser)** |
| A12 | M | CSP still needs `script-src 'unsafe-inline'`, because the design's JS emits inline handlers. To harden: move handlers to `addEventListener`, then use nonces and drop `'unsafe-inline'`. | — | Open |

### B. Authentication and session management
| ID | Sev | Issue → fix | Verification | Status |
|---|---|---|---|---|
| B1 | H | A password reset did not end other sessions. Fixed with **session fingerprints** (§3). | `SessionRevocationTest` (7); browser: password changed elsewhere → next action ends the session | **Fixed (browser)** |
| B2 | M | Login was throttled per IP only. Fixed: failure-based limits per email+IP (5), per email (20) and per IP (30), all over 15 min, plus the existing route throttle. | `AuthSecurityTest` (IP rotation, spraying, no enumeration, expiry, success clears) | **Fixed** |
| B3 | M | Password minimum was 6. Fixed: 8 for register and reset (server + modal). Existing shorter passwords still sign in. | Tests + browser | **Fixed (browser)** |
| B4 | H | Session/cache config for production: driver, `SESSION_SECURE_COOKIE`, and a cache store with a table/Redis (rate limiter, checkout lock). | — | Deploy |
| B5 | H | The front end ignored 401/419. Fixed: one client (`api-client.js`) handles 401 (sign-in modal, return to the page, once per page), 419 (fresh token + one retry), and 403/429/5xx (safe messages). `request.js` now uses it. | `api-client.test.js` (8); browser: 401 and 419 flows | **Fixed (browser)** |
| B6 | L | Register race returns 500; no email verification. | — | Open |
| B7 | L | Forgot-password could flood one inbox. Fixed: 3 per address per 15 min, same reply. | `AuthSecurityTest` | **Fixed** |
| B8 | info | Laravel 13 CSRF also accepts same-origin requests by `Sec-Fetch-Site`, so real 419s are rare in browsers. The browser test injected one to verify the retry path. | Browser (Fetch interception) | Noted |

### C. Checkout and order integrity
| ID | Sev | Issue → fix | Verification | Status |
|---|---|---|---|---|
| C1 | C | Duplicate orders. Fixed in Part 1: lock + 60 s replay. | Tests; browser: double click → exactly 1 order | **Fixed (browser)** |
| C2 | H | Coupon limit race. Fixed: `Checkout::claimCoupon()` locks the coupon row (`FOR UPDATE`) first in the order transaction, then re-checks status, dates, global limit and per-customer limit. Failed or rolled-back orders and replayed duplicates use nothing. Only one row is locked, always first, so no lock cycle. | `CouponRedemptionTest` (7); `CouponConcurrencyTest` (2, **separate PHP processes**) | **Fixed** |
| C3 | H | Stock is never decremented. Investigation in §7. | — | **Decision** |
| C4 | M | Incomplete saved addresses. Fixed: server-side completeness check (recipient, street, suburb, valid state, 4-digit postcode, phone with 8–15 digits) for saved and inline addresses; phone rule on the address endpoints. | `CheckoutAddressTest` (11); browser: 422 with a clear message | **Fixed (browser)** |
| C9 | H | **New finding:** every Profile save (even a phone-only change) copied the displayed `street, suburb, state` back into `street_address`. The text grew on each save and corrupted delivery labels. Fixed: the address is only written when actually changed. When changed, suburb/state are cleared (free text cannot be split) and checkout asks for them. Existing rows already corrupted this way are **not** auto-repaired. | `CheckoutAddressTest` | **Fixed** |
| C5 | M | The checkout lock and rate limiter use single-server cache stores. | — | Deploy |
| C6 | M | Price change between quote and place is silent. | — | Open |
| C7 | L | 6-digit random order numbers. | — | Open |
| C8 | M | No order-confirmation email (needs SMTP). | — | Blocked |
| C10 | L | A product without an image requests `/null` (placeholder shown via `onerror`). Pre-existing; seen in the browser run. | — | Open |

### D. Customer account and journey
| ID | Sev | Item | Status |
|---|---|---|---|
| D1 | H | No order detail/tracking. "View My Orders" opens the Profile tab. | Open |
| D2 | M | Address book only in checkout; Profile has a single free-text field (see C9). | Open |
| D3 | L | `/account` shell is served to guests (data is protected server-side). | Decision |
| D4 | L | Orphan `welcome.blade.php`. | Open |
| D5 | L | Homepage "Our Story" anchor. | Open |

### E. Navigation and pages
| ID | Sev | Item | Status |
|---|---|---|---|
| E1 | H | No Contact, Shipping, Returns, Terms or Privacy pages. Content is needed from the owner. | Blocked (content) |
| E2 | H | Error pages. Added branded 403/404/419/429/500/503 on a standalone layout (no DB/session access, so they work when MySQL is down), `noindex`, links back to shopping. AJAX errors stay JSON. 500s never show exception text when `APP_DEBUG=false`. Verified by `ErrorPagesAndHeadersTest` (11) and screenshots at 375 px and 1280 px (no horizontal scroll). | **Fixed (browser)** |
| E3 | M | Footer duplicates/placeholders. | Open |

### F. Performance
F1 catalog in every page (H), F2 JS-gated LCP (H), F3 no minification (M), F4 server caches/OPcache (Deploy), F6 unmeasured pages (M): all Open, unchanged.

Part 2 cost:
- One primary-key lookup per request **only when a customer is signed in** (`ValidateCustomerSession`).
- One extra 4 KB script (`api-client.js`, cache-busted, long-cacheable).
- Description sanitising runs once per catalog-cache rebuild, not per request.

### G. SEO
G1–G7 unchanged: all Open, plus G7 Decision. Error pages carry `noindex`.

### H. Stripe-ready payments
Unchanged (H1–H5).
- Before Stripe, the CSP will need `https://js.stripe.com` in `script-src`/`frame-src` and `https://api.stripe.com` in `connect-src`.
- The `payment=` Permissions-Policy is left unrestricted for Stripe.

### I. Testing and readiness
| ID | Sev | Item | Status |
|---|---|---|---|
| I1 | C | Suite fatal error | **Fixed** (Part 1) |
| I2 | C | Remote-DB guard + cache isolation; Part 2 made it run before any connection and forced `$_SERVER` | **Fixed** |
| I3 | H | `pdo_sqlite` missing. `composer test` now runs the MySQL suite and SQLite is optional (documented). | **Fixed** |
| I4 | H | Coverage: auth, session, address, coupon, headers, errors and XSS added. Still missing: quote edge cases, requests/newsletter, profile, cart/wishlist sync, page smoke tests for every route. | Open (partial) |
| I5 | M | Browser checks were run ad hoc (CDP script, not committed). Turn them into a committed Playwright suite. | Open |
| I6 | M | CI workflow added | **Fixed (not yet run on GitHub)** |
| I7 | L | Pint `line_ending`: the repo stores CRLF blobs. All Part 2 PHP files pass Pint when checked as LF copies. Decide on a `.gitattributes` / one-off normalisation commit. | Decision |
| I8 | L | Schema drift: `subcategories.section` and `customer_social_accounts` are missing from `sql/ecom.sql`. CI uses `tests/Support/ci-schema-drift.sql`. | Decision |
| I9 | M | No error tracking or log rotation | Open |
| I10 | info | `php artisan test --configuration=X` exits 1 even when green, so the scripts use `-c X`. `laravel/pao` (dev) switches test output to JSON when an AI agent runs it. | Noted |

---

## 3. Password and session behaviour

- **Sign-in state:** a session flag (`customer_id`) plus `customer_pw` = HMAC-SHA256 (keyed with `APP_KEY`) of the account's `password_hash`. The password hash itself is never stored in the session.
- **Every request with a signed-in customer:** one PK lookup. The session is ended (customer keys removed, session id rotated, CSRF token kept) when:
  - the account is deleted, or
  - its status is not `active`, or
  - its password hash no longer matches the fingerprint.

  Pages then render as a guest. Customer routes return 401 JSON (or redirect `GET /checkout` to `/?signin=/checkout`).
- **Password reset:** the new hash invalidates **every** session signed in before it, on any device and with any session driver (no session table needed). The resetting browser gets a new session id and is signed out if it was signed in as that customer. **Other customers are unaffected** (tested).
- **Google link that clears an unverified password** also ends the sessions that were created with that password.
- **Deploy-safe:** sessions from before this release (no fingerprint) adopt the current one on their next request. Nobody is signed out by the deploy, and a later reset still revokes them (tested).
- **Policy:** new passwords must be at least 8 characters, with no composition rules, enforced on register and reset (server + modal). Sign-in does not re-validate length.
- **Login limits** (failures only, 15-minute windows): email+IP 5, email 20, IP 30, plus the route `throttle:10,1`. The 429 reply is identical for existing and unknown emails and includes `Retry-After`. Lockouts expire, and "Forgot password" still works during one. Forgot-password: 3 emails per address per 15 min, same reply every time.
- **Production requirement:** rate limits and the checkout lock use Laravel's cache. Use `CACHE_STORE=file` (single server) or `redis`. **Do not use `database`**: `sql/ecom.sql` has no cache tables.

## 4. Security headers and CSP exceptions

Headers sent on every response, error pages included:
```
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net;
  style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' data: https://fonts.gstatic.com;
  img-src 'self' data: blob: https:; connect-src 'self'; frame-src 'none'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'
X-Frame-Options: SAMEORIGIN · X-Content-Type-Options: nosniff · Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=(), usb=() · Cross-Origin-Opener-Policy: same-origin-allow-popups
Strict-Transport-Security: max-age=31536000; includeSubDomains   (HTTPS + APP_ENV=production only; never on local HTTP)
```
| Exception | Why |
|---|---|
| `script-src 'unsafe-inline'` | The design's JS generates inline `onclick=`/`onerror=` handlers, and the layout has one inline bootstrap script. Nonces cannot cover inline handlers, and adding a nonce makes browsers *ignore* `'unsafe-inline'`, which would break those handlers. XSS is handled at the sinks instead (A3/A5/A6/A11); removal is planned in A12. |
| `code.jquery.com`, `cdn.jsdelivr.net` (script/style) | jQuery 3.7.1 + Select2 on `/bulk-order` (SRI missing: A10) |
| `style-src 'unsafe-inline'`, `fonts.googleapis.com`, `fonts.gstatic.com` | Inline `style=""` throughout the design; Google Fonts |
| `img-src https:` | Product/category/hero images are admin-entered URLs (Cloudinary, Unsplash, others) |
| `form-action 'self'` | No external form posts. Google sign-in is a GET navigation, not a form. |

Browser result: **0 CSP violations and 0 JS errors** on `/`, `/products`, a category, two product pages, `/bulk-order`, `/request`, `/account`, `/checkout` and the 404 page.

## 5. Output safety (XSS) audit

| Source | Where it is output | Treatment |
|---|---|---|
| Product description / short description (admin rich text) | Product page accordion, cards | `SafeHtml::clean()` server-side allowlist (p, br, b/strong, i/em, u, s, sub/sup, small, span, div, blockquote, h2–h6, lists, tables, `a[href http/https/mailto/tel/#/relative]` + `rel=noopener noreferrer nofollow`, `img[src http/https/relative]`). Script, style, iframe, svg, math, form and the like are removed with their content; unknown tags are unwrapped; every attribute not allowlisted is dropped. |
| Product, category, subcategory and brand names; mega-menu headings/labels; footer links; hero title/subtitle/CTA; colour names/hex; size labels; variant text; cart/wishlist/search items | `app.js`, `product-detail.js`, `pages/bulk-order.js` templates | `escHtml()`/`escapeHtml()` at each sink; slugs `encodeURIComponent`; URLs `safeUrl()`. Select2's template (which re-injects decoded option text) is escaped too. |
| Customer name/email | Header, dropdown, mobile nav, toasts, account page | Escaped (Part 1 + Part 2) |
| Customer address/order/request data | Account, checkout | Already escaped (`accountEsc`, `esc`); verified |
| Size guide text | Product modal | Already escaped |
| Reviews | Only the average rating and count are output (no review text) | n/a |
| SEO/meta (`<title>`, `og:*`, description) | Blade | Two-argument `@section` escapes once; descriptions `strip_tags`. Tested with a `"><script>` product name. |
| Boot JSON | `<script type="application/json">` | `JSON_HEX_TAG\|JSON_HEX_AMP` cannot close the tag (tested) |
| Search query | Overlay, listing | `textContent` / `esc()`. Already safe. |

**Deploy note:** the catalog cache (`storefront.catalog.v2`) holds descriptions built before this release for up to about 6 minutes. Clear it on deploy: `php artisan tinker --execute="Cache::store('file')->forget('storefront.catalog.v2')"`.

## 6. Browser verification (performed)

**Setup:**
- Headless Chrome (CDP), app served by PHP's built-in server on `shaotal_storefront_test` (temporary `.env.browsertest`, deleted afterwards).
- Hostile fixtures in the test DB: a category, a product (name and description payloads), a hero slide with a `javascript:` CTA, and a customer with a payload last name. All were removed afterwards.
- The dev catalog cache was snapshotted and restored byte-identical.

**Checks (48/48 passed):**
- **Pages:** no CSP violations, no JS errors and no payload execution on 9 pages.
- **Product page:** the description keeps formatting and loses script/handlers/`javascript:`. The hero `javascript:` CTA renders `#`.
- **Cart, search, wishlist:** hostile names render as text.
- **419 (injected via Fetch interception):** `/newsletter:419 → /auth/csrf:200 → /newsletter:200`, and the meta token is updated.
- **Sign-in:** `?signin=/checkout` opens the modal; signing in returns to `/checkout`.
- **Checkout:** an incomplete Profile-style address gets 422 "missing a suburb". Place Order clicked twice creates **exactly 1** order and shows the success dialog.
- **401 (password changed elsewhere):** the next account action opens the sign-in modal with "Your session has ended"; the header switches to signed out; signing in returns to `/account`; the old password is rejected.
- **Register:** a 7-character password is rejected in the modal.
- **Error pages:** 404 screenshots at 375 px and 1280 px are on-brand with no overflow.

**Still to check manually** (not automatable here):
- Real Google sign-in with credentials.
- Real password-reset email delivery (SMTP).
- iOS Safari / Android Chrome devices.
- Production HTTPS (HSTS, secure cookies).
- `/bulk-order` Select2 behaviour with real catalog data on a phone.

## 7. Inventory investigation (C3): owner decision required

**Facts (read-only inspection of both apps and the schema):**
- Schema: a simple product's stock is in `products.stock_qty` (`INT UNSIGNED`, never negative at the DB level). A variant product's stock is in `product_variants.stock_qty` (`INT UNSIGNED NOT NULL DEFAULT 0`). There is no reservation, stock-movement or ledger table.
- Admin (`admin-shaotal`): stock is set **manually** in the product form, Excel import or product import. `OrderController::updateStatus` changes `order_status` and writes `order_timeline` **only, with no stock changes on confirm, ship, cancel or return**. The admin's own `docs/PROGRESS.md` states: "the admin never touches stock or order creation". The dashboard and product list show low-stock warnings (`stock_qty <= COALESCE(low_stock_threshold, 5)`).
- Storefront: `Checkout::priceLines` only blocks `availability` = `out_of_stock`/`coming_soon` (comment: "Stock is not enforced for now"). Quantity is never compared with stock, and `Checkout::place` never decrements. `stockQty` is used only for the "Low stock" badge.
- **Current lifecycle:** none. Stock is a number staff edit by hand. Orders can exceed stock, and nothing restocks on cancel.

**Recommended lifecycle** (for the owner to confirm; **not implemented**, because no rule exists in either app):
1. **Decrement at order placement** in the storefront, inside the existing order transaction, per line: `UPDATE … SET stock_qty = stock_qty - :q WHERE id = :id AND stock_qty >= :q`. If 0 rows are affected, roll back with "Only N left". This is atomic, never goes negative, and needs no new table.
2. Pre-order products skip the check (current behaviour allows ordering them at 0).
3. **Restock on `cancelled`/`returned`** in the admin's `updateStatus` (same transaction as the timeline row). This must be built in the admin app, or stock drifts downward.
4. When Stripe arrives, decide whether unpaid card orders hold stock and for how long (H3).

**Decision needed:** confirm (a) decrement at placement vs at admin confirmation, (b) restock on cancel/return in the admin, (c) pre-order rules. Until then, behaviour is unchanged.

---

## 8. Acceptance criteria (status)
1. Journeys. Browser-verified for sign-in → checkout → order, session expiry and re-sign-in. Not yet: committed E2E suite, order detail (D1).
2. Isolation. ✅ Tested per endpoint; deactivated, deleted and password-changed sessions are rejected.
3. Server-side authorization. ✅
4. Order integrity. ✅ Pricing, atomicity, duplicates, coupon limits and complete addresses. ⏳ Stock (C3 decision), price-change notice (C6).
5. Performance. ⏳ Workstream F (Part 3+).
6. SEO. ⏳ Workstream G.
7. Payments seam. ⏳ H1.
8. Security. Headers ✅, XSS ✅ (browser), session ✅. ⏳ `unsafe-inline` removal (A12), SRI (A10), uploads (A7), deploy config (A9/B4).
9. Tests in CI. ✅ Workflow + recipe validated locally; ⏳ first GitHub run.
10. No regressions. ✅ All Part 1 tests still pass. One Part 1 test payload changed: phone `'1'` → `'0400 000 000'`, because the stricter phone rule now applies before the ownership check.

## 9. Recommended order for Part 3
1. **Deploy blockers:** A9 + B4 + C5 (production `.env`: debug off, secure cookies, cache/session store), and the catalog-cache clear on deploy.
2. **G1–G6 SEO:** server-render the product core, canonical, sitemap, robots, JSON-LD, OG. This is the biggest remaining Critical.
3. **F1/F2 performance:** page-specific boot payload, SSR hero.
4. **D1** order detail + "View My Orders" link.
5. **A10** SRI hashes, **A7** upload limits, **I4** remaining coverage, **I5** commit the browser checks as Playwright.
6. **Owner decisions:** C3 stock, I7 line endings, I8 schema drift, E1 policy content, H2/H3 Stripe.

---

## 10. Change log

### Part 1
- `app/Http/Middleware/EnsureCustomer.php`, `bootstrap/app.php`, `routes/web.php`
- `CheckoutController` duplicate guard
- `app.js` name escaping
- Test infrastructure: `TestCase`, `SizeGuideUnitTest`, `ExampleTest`
- Tests: `CustomerAccessTest`, `CheckoutPlaceOrderTest`

### Part 2
| File | Change |
|---|---|
| `app/Support/CustomerSession.php` (new) | Sign-in/out helper + password fingerprint |
| `app/Http/Middleware/ValidateCustomerSession.php` (new) | Global (web group): ends deleted, inactive or password-changed sessions |
| `app/Http/Middleware/EnsureCustomer.php` | Now only checks sign-in (validation moved to the global middleware); friendlier 401 text |
| `app/Http/Middleware/SecurityHeaders.php` (new) | CSP + headers, HSTS in production over HTTPS |
| `bootstrap/app.php` | Registers both middlewares |
| `app/Http/Controllers/AuthController.php` | 8-character policy, failure-based login limits, forgot-password per-address cap, reset revokes sessions, `csrf()` endpoint, fingerprint at sign-in |
| `app/Http/Controllers/GoogleAuthController.php` | Uses `CustomerSession::start` |
| `app/Http/Controllers/AccountController.php` | Phone rule + message, `addressProblem()`, Profile address-corruption fix (C9) |
| `app/Http/Controllers/CheckoutController.php` | Rejects incomplete addresses; phone rule on inline addresses |
| `app/Models/Checkout.php` | `claimCoupon()` row lock + re-check in the order transaction |
| `app/Models/Catalog.php` | Descriptions sanitised with `SafeHtml` |
| `app/Support/SafeHtml.php` (new) | Allowlist sanitizer |
| `routes/web.php` | `GET /auth/csrf` (throttled) |
| `resources/views/errors/*` (new) | layout + 403/404/419/429/500/503 |
| `resources/views/layouts/store.blade.php` | Loads `api-client.js` before `app.js` |
| `public/assets/js/api-client.js` (new) | Shared AJAX client (401/419/403/429/5xx) |
| `public/assets/js/app.js` | Uses the client; `handleSessionEnded`; `setCsrf`; `escHtml`/`safeUrl` at all catalog sinks incl. footer; password minimum 8 in the modal |
| `public/assets/js/product-detail.js` | Escapes swatches, sizes, toast |
| `public/assets/js/pages/bulk-order.js` | Escapes options, Select2 template, unlisted inputs |
| `public/assets/js/pages/request.js` | Uses the shared client (current CSRF token, safe messages) |
| `phpunit.mysql.xml` (new), `composer.json` | `composer test` = MySQL suite; `composer test:sqlite` |
| `.github/workflows/tests.yml` (new) | CI |
| `tests/TestCase.php` | Guard + skip moved before any DB connection |
| `tests/Concerns/StorefrontFixtures.php`, `tests/Support/place-order-child.php`, `tests/Support/ci-schema-drift.sql` (new) | Test support |
| New tests | `SessionRevocationTest`, `AuthSecurityTest`, `CheckoutAddressTest`, `CouponRedemptionTest`, `CouponConcurrencyTest`, `CatalogOutputSafetyTest`, `ErrorPagesAndHeadersTest`, `tests/Unit/SafeHtmlTest.php`, `tests/js/api-client.test.js` |
| `tests/Feature/CustomerAccessTest.php`, `tests/Feature/SizeGuideUnitTest.php` | Valid phone in a payload; skip without `pdo_sqlite` |
