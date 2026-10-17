# Progress

_Last updated: 2026-10-16_

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
5. **Stock is validated but not decremented** at order time (unclear whether the admin app does it). Decide who decrements.
6. **Sourcing images** are stored on the Laravel `public` disk (`php artisan storage:link`, already run); switch to Cloudinary upload when credentials exist.
7. `.env`: `CACHE_STORE`/`QUEUE_CONNECTION=database` but no such tables in the SQL; the storefront uses the `file` cache store explicitly and no queues. `sessions` use files.
8. Footer links Shipping/Returns/FAQ/Size Guide and the launch-offer strip (ends 2026-10-15, static in design) have no designed pages/data; omitted or left as design.
9. Brand renamed to "Shaotal" everywhere user-visible (config, titles, footer, JS fallbacks). Internal identifiers keep the old prefix on purpose: localStorage keys `bongo_*` (renaming would empty customers' saved carts/wishlists) and `window.Bongo*` globals. `design/` prototype and `.env` `APP_NAME` (still `Laravel`) were not changed.
10. Category slugs `home-decor` and `preorder`-availability are used by home sections as in the design.

## Performance (2026-10-16)
Image optimization (Cloudinary/Unsplash), hero/product preload, reserved heights, smaller boot payload, `Cache::flexible` catalog cache, fewer queries, versioned assets. Details, before/after numbers, stale-data behaviour and the production deployment checklist: [docs/performance.md](performance.md). Not done: Apache/Nginx cache headers (host unconfirmed), server-side rendering of the hero/product core, minification, catalog pagination.
