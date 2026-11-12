# Shaotal Storefront — Production Readiness Plan

_Created 2026-11-11 (Part 1 of 8), updated after Part 2 (security hardening and checkout reliability) on 2026-11-12 after Parts 3 (order lifecycle and customer tracking) and 4 (SEO and server-side rendering), and after Part 5 (admin integration spec, production configuration, delivery wording, performance). Living checklist: update **Status** as items close. Part 3/4 details: §11 and §12. Part 5: §14–§17. Deployment: [STOREFRONT_DEPLOYMENT_CHECKLIST.md](STOREFRONT_DEPLOYMENT_CHECKLIST.md)._

Status values:
- **Fixed**: code plus regression test, verified.
- **Fixed (browser)**: also verified in a real browser.
- **Open**: not started.
- **Decision**: needs an owner/business decision first.
- **Deploy**: server or env config, not code.
- **Blocked**: needs credentials or access.

Scope: `shaotal` storefront (Laravel 13.36, PHP 8.4). The admin app (`admin-shaotal`) shares MySQL `hishabiz_ecom`; it is read-only reference here. Source audit: `STOREFRONT_PAGE_AND_FLOW_AUDIT.md` (checked against the code in Part 1).

**Data safety (Parts 1–5):** no query was run against the remote `hishabiz_ecom` database (Parts 3–5 ran on a macOS machine with no remote credentials at all: the `.env` was created from `.env.example`, SQLite default). Part 5 used only local `shaotal_storefront_test` and `shaotal_perf_test`. `shaotal_local` was only read (a schema-only `mysqldump --no-data` in Part 1); none of its data was changed. Tests and browser checks used only the local isolated DBs `shaotal_storefront_test` and `shaotal_ci_test`.

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

### macOS environment (Parts 3–4)
Parts 1–2 ran on Windows/Laragon. Parts 3–4 ran on macOS (PHP 8.4.21 with `pdo_mysql` + `pdo_sqlite`, Node 26), where nothing was set up:
1. `composer install` (lockfile only, no new packages).
2. `brew install mysql@8.4` + `brew services start mysql@8.4` (local, root, no password, `127.0.0.1:3306`).
3. `shaotal_storefront_test` built with the CI recipe (`sql/ecom.sql` → `sql/google_login.sql` → `tests/Support/ci-schema-drift.sql` → `sql/local_test_seed.sql`).
4. `cp .env.example .env && php artisan key:generate` (same as CI; `.env` is gitignored). Without an `APP_KEY` every feature test fails with "No application encryption key".
5. Baseline before any change: **123/123** PHP, **17/17** JS. Pint: files are LF on this checkout, so the CRLF problem (I7) does not reproduce here.

### CI
`.github/workflows/tests.yml`:
- Spins up a MySQL 8 service and builds the test DB from `sql/`.
- Runs `composer test`, then the JS tests.
- Runs Pint on changed files for pull requests.

**It has not been executed on GitHub** (nothing was pushed). The recipe and both composer scripts were validated locally.

---

## 1. Results

| Run | Part 1 | Part 2 | Parts 3–4 (macOS) | Part 5 (macOS) |
|---|---|---|---|---|
| Full PHP suite, local MySQL | 52/52 | **123/123** (692 assertions) | **177/177** (1152 assertions); `composer test` exit 0 | **206/206** (1388 assertions); `composer test` exit 0 |
| Default `phpunit.xml` (SQLite) | 16 pass / 36 skip / 0 fail | 47 pass / 76 skip / 0 fail | 57 pass / 120 skip / 0 fail | 57 pass / 149 skip / 0 fail |
| JS | 9/9 | **17/17** | **17/17** (no JS test added; see I4) | **17/17** + `node --check` on every script |
| Pint (changed PHP files) | — | pass (as LF copies) | **pass** | **pass** (all changed + new PHP files) |
| Real browser (headless Chrome over CDP, local test DB) | not done | **48/48** checks (§6) | **18/18** (Part 3) + **28/28** (Part 4), §11.7 / §12.7 | **37/37** committed smoke (`tests/browser/smoke.mjs`) + performance runs (§14) |

Mutation checks (fix removed, test fails; fix restored, test passes):
- Part 1: the customer middleware and the duplicate-order guard.
- Part 2: coupon locking. Without it, two simultaneous processes placed **2 orders** for a 1-use coupon, and the lock-wait test failed.
- Part 3: dropping the `customer_id` scope from the order-details query makes `test_another_customer_gets_404_for_details_and_cancel` fail.
- Part 4: dropping `JSON_HEX_TAG` from the JSON-LD encoder makes `test_json_ld_and_meta_cannot_be_broken_out_of` fail.
- Part 5: removing the throttle key prefixes from the catalog, CSRF and login routes makes `test_catalog_browsing_does_not_use_up_the_sign_in_rate_limit` fail.

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
| A9 | H | `APP_DEBUG=true`/`LOG_LEVEL=debug` in `.env`; dev points at the production DB. Part 5: `php artisan storefront:deploy-check` fails on debug, non-https `APP_URL`, insecure cookies, unusable cache/session store; `.env.production.example` template. | `ProductionReadinessTest` | **Deploy (tooling ready)** |
| A10 | M | `/bulk-order` loads jQuery and Select2 from CDNs **without SRI**. | — | Open |
| A11 | M | Admin URLs (hero buttons, mega-menu, social, footer) could be `javascript:`. Fixed: `safeUrl()` (URL-parser allowlist). | Browser: hero `javascript:` CTA renders `#` | **Fixed (browser)** |
| A13 | H | **New (Part 5):** every `throttle:N,M` route shared ONE per-IP bucket (Laravel keys unnamed limiters by IP only). CSRF refreshes, newsletter, order cancel and (new) catalog browsing used up the 10/min sign-in allowance. Fixed: a distinct key prefix per route. | `test_catalog_browsing_does_not_use_up_the_sign_in_rate_limit` (mutation-checked) | **Fixed** |
| A14 | M | **New (Part 5):** `/robots.txt` and the sitemaps (Part 4) were sent `Cache-Control: public` **with a session Set-Cookie**: a shared cache could hand one visitor's session cookie to others. Public routes now run without session/cookie/CSRF middleware. | `test_public_endpoints_send_no_cookies_and_are_publicly_cacheable` | **Fixed** |
| A12 | M | CSP still needs `script-src 'unsafe-inline'`, because the design's JS emits inline handlers. To harden: move handlers to `addEventListener`, then use nonces and drop `'unsafe-inline'`. | — | Open |

