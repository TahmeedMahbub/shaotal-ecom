# Session handoff: Part 8 COMPLETE (uncommitted)

_Updated 2026-11-12 after Part 8 (final regression, payment review, release readiness), on the home PC (Windows/Laragon). Base commit: `c95ec27` "part 6", which contains Parts 6 **and** 7. Part 8 is uncommitted. Nothing was committed, pushed, stashed or reset. Authoritative record: STOREFRONT_PRODUCTION_READINESS_PLAN.md §20 (issue log §20.6)._

**Part 8 changed (working tree):**
- `tests/browser/journey.mjs` (new);
- `.github/workflows/tests.yml` (journey step);
- `docs/payments-architecture.md` (Part 8 review, P1–P8);
- the plan (§0, §1, I5/I6, §8, §19 note, §20, change log);
- `docs/SEO_AUDIT.md` §14, `docs/PROGRESS.md`, `STOREFRONT_DEPLOYMENT_CHECKLIST.md` (0.13, 0.14, §6), this file.

No application code changed.

**Results:**
- **GitHub CI:** run #2 on `c95ec27` passed both jobs (first ever GitHub run of the browser job).
- **PHPUnit:** MySQL 252/255, 0 skipped. The 3 errors are Windows `symlink()` permission; they pass on CI. SQLite: 49 pass / 206 skip / 0 fail.
- **Other suites:**
  - JS 17/17;
  - smoke 51/51;
  - SEO 42/42 + 2 SKIP (seed) and 54/54 (perf);
  - **journey 32/32** (new);
  - crawl 405/405.
- **Perf, 11 pages × 2 profiles:** 0 errors, 0 CSP violations. Home mobile LCP 2.02 s.

**Release blockers (plan §20.6):**
1. **RB1** admin integration.
2. **RB2 (new)** `composer audit` reports 8 advisories. Fix:
   ```sh
   composer update -W "symfony/*" phpseclib/phpseclib   # patch/minor only, per the dry run
   ```
   Then run the full suite and CI, and commit `composer.lock`.
3. **RB3** production deployment.
4. **RB4** owner decision on the policy pages.

**New items:**
- **C12** owner: free shipping is decided before the coupon discount.
- **I11** tests log to the real `laravel.log`.
- **I12** the private-disk `serve` route.
- **P1–P8** Stripe design gaps.

**Local environment at the end of Part 8 (home PC):**
- Local test DBs only (all `127.0.0.1`, root, no password):
  - `shaotal_storefront_test` (now with `proposed-schema.sql`);
  - `shaotal_browser_test` (seed + fixtures; contains the journey/smoke test rows: 3 test orders, 3 journey customers, contact/sourcing/newsletter rows);
  - `shaotal_perf_test` (405 products + perf customer and order).

  All are safe to drop and rebuild with the CI recipe (plan §0).
- No server is left running (port 8124 was stopped).
- The file catalog cache is shared between local DBs: run `storefront:refresh-catalog` with the target `DB_*` and `CACHE_STORE=file` before switching.
- Laragon PHP has no `pdo_sqlite`; pass `-d extension=pdo_sqlite` for the MySQL suite to run all 255 tests.
- Browser suites on Windows: `CHROME_PATH="C:/Program Files/Google/Chrome/Application/chrome.exe"`.
- Not done (permission): running `config/route/view/event:cache` locally. Do it on the server (checklist §1.5) or in a scratch checkout.

**Next:**
- Apply RB2 and push (that also gives the journey step its first CI run).
- RB1 when the admin code is available.
- Owner decisions (§20.6).
- No Part 9 brief exists. This was the last planned part (plan: "Part 1 of 8").

_Part 7 record (kept for history; committed with Part 6 in `c95ec27` "part 6", so "uncommitted" below is no longer true):_

