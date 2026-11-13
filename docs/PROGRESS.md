# Progress

_Last updated: 2026-11-12 (production-readiness Parts 3–8: see STOREFRONT_PRODUCTION_READINESS_PLAN.md §11–§20, docs/SEO_AUDIT.md, STOREFRONT_DEPLOYMENT_CHECKLIST.md and SESSION_HANDOFF.md)_

## Architecture (how the design was kept)
The prepared design is rendered as Blade pages (`resources/views/pages`, shared shell in `layouts/store` + `partials/`). Its CSS/JS live in `public/assets` and run unchanged in spirit: the catalog, categories, hero and nav are read from MySQL (`App\Models\Catalog` / `Storefront`, cached 60s on the file store) and injected as `window.__BOOT__`; `public/assets/js/data.js` turns that into the globals the design scripts expect. Cart/wishlist use localStorage for guests and are synced to `carts`/`cart_items`/`wishlist_items` for signed-in customers. All prices, stock, coupons and totals are recomputed server-side (`App\Models\Checkout`). Customer sessions use Laravel's session (`customers` table), no Eloquent models/migrations.

## Done (tested end-to-end on a local scratch DB `shaotal_local`, loaded from `sql/ecom.sql` + `sql/local_test_seed.sql`)
- Home, Products (filters/sort/infinite scroll, DB-driven filter options), Product detail (variants, colour-specific images, size guide, specs, reviews rating), Cart drawer, Wishlist drawer, Search, Nav/footer.
- Register / login / logout / password reset (emailed 6-digit code via `customer_verification_codes`), security log.
- Account: profile, orders, wishlist, cart, sourcing + bulk requests, saved addresses (`customer_addresses`).
- Checkout: addresses, coupons (`coupons*` tables), shipping rule from design, order + items + payment + timeline in one transaction, COD.
- Bulk order request, Sourcing request (with image upload), Newsletter.
- Verified: all routes 200, no console/network errors, order/request rows written correctly.