### B. Authentication and session management
| ID | Sev | Issue → fix | Verification | Status |
|---|---|---|---|---|
| B1 | H | A password reset did not end other sessions. Fixed with **session fingerprints** (§3). | `SessionRevocationTest` (7); browser: password changed elsewhere → next action ends the session | **Fixed (browser)** |
| B2 | M | Login was throttled per IP only. Fixed: failure-based limits per email+IP (5), per email (20) and per IP (30), all over 15 min, plus the existing route throttle. | `AuthSecurityTest` (IP rotation, spraying, no enumeration, expiry, success clears) | **Fixed** |
| B3 | M | Password minimum was 6. Fixed: 8 for register and reset (server + modal). Existing shorter passwords still sign in. | Tests + browser | **Fixed (browser)** |
| B4 | H | Session/cache config for production: driver, `SESSION_SECURE_COOKIE`, and a cache store with a table/Redis (rate limiter, checkout lock). Part 5: secure cookie now defaults on for an https `APP_URL`; `TRUSTED_PROXIES` support; https URLs forced in production; all checked by `storefront:deploy-check`. | `ProductionReadinessTest` | **Deploy (tooling ready)** |
| B5 | H | The front end ignored 401/419. Fixed: one client (`api-client.js`) handles 401 (sign-in modal, return to the page, once per page), 419 (fresh token + one retry), and 403/429/5xx (safe messages). `request.js` now uses it. | `api-client.test.js` (8); browser: 401 and 419 flows | **Fixed (browser)** |
| B6 | L | Register race returns 500; no email verification. | — | Open |
| B7 | L | Forgot-password could flood one inbox. Fixed: 3 per address per 15 min, same reply. | `AuthSecurityTest` | **Fixed** |
| B8 | info | Laravel 13 CSRF also accepts same-origin requests by `Sec-Fetch-Site`, so real 419s are rare in browsers. The browser test injected one to verify the retry path. | Browser (Fetch interception) | Noted |

### C. Checkout and order integrity
| ID | Sev | Issue → fix | Verification | Status |
|---|---|---|---|---|
| C1 | C | Duplicate orders. Fixed in Part 1: lock + 60 s replay. | Tests; browser: double click → exactly 1 order | **Fixed (browser)** |
| C2 | H | Coupon limit race. Fixed: `Checkout::claimCoupon()` locks the coupon row (`FOR UPDATE`) first in the order transaction, then re-checks status, dates, global limit and per-customer limit. Failed or rolled-back orders and replayed duplicates use nothing. Only one row is locked, always first, so no lock cycle. | `CouponRedemptionTest` (7); `CouponConcurrencyTest` (2, **separate PHP processes**) | **Fixed** |
| C3 | H | Stock is never decremented. Investigation in §7. **Owner decision (Part 3 brief): stock quantity tracking is out of scope until V2.** The storefront never decrements, reserves, restores or checks numeric stock; only the admin availability flag and status/visibility gate ordering. | `CustomerOrdersTest` (zero-stock simple + variant orders, qty above stock, stock unchanged after order and after cancel) | **Decided (V2)** |
| C4 | M | Incomplete saved addresses. Fixed: server-side completeness check (recipient, street, suburb, valid state, 4-digit postcode, phone with 8–15 digits) for saved and inline addresses; phone rule on the address endpoints. | `CheckoutAddressTest` (11); browser: 422 with a clear message | **Fixed (browser)** |
| C9 | H | **New finding:** every Profile save (even a phone-only change) copied the displayed `street, suburb, state` back into `street_address`. The text grew on each save and corrupted delivery labels. Fixed: the address is only written when actually changed. When changed, suburb/state are cleared (free text cannot be split) and checkout asks for them. Existing rows already corrupted this way are **not** auto-repaired. | `CheckoutAddressTest` | **Fixed** |
| C5 | M | The checkout lock and rate limiter use single-server cache stores. Several servers → `CACHE_STORE=redis` (checklist §0.8). | — | Deploy |
| C6 | M | Price change between quote and place is silent. | — | Open |
| C7 | L | 6-digit random order numbers. | — | Open |
| C8 | M | No order-confirmation email (needs SMTP). | — | Blocked |
| C10 | L | A product without an image requested `/null`. Server-rendered pages (product, cards, order page) now use `Img::PLACEHOLDER` (same data URI as `window.PLACEHOLDER_IMG`); script-built images already fell back via `onerror`. | `SeoTest::test_product_without_images_uses_the_placeholder_not_null`; browser: 0 `/null` requests on 9 pages × 2 widths | **Fixed (browser)** |
| C11 | M | Checkout success dialog said "Amount Paid" for a COD order. Now "Order Total". Delivery wording is now configurable in one place (§16); the wording itself still needs the owner. | `ProductionReadinessTest` | Fixed (label, configurable) / **Decision** (copy) |