_Updated 2026-11-12 after Part 7 (SEO audit and optimisation). Last commit is still `47920bf Part 3, 4, 5`. **All Part 6 and Part 7 work is uncommitted:** nothing was committed, pushed, stashed or reset. The authoritative records:_
- _Part 7: STOREFRONT_PRODUCTION_READINESS_PLAN.md §19 and docs/SEO_AUDIT.md;_
- _Part 6: plan §18._

**Part 7 in brief:**
- **Pagination:** crawlable self-canonical `?page=N` listing pages.
- **Related products:** rotate through the category. Products reachable by links: 405/405, was 71/405.
- **robots.txt:** no longer hides noindex on account and checkout.
- **Trailing slash:** global 301 middleware.
- **Title and breadcrumb:** consistent before and after scripts.
- **Structured data:** CollectionPage/ItemList, shipping details, ContactPage/WebPage, configured contactPoint.
- **Sitemap:** image entries.
- **deploy-check:** fails a local `APP_URL` host.
- **Tests:** `tests/Feature/SeoCrawlabilityTest.php` (17 tests) and `tests/browser/seo.mjs` (also in CI).
- **Results:**
  - PHPUnit 255/255 (2182 assertions, 0 skipped);
  - SQLite 57 pass / 198 skip / 0 fail;
  - JS 17/17;
  - browser SEO 54/54 (perf catalog) and 42/42 + 2 SKIP (seed DB);
  - smoke 51/51.