## Pending / blockers (need you)
1. **Cloudinary URLs**: product/category/hero images come from DB `image_url` fields. The 3 static home images + social links read `site_settings` keys (`home_living_image`, `home_jute_image`, `home_story_image`, `social_*`); sections hide them until set. Template in `sql/proposed_changes.sql` (not executed).
2. **Schema changes (approval needed)**: `sql/proposed_changes.sql` adds postcode/target date/variant columns and makes `email` nullable on bulk/sourcing requests. Until then those values are stored in the message/details text, and phone-only contacts require a signed-in account email.
3. **Card & PayPal payments**: no gateway credentials, so only COD is enabled (`config/storefront.php` `payment_methods`). Orders are created `pending`.
4. **Google sign-in** implemented but needs credentials + `sql/google_login.sql` (not executed) — see `docs/google-login.md`. Password-reset email uses `MAIL_MAILER` (currently `log`) — configure SMTP.
5. **Stock quantity is out of scope until V2** (owner decision, 2026-11-12): never decremented, reserved or checked; only the admin availability flag and status/visibility gate ordering.
6. **Sourcing images** are stored on the Laravel `public` disk as re-encoded copies with random names (Part 6). `php artisan storage:link` is **required** on the server, or staff get 404 for every photo. It is **not** set up in this local checkout (`public/storage` absent). `storefront:deploy-check` fails without it. Switch to Cloudinary upload when credentials exist.
7. `.env`: `CACHE_STORE`/`QUEUE_CONNECTION=database` but no such tables in the SQL; the storefront uses the `file` cache store explicitly and no queues. `sessions` use files.
8. Footer links: `/contact`, `/shipping`, `/returns`, `/terms`, `/privacy` exist since Part 6 as **drafts** ("being finalised", noindex, not in the sitemap) until the owner approves them (`STOREFRONT_APPROVED_PAGES`; terms/privacy also need legal review). FAQ and the launch-offer strip (ends 2026-10-15, static in design) still have no pages or data.
9. Brand renamed to "Shaotal" everywhere user-visible (config, titles, footer, JS fallbacks). Internal identifiers keep the old prefix on purpose: localStorage keys `bongo_*` (renaming would empty customers' saved carts/wishlists) and `window.Bongo*` globals. `design/` prototype and `.env` `APP_NAME` (still `Laravel`) were not changed.
10. Category slugs `home-decor` and `preorder`-availability are used by home sections as in the design.
11. Product detail "Description & Craft Story" accordion renders the admin-authored `products.description` as real HTML (paragraphs, bold, lists, links) when it contains markup, instead of escaping it to literal text; plain-text/legacy descriptions still render exactly as before. Meta `<description>` tags (`PageController::products`/`product`) strip tags first so rich text never leaks raw markup into `<meta>`. `public/assets/js/product-detail.js` (`renderAccordions`), `public/assets/css/product-detail.css` (`.accordion-inner-body` rich-text styles), `app/Http/Controllers/PageController.php`.
12. Topbar mega menu now groups subcategories into separate columns by `subcategories.section` (confirmed live column, not yet in `sql/ecom.sql` — see docs/database.md). A subcategory with no section falls back into a column headed by the category name, so categories that never set `section` render exactly as before. `app/Models/Catalog.php::subcategories()`, `public/assets/js/data.js` (nav building).

## Performance (2026-10-16)
Image optimization (Cloudinary/Unsplash), hero/product preload, reserved heights, smaller boot payload, `Cache::flexible` catalog cache, fewer queries, versioned assets. Details, before/after numbers, stale-data behaviour and the production deployment checklist: [docs/performance.md](performance.md). Not done: Apache/Nginx cache headers (host unconfirmed), server-side rendering of the hero/product core, minification, catalog pagination.

## Orders & SEO (2026-11-12)
Account order history (paginated) + order details/tracking page (`/account/orders/{number}`) built on `order_timeline`, customer cancellation while `pending`, payment-method seam (`app/Payments`). Server-rendered product/listing content, canonical/robots/OG/Twitter meta, JSON-LD, `/sitemap.xml`, dynamic `/robots.txt`. Details and admin-side follow-ups: STOREFRONT_PRODUCTION_READINESS_PLAN.md §11–§12.

## Performance, deployment & admin spec (2026-11-12, Part 5)
- **Catalog data:** pages embed only the products they render (was the whole catalog). Listing, search and lookups are server endpoints.
- **Layout shift:** server-rendered nav, home sections, product options, account and checkout removed it (CLS ≤ 0.05 everywhere measured).
- **Deployment:** `storefront:deploy-check`, `.env.production.example` and STOREFRONT_DEPLOYMENT_CHECKLIST.md.
- **Delivery wording:** now in `storefront.delivery_copy` (owner decisions in plan §16).
- **Admin changes:** specified in docs/admin-order-integration.md. Not applied or verified (admin code not available).

## Security, policies & release prep (2026-11-12, Part 6)
Details: STOREFRONT_PRODUCTION_READINESS_PLAN.md §18.

**Done and tested:**
- **CSP:** no inline JavaScript anywhere (`script-src 'self'`, `script-src-attr 'none'`). The two CDN files are allowed only on `/bulk-order`, pinned with SRI.
- **Fixes:** an open redirect after Google sign-in, and `javascript:` links in requests.
- **Uploads:** content-checked and re-encoded with GD, random names, size, dimension and count limits.
- **Spam protection:** honeypot, link limit, per-contact and per-IP limits.
- **Contact form:** stores messages in `contact_messages`.
- **Policy pages:** drafts.
- **Delivery:** A$12 under A$299, free at A$299 or more; Australia only; no express service; no carrier or tracking claims.
- **Dates:** shown in Australia/Sydney (AEST/AEDT), stored in UTC.
- **Hero:** responsive, with a mobile source.
- **Return requests:** built behind a switch that is **off**.
- **Payments:** a written architecture plan for Stripe (future).
- **CI:** a browser smoke-test job.
- **Deploy-check:** updated.

**Test results:**
- PHPUnit (local MySQL test DB): 238/238.
- Browser smoke test: 51/51.
- Real-browser checks (upload, returns, 419): 13/13.

**Still blocked:**
- **Admin app integration** is a release blocker: orders, return review, contact messages and sourcing photos.
- **Owner approvals:**
  - returns policy and window;
  - wording of the policy pages, with legal review of terms and privacy;
  - contact details;
  - hero images.
- **DB owner:** must apply `sql/proposed_changes.sql` §4 before returns can be switched on.
- **Not yet run:** CI on GitHub.

## SEO audit & optimisation (2026-11-12, Part 7)
Details: [docs/SEO_AUDIT.md](SEO_AUDIT.md) and plan §19.

**Done and tested:**
- **Product discovery:** crawlable listing pages (`?page=N`, self-canonical, 404 past the end; links hidden on page 1 where infinite scroll runs). "You May Also Like" rotates through the category. A link-only crawl of the 405-product local catalog now reaches **405/405** products within 3 clicks (was 71/405).
- **robots.txt:** no longer hides the noindex on account and checkout.
- **Trailing slash:** 301 on any web server.
- **Listings:** keep the server `<title>` after scripts run; subcategory breadcrumbs show the category level.
- **New structured data:** CollectionPage/ItemList, shipping details from the confirmed rule, ContactPage/WebPage for approved pages, Organization contact only when configured, category share image, image sitemap.
- **deploy-check:** rejects a local or test `APP_URL`.

**Test results:**
- PHPUnit 255/255 (2182 assertions, 0 skipped).
- Browser SEO check: 54/54 (perf catalog) and 42/42 with 2 SKIP (seed DB, as CI).
- Smoke test: 51/51.
- Mobile performance: unchanged within 0–2.5% (local, synthetic).

**Still pending:**
- Google Search Console and Rich Results checks (need the owner and the live site).
- About page (no content).
- Policy pages approval.
- Ratings, return-policy and delivery-time markup (decisions).
- Field Core Web Vitals.
- **Admin integration** is still the release blocker.

## Final regression, payment review & release readiness (2026-11-12, Part 8)
Details: STOREFRONT_PRODUCTION_READINESS_PLAN.md §20 (final issue log §20.6). No application code changed.

**Verified:**
- **GitHub CI:** green. Run #2 on `c95ec27` passed both jobs: PHPUnit/JS and browser smoke + SEO.
- **PHPUnit:** 252/255, 0 skipped. The 3 errors are a Windows symlink permission issue; those tests pass on CI.
- **Other suites:**
  - JS 17/17;
  - smoke 51/51;
  - SEO 42/42 (seed) and 54/54 (405 products);
  - link-only crawl 405/405.
- **New customer journey test** `tests/browser/journey.mjs`: **32/32**. It covers registration through COD order, cancel, sourcing photo, newsletter and sign-out. Database rows are correct, stock is unchanged, nothing is `paid`. Added to the CI browser job; not yet run on GitHub.
- **Security, live:** headers, cookies, access control, redirects, 419 and 404 all as designed. No secrets in the repository.
- **Payments:** the Stripe-ready seam was confirmed. 8 design gaps for the future card path were added to `docs/payments-architecture.md`. Stripe and returns are still off.
- **Hero LCP** re-measured: home mobile 2.02 s (5.38 s in Part 5).

**Release blockers:**
1. Admin integration.
2. **New:** `composer audit` reports 8 advisories (Symfony, phpseclib). Patch updates are available but not yet applied.
3. Production deployment per the checklist.
4. Owner decision on the policy pages.

**New owner question:** is free shipping decided before or after the coupon discount? Today it's before.