### D. Customer account and journey
| ID | Sev | Item | Status |
|---|---|---|---|
| D1 | H | Order history (paginated), order details and tracking, customer cancellation; "View My Orders" now opens the Orders tab (and the new order's page right after checkout). §11. | **Fixed (browser)** |
| D2 | M | Address book only in checkout; Profile has a single free-text field (see C9). | Open |
| D3 | L | `/account` shell is served to guests (data is protected server-side). | Decision |
| D4 | L | Orphan `welcome.blade.php` (the only Vite user). | Open |
| D5 | L | Homepage "Our Story" anchor. | Open |

### E. Navigation and pages
| ID | Sev | Item | Status |
|---|---|---|---|
| E1 | H | No Contact, Shipping, Returns, Terms or Privacy pages. Content is needed from the owner. | Blocked (content) |
| E2 | H | Error pages. Added branded 403/404/419/429/500/503 on a standalone layout (no DB/session access, so they work when MySQL is down), `noindex`, links back to shopping. AJAX errors stay JSON. 500s never show exception text when `APP_DEBUG=false`. Verified by `ErrorPagesAndHeadersTest` (11) and screenshots at 375 px and 1280 px (no horizontal scroll). | **Fixed (browser)** |
| E3 | M | Footer duplicates/placeholders. | Open |

### F. Performance
| ID | Sev | Item | Status |
|---|---|---|---|
| F1 | H | Whole catalog (583 KB at 400 products) embedded in every page. Part 5: page-scoped; listing/search via `/catalog/feed` and `/catalog/search` (§14). | **Fixed (browser)** |
| F2 | H | JS-gated LCP. Product, listing, hero, popular, categories, nav, account and checkout content are now in the server HTML. Mobile LCP is bound by uncompressed CSS/JS (F5). | **Fixed (code)** / F5 |
| F3 | M | No minification. Measured: gzip alone takes CSS/JS from ~300 KB to ~65 KB. Minifying would save a little more; not done (no build step for these files). | Open (low value after F5) |
| F4 | M | Server caches/OPcache. Commands and settings in the deployment checklist. | Deploy |
| F5 | H | **New:** CSS/JS served uncompressed and without long cache headers. Guarded rules added to `public/.htaccess` + Nginx snippet. Measured mobile LCP 1.7–2.0 s → 0.6–0.9 s with gzip (§14). | **Deploy (rules ready)** |
| F6 | M | Unmeasured pages. All 11 page types measured, desktop + throttled mobile (§14). | **Fixed** |
| F7 | M | Layout shift: account 0.76 (mobile), checkout 0.13, product 0.08. Server-rendered state. Now ≤ 0.05 everywhere measured. | **Fixed (browser)** |
| F8 | L | Home hero: an admin URL that already carries a Cloudinary width (as in the test data) cannot be resized; mobile still downloads the desktop image (home mobile LCP ~4–5 s locally). Mobile source added (`mobile_image_url`, else a 768 px transform); upload hero images without a fixed width, or set a mobile image. | **Decision (content)** |
| F9 | L | Listing pages filter the cached catalog in PHP. Fine for thousands of products; beyond ~5k move the filters into SQL. | Noted |

Part 2 cost:
- One primary-key lookup per request **only when a customer is signed in** (`ValidateCustomerSession`).
- One extra 4 KB script (`api-client.js`, cache-busted, long-cacheable).
- Description sanitising runs once per catalog-cache rebuild, not per request.

### G. SEO (Part 4, details in §12)
| ID | Sev | Item | Status |
|---|---|---|---|
| G1 | C | Product and listing content was JS-only. Product name, description, price, availability, main image, breadcrumb, description accordion and related links; listing heading, count and first batch of cards; first hero slide (home `h1`); footer category links: all now in the initial HTML. | **Fixed (browser)** |
| G2 | H | Metadata: unique title/description, robots, canonical, Open Graph, Twitter card on every page. | **Fixed** |
| G3 | H | Canonical rules (product slug, stable category/subcategory URLs, sort/tracking/paging ignored, case redirect). | **Fixed** |
| G4 | H | Dynamic sitemap index + pages + chunked product sitemaps. | **Fixed** |
| G5 | M | `robots.txt` (dynamic, sitemap URL from `APP_URL`). | **Fixed** |
| G6 | H | JSON-LD: Organization, WebSite, BreadcrumbList, Product/Offer. | **Fixed** |
| G7 | M | Product cards had no `<a href>` (JS click handlers only), so crawlers could not follow product links. Card names are now real links (server and script cards). | **Fixed (browser)** |
| G8 | M | Home "Popular", "Shop by Category" and the header mega-menu are now server-rendered (Part 5); the script keeps the server's popular pick. | **Fixed (browser)** |
| G9 | L | `aggregateRating` not emitted: the rating appears only after script runs, and Google requires rated content to be visible. Decide whether to server-render reviews first. | **Decision** |
| G10 | M | Product page design copy not backed by data. "Free AU Shipping over A$299" now uses the threshold actually charged (A$100). The other lines are configurable but unchanged (§16). | Fixed (threshold) / **Decision** (copy) |
| G11 | — | No Search Console data: no indexing/ranking claims are made. | Noted |

### H. Stripe-ready payments
H1 (payment seam): `App\Payments\PaymentMethod` interface + `CashOnDelivery` + `PaymentMethods` registry. Checkout validates against implemented methods only (a configured but unimplemented `card` is rejected and not shown), stores `provider`/initial status from the method, and never sets `paid` at placement (§11.5). **Fixed (seam only, no Stripe).** H2–H5 unchanged.
- Before Stripe, the CSP will need `https://js.stripe.com` in `script-src`/`frame-src` and `https://api.stripe.com` in `connect-src`.
- The `payment=` Permissions-Policy is left unrestricted for Stripe.

### I. Testing and readiness
| ID | Sev | Item | Status |
|---|---|---|---|
| I1 | C | Suite fatal error | **Fixed** (Part 1) |
| I2 | C | Remote-DB guard + cache isolation; Part 2 made it run before any connection and forced `$_SERVER` | **Fixed** |
| I3 | H | `pdo_sqlite` missing. `composer test` now runs the MySQL suite and SQLite is optional (documented). | **Fixed** |
| I4 | H | Coverage: auth, session, address, coupon, headers, errors and XSS added; Part 3 orders/tracking/cancel/COD/stock policy; Part 4 SEO. Still missing: quote edge cases, requests/newsletter, profile, cart/wishlist sync, a JS unit test for the account order list. | Open (partial) |
| I5 | M | Browser checks. Part 5 committed a dependency-free CDP smoke suite (`tests/browser/smoke.mjs`, 37 checks) and the performance harness (`tests/browser/perf.mjs`, `tests/Support/perf-*.php`). Not in CI yet (needs Chrome + a served app). | **Partial** |
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

## 7. Inventory investigation (C3): decided in Part 3 — out of scope until V2 (the recommendation below is kept for V2)

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
1. Journeys. Browser-verified for sign-in → checkout → order, session expiry and re-sign-in, order history → details → cancel (Part 3). Not yet: committed E2E suite.
2. Isolation. ✅ Tested per endpoint; deactivated, deleted and password-changed sessions are rejected.
3. Server-side authorization. ✅
4. Order integrity. ✅ Pricing, atomicity, duplicates, coupon limits, complete addresses, price snapshots, COD never paid at placement. Stock: decided out of scope (V2). ⏳ Price-change notice (C6).
5. Performance. ✅ Measured on all 11 page types (§14): page-scoped data, server-rendered state, CLS ≤ 0.05. ⏳ CSS/JS compression and cache headers on the server (F5: rules ready, deploy), hero image content (F8).
6. SEO. ✅ G1–G7 (Part 4). ⏳ G8, G9/G10 decisions.
7. Payments seam. ✅ H1 seam. ⏳ Stripe itself (H2–H5).
8. Security. Headers ✅, XSS ✅ (browser), session ✅. ⏳ `unsafe-inline` removal (A12), SRI (A10), uploads (A7), deploy config (A9/B4).
9. Tests in CI. ✅ Workflow + recipe validated locally; ⏳ first GitHub run.
10. No regressions. ✅ All Part 1 tests still pass. One Part 1 test payload changed: phone `'1'` → `'0400 000 000'`, because the stricter phone rule now applies before the ownership check.

## 9. Recommended order (written after Part 2; see §13 for the Part 5 starting point)
1. **Deploy blockers:** A9 + B4 + C5 (production `.env`: debug off, secure cookies, cache/session store), and the catalog-cache clear on deploy.
2. **G1–G6 SEO:** server-render the product core, canonical, sitemap, robots, JSON-LD, OG. This is the biggest remaining Critical.
3. **F1/F2 performance:** page-specific boot payload, SSR hero.
4. **D1** order detail + "View My Orders" link.
5. **A10** SRI hashes, **A7** upload limits, **I4** remaining coverage, **I5** commit the browser checks as Playwright.
6. **Owner decisions:** C3 stock, I7 line endings, I8 schema drift, E1 policy content, H2/H3 Stripe.

## 11. Part 3: order lifecycle and customer tracking

### 11.1 Source of truth (audit)
- **Order status:** `orders.order_status` ENUM `pending, confirmed, processing, shipped, delivered, cancelled, returned` (`sql/ecom.sql`). Written by the storefront only at placement (`pending`) and on customer cancellation (`pending → cancelled`); everything else is the admin app.
- **Payment status:** `orders.payment_status` ENUM `pending, paid, failed, partially_refunded, refunded`, plus one `payments` row per order (`status` ENUM also allows `authorized`, `cancelled`). Separate from delivery status everywhere in the UI.
- **Status history:** `order_timeline` already exists (`old_status`, `new_status`, `note`, `changed_by_admin_id`, `created_at`, index `(order_id, created_at)`). The storefront writes the placement row; per the Part 1 admin inspection, admin `OrderController::updateStatus` writes `order_status` + a timeline row. **No migration or schema change was needed or made.**
- **Courier/tracking:** there are no courier, tracking-number or tracking-URL columns. Nothing courier-related is displayed.
- **Returns:** no return-request table or status besides the final `returned`.
- **Admin app:** not present on this machine. Admin behaviour above is from the Part 1 read-only inspection; it was **not** re-verified in Part 3.

### 11.2 Customer-facing mapping
| Journey step (brief) | DB status | Shown |
|---|---|---|
| Order Placed | `pending` | Yes |
| Confirmed | `confirmed` | Yes |
| Processing | `processing` | Yes |
| Shipped | `shipped` | Yes |
| Out for Delivery | — (no status) | **Not shown** |
| Delivered | `delivered` | Yes |
| Cancelled | `cancelled` | Exception banner |
| Failed Delivery | — (no status) | **Not shown** |
| Returned | `returned` | Exception banner |

Payment wording: `pending` + COD → "Pay on delivery"; `pending` + cancelled → "No payment due"; otherwise the enum label (Paid, Payment Failed, Refunded, …). Code: `App\Support\OrderStatus`.

### 11.3 Timeline rules (no invented data)
- A step's time is the **first** `order_timeline` row entering that status; "Order Placed" uses `orders.placed_at`. Cancelled uses `orders.cancelled_at`, else its timeline row.
- Steps after the current one are "upcoming" with no time. A step the admin skipped (e.g. `pending → shipped`) is shown as passed **without** a time.
- A current status with no timeline row (older data, or a direct DB edit) is shown as reached without a time.
- For cancelled/returned orders, progress stops at the status recorded before the exception (`old_status` of the exception row), else the furthest recorded step, else "placed" (returned: "delivered"); the remaining steps are marked "not reached".
- **Timezone:** times are shown as stored. Storefront writes use `APP_TIMEZONE` (UTC) and MySQL `CURRENT_TIMESTAMP` uses the server's zone; the admin's zone is unknown. Decide one zone (likely `Australia/Sydney` for display) before launch.

### 11.4 Customer cancellation (decision documented)
- Allowed only while `pending` (not yet confirmed by staff). Config: `storefront.customer_cancellable_statuses` (`['pending']`; set `[]` to turn it off). Only `pending` is accepted even if more are configured.
- Atomic: row lock + compare-and-set on the current status, so a concurrent admin confirmation wins and the customer gets a 422. One `order_timeline` row (`pending → cancelled`, note "Cancelled by customer", no admin id), `cancelled_at` set.
- **Never** touches stock, `payments` or `payment_status` (no automatic COD refund). A cancelled order stops counting toward coupon limits (existing rule: usage = non-cancelled orders).
- Another customer's order or an unknown number → 404; guests → 401 JSON / sign-in redirect.
- **Returns are not offered online:** no schema for return requests. Needs an owner decision + schema + admin flow.

### 11.5 Integrity re-verification
Duplicate prevention (lock + 60 s replay), coupon row lock, server-side prices/discounts/delivery and order-item snapshots still hold; all Part 1–2 tests pass. New: browser-sent `paymentStatus`/`total`/`discount` are ignored (test), COD orders start `pending` with `provider` NULL and `paid_at` NULL, unimplemented methods are rejected, and the details page shows snapshot prices even after a product price change.

### 11.6 Admin-side changes required (admin codebase not available here)
Admin updates already reach the storefront because both read the same tables live (no cache on order pages). Tested by simulating the admin write (`order_status` + timeline row with `changed_by_admin_id`). To make the sync **safe**, the admin `updateStatus` should:
1. Enforce `OrderStatus::TRANSITIONS` (forward only; `cancelled`/`returned` final; no `shipped → cancelled`).
2. Update with a compare-and-set on the old status (`WHERE id = ? AND order_status = ?`) and write the timeline row in the same transaction, so it cannot overwrite a customer cancellation that happened meanwhile.
3. Set `orders.cancelled_at` when cancelling (the storefront displays it).
4. Keep `payment_status` changes separate from fulfilment changes (e.g. record COD cash as `paid` + `payments.paid_at`).
5. Never decrement/restore stock (V2 decision).
6. Optional: if courier tracking is wanted, add `courier_name`/`tracking_number`/`tracking_url` (schema change in `sql/ecom.sql` first); the storefront would then show them.
**Full two-way synchronisation is therefore not claimed as verified.**

### 11.7 Browser verification (18/18)
Isolated DB `shaotal_browser_test` (built with the CI recipe, dropped afterwards), `php artisan serve` with env overrides, headless Chrome over CDP: sign-in → order with a 0-stock variant → `/account#orders` opens the Orders tab → list shows payment method/status and a details link → hash change switches tab → details page (heading, badge, only "placed" reached, 1 timestamp, 2 items, `noindex`, no JS errors, no overflow at 1280 and 375 px) → cancel through the UI (badge "Cancelled", exception banner, "No payment due", button gone) → another customer gets 404 → guest is redirected.

## 12. Part 4: SEO and server-side rendering

### 12.1 Rendering audit (before → after, raw HTML without JS)
| Page | Before | After |
|---|---|---|
| Home | No `h1`; links only to `/products`, `/request`, `/bulk-order` | First hero slide (`h1` + CTA link) and footer category links server-rendered; Organization + WebSite JSON-LD. "Popular" and "Shop by Category" still script-only (G8). |
| Listing / category / subcategory | "All Products" placeholder, empty grid | Real heading, count and first batch (8, same as the script's page size) of cards with product links, BreadcrumbList. |
| Search | Empty grid | Same server filter as the script; `noindex, follow`. |
| Product | "Loading Product...", empty image, A$0.00 | Name, short + full description, price (same initial variant price as the script), availability text, main image with `width`/`height`/`fetchpriority`, breadcrumb, related product links; Product + BreadcrumbList JSON-LD. |
| Bulk order, Request | Static content already server-rendered | Canonical added. |
| Account, Checkout, Order details | — | `noindex, nofollow` meta + `X-Robots-Tag`; checkout and order pages `Cache-Control: private, no-store`. |

The scripts still render the interactive versions on load (same markup), so interactions are unchanged (browser-verified, §12.7). The product page now reads the product **live** from the DB (not the up-to-6-min-stale catalog cache), so price/availability in HTML and JSON-LD are current.

### 12.2 Metadata and canonical rules
- Layout emits title, description, robots, canonical, `og:*` (site_name, title, description, type, url, image, locale) and Twitter card on every page. Pages pass `canonical`, `robots`, `ogImage`, `jsonLd` as view data. All values escaped once.
- Absolute URLs use `config('app.url')`, never the request Host header. **Production `APP_URL` must be the public https origin.**
- Product: canonical `/products/{slug}`; a differently-cased slug 301-redirects (the column collation is case-insensitive). Title = admin `seo_title` or "Name — Category"; description = `seo_description` → short description → full description → generated, plain text, ≤ 158 chars.
- Listing: only `category` then `subcategory` define an indexable URL (`/products?category=x&subcategory=y`, fixed order). Sort, `utm_*`, `gclid`, `page` and array parameters are ignored by the canonical. **`noindex, follow` and no canonical** for search, brand filter, pre-order view, unknown category, or a subcategory not under its category.
- Trailing slashes: Apache's `public/.htaccess` 301s them; Laravel matches both forms and the canonical is slash-free. On Nginx add an equivalent rule (deploy checklist).

### 12.3 Sitemap
`/sitemap.xml` (index) → `/sitemap-pages.xml` (home, `/products`, `/bulk-order`, `/request`, categories and subcategories **that list at least one product**) + `/sitemap-products-{n}.xml` (5,000 per file, active + visible products in active categories). `application/xml`, absolute URLs, `lastmod` from `updated_at`, de-duplicated, `&` escaped. Cached 10 min on the file store; ≤ 5 queries cold, 0 warm (tested). Never account, checkout, cart, order, search or filter URLs.

### 12.4 robots.txt
Dynamic route (the static `public/robots.txt` was removed, since a web server would serve it instead): `Disallow` `/account`, `/checkout`, `/auth/`, `/size-guides/`, search and brand query URLs; `Allow: /`; `Sitemap: {APP_URL}/sitemap.xml`. Assets, products and categories are not blocked. Private pages also send `noindex` and are protected server-side.

### 12.5 Structured data
- Organization (name, url, logo, `sameAs` from https social settings), WebSite, BreadcrumbList, Product (name, url, description, images, sku, brand, category) with Offer, or AggregateOffer when variant prices differ. `priceCurrency` AUD.
- Availability: `PreOrder` for pre-order, `OutOfStock` when the admin flags out_of_stock/coming_soon, otherwise `InStock`, **never from the stock count**.
- No ratings, reviews, condition or GTIN (no verified data visible on the page; G9). Encoded with `JSON_HEX_TAG|JSON_HEX_AMP`; one block per type per page.

### 12.6 Images
Main product image: real `src`, `width`/`height` 800 (square viewport), `fetchpriority="high"`, alt = product name. Cards: alt, 400×450, first row eager, rest `loading="lazy"`. Order page thumbnails lazy with dimensions. Placeholder instead of `/null` (C10). Nothing was downloaded or rehosted.

### 12.7 Browser verification (28/28)
Same isolated setup as §11.7. 9 pages (`/`, `/products`, category, search, 2 products, `/bulk-order`, `/request`, `/account`) × 1280 px and 375 px: 0 JS errors, 0 CSP violations (JSON-LD blocks are data, not script), no horizontal overflow, no `/null` requests; exactly one `h1` on product pages. Product page after scripts: accordions not duplicated, 4 related cards, swatches, price equals the server price, Add to Cart and accordion toggles work. Listing: script cards keep the link, sorting works, card click opens the product. Home: one `h1`, carousel built, footer columns rebuilt.

## 13. Part 5 starting point (written after Part 4; done, see §14–§17; Part 6 start: §17)
1. **Deploy blockers** (unchanged): A9 + B4 + C5 (production `.env`: `APP_DEBUG=false`, `APP_URL=https://…` — now also required for canonicals/sitemap/robots — secure cookies, file/redis cache), catalog-cache clear on deploy, Nginx trailing-slash rule if not Apache.
2. **Admin app changes** in §11.6 (transitions, compare-and-set, `cancelled_at`, timezone).
3. **F1/F2 performance**: page-specific boot payload (the product and listing pages now have server HTML, so the full catalog in every page is the next cost), G8 home sections.
4. A10 SRI, A7 upload limits, A12 inline handlers, I5 Playwright suite (port the two CDP scripts), I4 remaining coverage.
5. Owner decisions: returns flow, courier tracking fields, display timezone, C11/G10 delivery and returns copy, G9 ratings, I7, I8, E1 policy pages, H2/H3 Stripe.

## 14. Part 5: performance (measured, local)

### 14.1 Method (reproducible)
- **Data:** `shaotal_perf_test` = CI recipe + `php tests/Support/perf-catalog-seed.php 400`. That gives **405 products, 2,418 variants, 1,606 images**, deterministic.
- **Server:** `tests/Support/perf-server.php`. Runs in-process, boots a fresh app per request, and records:
  - wall time inside the kernel, query count and query time;
  - HTML bytes (raw and gzip) and boot JSON bytes;
  - warm (median of 5) and cold-catalog-cache runs.

  Limits: PHP CLI without OPcache, no network.
- **Browser:** `tests/browser/perf.mjs`, headless Chrome over CDP, browser cache disabled, median of 3 runs.
  - Desktop: 1280×900, unthrottled. Mobile: 375×812, 4× CPU, 150 ms RTT, 1.6 Mbps.
  - Records TTFB, LCP, CLS, long-task time, script time, requests and bytes per type, image lazy-loading, `/null` requests, JS errors and CSP violations.
  - Served by PHP's built-in server through `tests/Support/perf-router.php`, which gzips PHP output like production would. Static files are uncompressed unless `PERF_GZIP_STATIC=1`.
  - **INP not measured** (needs real interactions).
- **Raw results:** `docs/perf/2026-11-12-*.json`.
- Local numbers: compare before and after; **do not quote them as production values**.

### 14.2 Before → after (same data, same harness)
| Page | Boot JSON (raw) | HTML gz | Warm queries | Mobile LCP | + CSS/JS gzip (deploy) | Mobile CLS | Desktop CLS | Mobile script |
|---|---|---|---|---|---|---|---|---|
| home | 569 → 85 KB | 45 → 14 KB | 0 → 0 | 5.38 → 5.38 s | 4.20 s | 0.062 → 0.037 | 0.000 → 0.003 | 34 → 26 ms |
| listing | 569 → 14 KB | 45 → 8 KB | 0 → 0 | 2.04 → 2.04 s | 0.91 s | 0.004 → 0.000 | 0.015 → 0.023 | 16 → 10 ms |
| category | 569 → 18 KB | 45 → 9 KB | 0 → 0 | 1.89 → 1.90 s | 0.77 s | 0.005 → 0.000 | 0.015 → 0.023 | 16 → 11 ms |
| subcategory | 569 → 18 KB | 45 → 9 KB | 0 → 0 | 1.87 → 1.84 s | 0.69 s | 0.004 → 0.000 | 0.015 → 0.023 | 30 → 10 ms |
| search | 569 → 12 KB | 45 → 8 KB | 0 → 0 | 1.95 → 1.90 s | 0.80 s | 0.004 → 0.000 | 0.015 → 0.023 | 31 → 11 ms |
| product | 569 → 10 KB | 46 → 9 KB | 8 → 8 | 2.64 → 2.63 s | 2.21 s | 0.041 → 0.005 | 0.076 → 0.003 | 40 → 11 ms |
| bulk-order | 569 → 569 KB | 47 → 47 KB | 0 → 0 | 2.10 → 2.09 s | 0.65 s | 0.053 → 0.046 | 0.037 → 0.043 | 32 → 18 ms |
| request | 569 → 3 KB | 46 → 8 KB | 0 → 0 | 1.73 → 1.70 s | 0.59 s | 0.025 → 0.021 | 0.004 → 0.007 | 17 → 5 ms |
| account | 569 → 4 KB | 45 → 7 KB | 4 → 4 | 1.76 → 1.72 s | 0.59 s | **0.755 → 0.001** | **0.343 → 0.007** | 22 → 7 ms |
| checkout | 569 → 4 KB | 48 → 10 KB | 4 → 5 | 1.77 → 1.76 s | 0.60 s | **0.132 → 0.000** | **0.105 → 0.022** | 19 → 6 ms |
| order | 569 → 4 KB | 44 → 6 KB | 8 → 8 | 1.74 → 1.68 s | 0.58 s | 0.000 → 0.000 | 0.000 → 0.003 | 20 → 6 ms |

**Reading it:**
- **Page-specific data** cut the HTML by 75–87% and mobile script time by about two-thirds.
- **Server-rendered state** removed the big layout shifts.
- **Mobile LCP barely moved in code alone.** It is dominated by ~300 KB of uncompressed CSS/JS: serving those gzipped (`.htaccess` rules / Nginx snippet, F5) takes most pages to **0.6–0.9 s**.
- **Home** stays image-bound: the seeded hero URL is pre-sized to 1600 px and cannot be resized (F8).
- **Bulk order** still embeds everything: its product picker needs every product.
- **Server cost:** warm listing pages went from ~7 ms to ~17 ms (filtering and facets now run on the server). Cold catalog builds have +1 query: the 10-second catalog-version check.
- **Unexplained (small):** unthrottled desktop LCP medians moved from ~130–200 ms to ~190–260 ms. On desktop, LCP equals first contentful paint (a text paragraph), and repeated probes give ~170–190 ms. This is within this local server's variation and could not be attributed; check with real-user data after launch.
- **No errors anywhere:** 0 JS errors, 0 CSP violations and 0 `/null` requests on every page and profile, before and after.

### 14.3 What changed
- **Page-scoped catalog** (`Storefront::boot`, `catalogProducts` view data):
  - home: first 15 per category + first 4 pre-orders; listing: the first page (8); product: itself + 4 related; bulk order: all (picker); everything else: none.
  - A signed-in customer's cart and wishlist products are embedded too.
- **New public, cookie-free endpoints** (`CatalogController`, `App\Models\Listing`), `Cache-Control: public, max-age=60`:
  - `/catalog/products?ids=` for guest cart, wishlist and recently viewed lookups (≤ 60 ids);
  - `/catalog/search?q=` for the header search (≤ 20 results + total);
  - `/catalog/feed` for listing filters, sort and infinite scroll, with the same rules the script used.
- **Front end:**
  - `BongoCatalog` (`data.js`) appends fetched products to the same `PRODUCTS` array.
  - Cart lines whose product is not loaded are **kept** until the server confirms the product is gone. A failed lookup never empties a cart (browser-tested by blocking the endpoint).
- **Server-rendered on load:**
  - header nav;
  - home: popular (the script keeps the server's pick), categories, hero with a mobile source;
  - product page: thumbnails, swatches, size pills, rating row, all accordions;
  - account: signed-in/guest view and header card;
  - checkout: addresses embedded and rendered, summary rows drawn before the quote with prices pending.
- **Catalog cache:**
  - Key `storefront.catalog.v3.{version}`. The version comes from `site_settings.catalog_version` (an admin hook, read at most every 10 s) and a local counter (`php artisan storefront:refresh-catalog`).
  - A lock prevents cold-cache stampedes; `Cache::flexible` stale-while-revalidate is unchanged.
  - The product page and checkout already read prices live.
- **Database:** no new indexes. Warm pages run 0–8 fixed queries, and the product page's count does not grow with the catalog (tested). The query patterns use the existing indexes: `(status, visibility, created_at)` and per-product FKs. No schema change.
- **Images:**
  - Empty `src=""` placeholders removed from three modals.
  - Thumbnails, cards and order items carry dimensions and lazy loading; main image `fetchpriority=high`.
  - No `/null` requests (tested).

### 14.4 Regression tests (PHP, `CatalogPayloadTest`, `ProductionReadinessTest`)
- **Payload size per page:** request/account embed 0 products, listing 8 not 25, product ≤ 5, bulk all; the home cap holds.
- **Endpoints:** filters, sort, paging, search, id cap, unlisted products hidden.
- **Caching and privacy:**
  - public endpoints send no cookies;
  - customer pages are never `public`;
  - checkout embeds only the customer's own addresses.
- **Query budgets:** warm query counts and growth.
- **Cache invalidation:** both the artisan command and the admin version bump (with time travel).
- **Rate limits:** catalog browsing never blocks sign-in.
- **Images:** no null or empty image URLs.
- **Server-rendered state:** account view, product options, nav, hero mobile source.
- **Browser (37 checks, `tests/browser/smoke.mjs`):**
  - 8 pages × 2 widths with no JS error, CSP violation or overflow;
  - page scope;
  - the guest cart survives pages without its products; an unlisted product is dropped only after a successful lookup;
  - server search, listing filter/clear/infinite scroll/sort, recently viewed;
  - sign-in, the account view, checkout CLS < 0.1.

## 15. Part 5: admin integration and production configuration

### 15.1 Admin (5A): **not verified**
The `admin-shaotal` codebase is not on this machine (searched the home directory; the other Laravel projects there are unrelated). [docs/admin-order-integration.md](docs/admin-order-integration.md) specifies:
- the shared transition table;
- a compare-and-set `updateStatus` (row lock, expected status, one timeline row, `cancelled_at`, no stock/payment changes);
- payment recording as a separate action;
- the UTC DB session;
- the `catalog_version` bump;
- an 8-step manual verification.

Storefront-side, customer cancellation is already compare-and-set (Part 3). **Real admin → storefront sync remains unverified** until those steps are run against both apps on a local DB.

### 15.2 Production configuration (5B)
- `php artisan storefront:deploy-check` (read-only, prints no secrets, exit 1 on any FAIL).
- `.env.production.example` (placeholders only; parsed with the project's dotenv to confirm it reads correctly).
- [STOREFRONT_DEPLOYMENT_CHECKLIST.md](STOREFRONT_DEPLOYMENT_CHECKLIST.md): install, no migrations (SQL-first), the four Laravel caches, storage link (not needed), catalog refresh, post-deploy curl checks, rollback, logs and personal data.
- **Code:**
  - secure session cookie by default for an https `APP_URL`;
  - `TRUSTED_PROXIES`;
  - `URL::forceHttps()` in production;
  - HSTS only on secure production requests (tested through a trusted proxy);
  - MySQL session time zone `+00:00`.
- The local `.env` was **not** changed for production; `deploy-check` correctly fails on it (6 blocking items).
- `.env` is gitignored; no secrets in the diff.

## 16. Part 5: delivery information and time zone (5C)

**Source of truth:** `config/storefront.php` → `shipping_flat` (A$10) and `free_shipping_over` (A$100). Checkout charges exactly this; free delivery needs a subtotal **over** A$100, so A$100.00 exactly pays A$10.

**Every hardcoded delivery/returns statement found, now configurable in `storefront.delivery_copy`:**

| Where | Statement | Problem | Done |
|---|---|---|---|
| Product trust row | "Free AU Shipping over A$299" | Contradicts the A$100 actually charged (and the same page's delivery box) | **Now built from `free_shipping_over`**. Owner to confirm A$100. |
| Product delivery box | "Complimentary Australia Post Standard Shipping … over A$100. Flat rate A$10 …" | Carrier not integrated | Amounts from config; carrier configurable |
| Product delivery box | "Dispatched from our Sydney hub within 24 hours. Express shipping takes 1–2 business days. Standard … 3–5 … across AU & NZ." | No express option at checkout; checkout accepts AU states only (not NZ) | Configurable, unchanged |
| Product trust row / availability | "West Croydon Dispatch" / "Ready to dispatch from Sydney" | Two different dispatch locations | Configurable, unchanged (also passed to the script) |
| Product, checkout | "30-Day Returns & Exchanges … Drop off at any Australia Post location" / "Free Returns" | No returns flow or policy page (E1, Part 3 returns decision) | Configurable, unchanged |
| Checkout badge | "Australia Post Express" | Checkout charges a flat standard rate | Configurable, unchanged |
| Checkout success | "3 - 5 Business Days via Australia Post" | No courier integration | Configurable, unchanged |
| Account announcement | "Express Australian Delivery · Seamless Door-to-Door Shipping" | "Express" not offered | Configurable, unchanged |
| Request / bulk pages | "Full door-to-door tracking with Australian customs clearance…", "trackable delivery nationwide" | Unverified service claims | **Not changed** (sourcing/B2B copy); listed for the owner |

**Owner decisions needed:**
1. Confirm A$100 / A$10 (and "over" vs "from" A$100).
2. Carrier name, or none.
3. Delivery estimate wording.
4. Whether express exists; remove it if not.
5. Dispatch location: Sydney or West Croydon.
6. NZ delivery: yes or no.
7. Returns policy (30 days? free?) and the returns flow.
8. The request/bulk page claims.

**Time zone:**
- Storage stays UTC (`APP_TIMEZONE=UTC`, and the MySQL session is now `+00:00`, so `CURRENT_TIMESTAMP` rows and app-written rows agree).
- No business time zone exists in settings or schema, and the copy mentions both Sydney and West Croydon (NSW or SA), so none is assumed.
- `STOREFRONT_DISPLAY_TIMEZONE` defaults to UTC, and order pages label times "UTC" until it is set.
- **Recommendation:** set it to the dispatch location's zone (`Australia/Sydney` if Sydney) once confirmed, and use the same zone in the admin app.

## 17. Part 6 starting point
1. **Deploy:** production `.env` from the template, then `storefront:deploy-check` green and the checklist. Enable CSS/JS compression and cache headers (F5: biggest mobile win). Set `TRUSTED_PROXIES` if behind a proxy.
2. **Admin:** apply and verify [docs/admin-order-integration.md](docs/admin-order-integration.md) (transitions, compare-and-set, `cancelled_at`, catalog version bump, UTC session), then run its 8 steps.
3. **Owner decisions:** delivery copy (§16), display time zone, returns, hero images uploaded without a fixed width or with a mobile image (F8), E1 policy pages, Stripe (H2/H3).
4. **Code:** A12 (inline handlers → CSP nonces), A10 SRI, A7 upload limits, run the browser smoke in CI (I5), D4 remove `welcome.blade.php`, I4 remaining coverage, F9 if the catalog grows past a few thousand products.

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

### Part 3
| File | Change |
|---|---|
| `app/Support/OrderStatus.php` (new) | Status/payment labels, badge classes, allowed transitions, timeline builder |
| `app/Models/CustomerOrders.php` (new) | Customer-scoped paginated history, details, atomic cancellation |
| `app/Http/Controllers/OrderController.php` (new) | `GET /account/orders`, `GET /account/orders/{number}`, `POST /account/orders/{number}/cancel` |
| `app/Http/Controllers/AccountController.php` | Old unpaginated `orders()` removed |
| `app/Payments/PaymentMethod.php`, `CashOnDelivery.php`, `PaymentMethods.php` (new) | Payment-method seam for Stripe |
| `app/Models/Checkout.php`, `app/Http/Controllers/CheckoutController.php` | Use the payment seam (validation, `provider`, initial status); Pint import order |
| `config/storefront.php` | `customer_cancellable_statuses` |
| `routes/web.php` | Order routes (customer middleware, number pattern, cancel throttle) |
| `resources/views/pages/order-detail.blade.php`, `public/assets/css/order-detail.css`, `public/assets/js/pages/order-detail.js` (new) | Order details / tracking page |
| `public/assets/js/pages/account.js` | Paginated list (Load More), details link, payment info, hash deep links + `hashchange` |
| `resources/views/pages/checkout.blade.php`, `public/assets/js/pages/checkout.js` | "View My Orders" → `/account#orders` then the new order's page; "Amount Paid" → "Order Total" |
| `app/Support/Img.php` | `PLACEHOLDER` constant |
| Tests | `tests/Feature/CustomerOrdersTest.php` (20), `tests/Unit/OrderStatusTest.php` (10); `CustomerAccessTest` reads the paginated shape |

### Part 4
| File | Change |
|---|---|
| `app/Support/Seo.php` (new) | Absolute URLs, canonical listing URLs, plain-text descriptions, JSON-LD builders |
| `app/Http/Controllers/SeoController.php` (new) | `robots.txt`, sitemap index/pages/products |
| `app/Http/Controllers/PageController.php` | Per-page SEO data, listing SSR + canonical/robots rules, live product + slug redirect, initial price, related, gallery, `X-Robots-Tag` on account/checkout/size guides |
| `app/Support/SafeHtml.php` | `link()` for server-rendered admin URLs |
| `resources/views/layouts/store.blade.php` | Robots, canonical, OG, Twitter, JSON-LD |
| `resources/views/partials/product-card.blade.php` (new) | Server twin of the JS card with a real link |
| `resources/views/pages/product-detail.blade.php`, `products.blade.php`, `index.blade.php`, `partials/footer.blade.php` | Server-rendered content (§12.1) |
| `resources/views/pages/account.blade.php`, `checkout.blade.php` | `noindex, nofollow` |
| `public/assets/js/app.js` | Card name is a link |
| `public/robots.txt` | Removed (replaced by the route) |
| Tests | `tests/Feature/SeoTest.php` (24) |

### Part 5
| File | Change |
|---|---|
| `app/Models/Listing.php` (new) | Server-side listing filters/sort/paging/facets, quick search, lookup by ids |
| `app/Http/Controllers/CatalogController.php` (new) | `/catalog/products`, `/catalog/search`, `/catalog/feed` (public, cookie-free) |
| `app/Models/Storefront.php` | Page-scoped boot, home product set, versioned catalog cache + lock, `refresh()`, sign-in payload includes products, `dispatchStatus` |
| `app/Models/Catalog.php` | Hero `imageSrcMobile` |
| `app/Providers/AppServiceProvider.php` | Boot payload per request (not per process), trusted proxies, https URLs in production |
| `app/Http/Controllers/PageController.php` | Uses `Listing`; page product scope; popular pick; initial options; checkout addresses |
| `routes/web.php` | Session-less group for robots/sitemaps/catalog JSON; catalog routes; per-route throttle prefixes (A13) |
| `routes/console.php` | `storefront:refresh-catalog`, `storefront:deploy-check` |
| `config/storefront.php` | `delivery_copy`, `display_timezone`, `trusted_proxies` |
| `config/session.php`, `config/database.php` | Secure-cookie default for https; MySQL session `+00:00` |
| `resources/views/partials/header.blade.php`, `pages/index.blade.php`, `pages/product-detail.blade.php`, `pages/account.blade.php`, `pages/checkout.blade.php`, `pages/products.blade.php`, `pages/order-detail.blade.php`, `partials/overlays.blade.php` | Server-rendered nav/popular/categories/hero/product options/accordions/thumbnails/account view/addresses; listing JSON; delivery copy; display time zone; empty `src` removed |
| `resources/views/partials/checkout-address-cards.blade.php` (new) | Server twin of the checkout address cards |
| `public/assets/js/data.js`, `app.js`, `products.js`, `product-detail.js`, `pages/account.js`, `pages/checkout.js` | `BongoCatalog`; safe cart reconcile; server search; feed-driven listing; fetch-by-id for recently viewed/wishlist; hero mobile source; keep server popular pick; embedded addresses; pending summary rows |
| `public/assets/css/sections.css` | Server-rendered hero background (mobile/desktop via CSS variables) |
| `public/.htaccess` | Guarded gzip + long cache for versioned assets |
| `.env.production.example`, `STOREFRONT_DEPLOYMENT_CHECKLIST.md`, `docs/admin-order-integration.md` (new) | Deployment template, checklist, admin specification |
| `tests/Feature/CatalogPayloadTest.php` (15), `tests/Feature/ProductionReadinessTest.php` (14) (new) | Part 5 regression tests |
| `tests/browser/cdp.mjs`, `smoke.mjs`, `perf.mjs`, `tests/Support/perf-server.php`, `perf-router.php`, `perf-catalog-seed.php` (new) | Committed browser smoke + reproducible performance harness |
| `docs/perf/2026-11-12-*.json` (new) | Raw before/after measurements |