**Local environment at the end of Part 7:**
- No local server is left running; ports 8124 and 8125 were stopped.
- The 2 smoke-test contact rows Part 7 created were deleted from `shaotal_browser_test` and `shaotal_perf_test`.
- **Note for future local testing:** two local servers on different `*_test` databases **share the file catalog cache** (the cache key comes from each database's `catalog_version`, empty in both). Run one server at a time, or run `storefront:refresh-catalog` with that server's `DB_*` and `CACHE_STORE=file` overrides. Production (one database) isn't affected.

**Next:**
- **Part 8:** not started. Wait for its brief.
- **Still open:**
  - Admin integration (release blocker);
  - owner approvals (plan §18.5, SEO_AUDIT §13);
  - Google Search Console (owner access);
  - first CI run on GitHub;
  - production upload and memory limits from the Part 6 audit.

_Part 6 record (kept for history):_

**Resolved since the pause:**
- Every item in "Implemented but NOT yet verified" below except CI on GitHub and hero LCP.
- **Final results:**
  - PHPUnit MySQL: 238/238.
  - SQLite: 57 pass / 181 skip / 0 fail.
  - `composer test`: exit 0.
  - JS: 17/17.
  - Smoke test: 51/51.
  - Targeted browser checks: 13/13.
- **Phase B code change:** `storefront:deploy-check` now:
  - requires the `storage:link` symlink (it used to FAIL on any `public/storage`);
  - checks GD and the new `STOREFRONT_*` settings.
  It has 2 new tests and 1 extended one.
- **Local cleanup:**
  - The Phase A rows were removed from `shaotal_browser_test`.
  - The 4 uploaded test images and their folder were removed.
  - The servers on ports 8124 and 8125 were stopped.

**Still open:**
- **Admin integration** (release blocker).
- **Owner and legal approvals** (plan §18.5).
- **First CI run on GitHub.**
- **Hero LCP re-measure** (optional).
- **Do not start Part 7** without a new brief.

_The sections below are the pause-time record, kept for history._

## Task status (Part 6 brief, Tasks 1–10)
| # | Task | Status | Verified by |
|---|---|---|---|
| 1 | Admin integration | **BLOCKED.** The admin codebase is still not on this machine. It remains a release blocker; nothing was simulated as verified. | — |
| 2 | Tighten CSP | **Done.** `script-src 'self'`, `script-src-attr 'none'`, no `'unsafe-inline'` for scripts. jQuery/Select2 allowed only on `/bulk-order`, pinned to exact files, with SRI (jQuery hash = official; Select2 hash identical on jsDelivr and unpkg). All inline handlers were replaced by `data-action` delegation + a global image-fallback listener (`data.js`); `window.__PAGE__` is now a JSON block; the 419 page uses `error-page.js`. `style-src 'unsafe-inline'` is kept on purpose (inline `style=""` throughout the design); `upgrade-insecure-requests` is on in production only. | `SecurityHardeningTest` (mutation-checked), browser smoke: 0 CSP violations on 13 pages × 2 widths |
| 3 | Uploads & spam | **Done.** `App\Support\ImageUpload`: content MIME (jpeg/png/webp), 5 MB each, 15 MB total, max 5 files, max 6000 px; re-encoded with GD (EXIF and appended payloads dropped) to a random UUID name. `App\Support\SpamGuard`: honeypot `company_url`, max 3 links, 5 per contact per hour. New per-route throttles. | `UploadAndSpamTest` (PHP). Browser: contact form only. **Sourcing upload in a real browser not verified.** |
| 4 | Returns workflow | **Implemented behind a flag (OFF), blocked for release.** `CustomerReturns`, `POST /account/orders/{n}/return`, order-page panel. Rules: own delivered order, once, optional `window_days` that needs a recorded delivery time. No refund, payment or stock change. Tables are only **proposed** (`sql/proposed_changes.sql` §4) and applied **only** to local `*_test` DBs via `tests/Support/proposed-schema.sql`. | `ReturnRequestTest` (PHP). **Browser UI not verified.** |
| 5 | Policy pages | **Done as drafts.** `/contact` (form → `contact_messages`), `/shipping`, `/returns`, `/terms`, `/privacy`. Unapproved pages show "Being finalised", are `noindex` and are not in the sitemap; approve with `STOREFRONT_APPROVED_PAGES`. Contact details show only if `STOREFRONT_CONTACT_*` is set (none invented). No About page: the only story content is 2 sentences already on the home page. Footer links added. | `BusinessPolicyTest`, smoke (CSP/overflow, contact submit). **Owner/legal review pending.** |
| 6 | Timezone | **Done.** `App\Support\DisplayTime`, default `Australia/Sydney` with an AEST/AEDT label. Covers order history and details, the timeline, request dates and "member since". Storage stays UTC; converted exactly once. | DST tests both directions (`BusinessPolicyTest`), updated `CustomerOrdersTest` / `ProductionReadinessTest` |
| 7 | Hero image | **Done.** First slide is a `<picture>`: mobile `<source>` (`mobile_image_url` or a 768 px transform), `srcset`/`sizes`, width/height 1600×533, `fetchpriority=high`, matching `imagesrcset` preloads; later slides load after `load`. `Img` (PHP + JS) now resizes a width-only Cloudinary transform (`w_1600`); other transforms stay untouched. | `ProductionReadinessTest` hero test; smoke home checks. **LCP not re-measured.** |
| 8 | Payment architecture | **Done (doc only, by design).** `docs/payments-architecture.md`: status mapping, Stripe plug-in plan, webhook signature + idempotency (proposed `payment_events` table), secrets, CSP. No Stripe code; COD unchanged. | Existing COD tests still pass |
| 9 | Security audit | **Done for findings; not yet written into the plan.** **Fixed:** (a) open redirect via `?signin=/\evil` or tab characters, client and Google `return` (`safeReturnPath`), mutation-checked; (b) `productLink` `javascript:` XSS, now `url:http,https`; (c) upload hardening. **Checked, no issue:** raw SQL (static/bound), unescaped Blade (static or sanitised), session regeneration on every sign-in, no `$request->all()` writes, IDOR scoping on orders/addresses/returns, client payment fields ignored. | `SecurityHardeningTest` |
| 10 | CI browser tests | **Added, never run on GitHub.** `.github/workflows/tests.yml`: new `browser` job (MySQL service, separate `shaotal_browser_test` DB, `tests/Support/browser-fixtures.php`, PHP server via `perf-router.php`, Chrome over CDP, uploads screenshots + server log on failure). The PHPUnit DB build now also loads `proposed-schema.sql`. | YAML parses; the same steps passed locally (51/51) |

## Latest test results (exact)
| Check | Result | When |
|---|---|---|
| PHPUnit MySQL (`php vendor/bin/phpunit -c phpunit.mysql.xml`) | **236/236**, 1860 assertions | Before the last two edits (see "not yet verified") |
| `SecurityHardeningTest::…no_inline_script…` after adding the order page | pass | After the edit |
| Pint on all changed/new PHP files | **pass** | After applying fixes |
| Browser smoke `tests/browser/smoke.mjs` | **51/51** on `shaotal_perf_test` and **51/51** on seed-only `shaotal_browser_test` | Before Pint (Pint only reformatted PHP) |
| Mutation checks | CSP `'unsafe-inline'` and the return-path check both made their tests fail, then were restored | Part 6 |
| JS unit `node --test tests/js/*.test.js` | 17/17 | **Start of Part 6 only** (the tested files are unchanged); `node --check` passed on every changed script |
| SQLite run, `composer test` | **not run in Part 6** | — |

## Implemented but NOT yet verified
1. **Full PHPUnit run after the Pint auto-fixes** (import order, `! $x` spacing in 6 files) and after the `SecurityHardeningTest` extension. The final verification command was interrupted.
2. SQLite run, `composer test` exit code, and the JS unit tests after the Part 6 JS changes.
3. Browser: sourcing request with real image uploads, the return-request form UI, and the 419 page "Go Back" in a browser.
4. Hero/LCP performance after Task 7: not re-measured (`tests/browser/perf.mjs`).
5. CI workflow on GitHub (both jobs).

## Blockers and owner decisions
- **Admin app unavailable (release blocker):** apply and verify `docs/admin-order-integration.md`. It still needs a §7 for the return-request review (transitions `requested → approved/rejected`, `approved → completed`, one event row per change); not written yet.
- **Returns:** approved policy + window (`STOREFRONT_RETURNS_WINDOW_DAYS`), apply `sql/proposed_changes.sql` §4 to the shared DB, admin review screen, then `STOREFRONT_RETURNS_ENABLED=true`.
- **Policy pages:** owner review of shipping/returns/contact; legal review of terms/privacy; then `STOREFRONT_APPROVED_PAGES=…`. Real contact details (`STOREFRONT_CONTACT_EMAIL/PHONE/HOURS`).
- **Hero assets:** upload hero images without a fixed width, or set `mobile_image_url`. Spec: desktop 3:1, ≥1920×640; mobile 2:1, ≥1080×540; JPEG/WebP.
- **Stripe** (future): decisions H2/H3 in `docs/payments-architecture.md`.
- **Deployment:** the checklist still says `storage:link` is optional; it is now **required** if staff must see sourcing photos. The new env vars must be added to `.env.production.example`, the checklist and `deploy-check`: `STOREFRONT_DISPLAY_TIMEZONE` (now defaults to Sydney), `STOREFRONT_APPROVED_PAGES`, `STOREFRONT_CONTACT_*`, `STOREFRONT_RETURNS_*`. GD is needed for uploads.

## Exact next steps to resume
1. **Verify** (the interrupted command):
   ```sh
   cd /Users/tahmeed/Documents/shaotal-ecom
   php artisan view:clear
   php vendor/bin/phpunit -c phpunit.mysql.xml          # expect 236/236 (one test was extended, none added)
   php vendor/bin/phpunit                                # SQLite: DB tests skipped, 0 failures
   composer test                                         # exit 0
   node --test tests/js/*.test.js                        # 17/17
   BASE=http://127.0.0.1:8124 node tests/browser/smoke.mjs   # needs the server below
   ```
2. **Browser-check** the unverified items: sourcing upload, return form (set `STOREFRONT_RETURNS_ENABLED=true` on the local server), 419 page.
3. **Docs:**
   - Plan: a Part 6 section with Tasks 1–10, the A12/A10/A7 statuses, the security findings, and E1 as drafts.
   - Admin doc: a returns §7.
   - Deployment checklist and `.env.production.example`: the new env vars and `storage:link`; extend `storefront:deploy-check` to warn on unapproved pages, missing contact details and missing GD.
   - `docs/PROGRESS.md`.
4. Optional: re-run `tests/browser/perf.mjs` for the hero/LCP comparison.
5. Do not start Part 7.

## Local environment
- MySQL 8.4 (Homebrew), running. Local test DBs only:
  - `shaotal_storefront_test` (PHPUnit; proposed return tables applied);
  - `shaotal_perf_test` (405-product catalog + perf customer; proposed tables applied);
  - `shaotal_browser_test` (seed + fixtures + proposed tables; created in Part 6, safe to drop).
- **A PHP server is still running on `127.0.0.1:8124`** against `shaotal_browser_test` (started in Part 6). Stop it with `for pid in $(lsof -ti tcp:8124); do kill $pid; done`, or restart it:
  ```sh
  cd public && PHP_CLI_SERVER_WORKERS=4 APP_DEBUG=false DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=shaotal_browser_test \
    DB_USERNAME=root DB_PASSWORD= CACHE_STORE=file SESSION_DRIVER=file APP_URL=http://127.0.0.1:8124 \
    php -S 127.0.0.1:8124 ../tests/Support/perf-router.php
  ```
- Rebuild a browser DB: the CI recipe files + `tests/Support/proposed-schema.sql`, then `php tests/Support/browser-fixtures.php` (with `DB_*` env pointing at the local `*_test` DB; it refuses anything else).
- The remote `hishabiz_ecom` was never contacted; the local `.env` has no remote credentials.

## Files changed in Part 6
**Modified (41):**
- `.github/workflows/tests.yml`, `.gitignore`
- `app/Http/Controllers/{AccountController,GoogleAuthController,OrderController,PageController,RequestController,SeoController}.php`
- `app/Http/Middleware/SecurityHeaders.php`
- `app/Models/{Catalog,Checkout,CustomerOrders,Storefront}.php`, `app/Support/Img.php`
- `config/storefront.php`
- `public/assets/css/sections.css`
- `public/assets/js/{app,data,product-detail}.js`, `public/assets/js/pages/{account,bulk-order,checkout,order-detail,request}.js`
- `resources/views/errors/{419,layout}.blade.php`, `resources/views/layouts/store.blade.php`
- `resources/views/pages/{account,bulk-order,checkout,index,order-detail,product-detail,request}.blade.php`, `resources/views/partials/footer.blade.php`
- `routes/web.php`, `sql/proposed_changes.sql`
- `tests/Feature/{CustomerOrdersTest,ProductionReadinessTest,SeoTest}.php`, `tests/browser/smoke.mjs`

**New (21):**
- `app/Models/CustomerReturns.php`
- `app/Support/{Delivery,DisplayTime,ImageUpload,SpamGuard}.php`
- `docs/payments-architecture.md`
- `public/assets/css/info-pages.css`
- `public/assets/js/error-page.js`, `public/assets/js/pages/contact.js`
- `resources/views/pages/info/{layout,contact,shipping,returns,terms,privacy}.blade.php`
- `tests/Feature/{BusinessPolicyTest,ReturnRequestTest,SecurityHardeningTest,UploadAndSpamTest}.php`
- `tests/Support/{browser-fixtures.php,proposed-schema.sql}`

**Business decisions applied in config:**
- Free shipping at **≥** A$100 (was >).
- `delivery_copy` with no express, NZ, Australia Post, tracking, dispatch-location, delivery-time or 30-day-returns claims.
- `display_timezone` defaults to `Australia/Sydney`.
