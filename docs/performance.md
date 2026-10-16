# Storefront performance

Phase 1 (audit) and Phase 2 (implementation) results. All numbers are lab measurements on a local machine — see "Test conditions".

## What changed

| # | Change | Where |
|---|---|---|
| 1 | Responsive image URLs: Cloudinary gets `f_auto,q_auto,c_limit,w_N`, Unsplash gets `w`/`q`/`auto=format`. Stored URLs and other hosts are never touched; Cloudinary URLs that already carry a transformation are left alone. PHP and JS implementations produce identical strings (14 cases checked, `tests/Unit/ImgTest.php`). Cards, gallery and drawers use `srcset`/`sizes`. | `app/Support/Img.php`, `window.imgUrl` / `imgSrcset` in `public/assets/js/data.js`, `app.js`, `product-detail.js`, `pages/*.js`, `index.blade.php` |
| 2 | Hero (LCP) image: `<link rel=preload fetchpriority=high>` with the same optimized URL the page uses; other slides load after `window.load`. Product page preloads its first gallery image; first 4 listing cards load eagerly. | `layouts/store`, `index`, `product-detail` blades, `PageController::firstGalleryImage`, `renderHero` in `app.js` |
| 3 | Reserved heights for the JS-rendered home sections (popular products, category grid) so content below does not jump. Only applied when the server confirms there is content. | end of `public/assets/css/sections.css`, `index.blade.php` |
| 4 | Boot payload: `<script type="application/json" id="boot-data">` instead of a JS string literal; `generalImages` removed (derived in the browser); `fullDesc` and `specs` sent only for the product page being viewed. | `layouts/store.blade.php`, `Storefront::boot`, `Catalog`, `data.js` |
| 5 | `Cache::flexible('storefront.catalog.v2', [60, 300])` (see "Cache behaviour"). | `Storefront::catalog` |
| 6 | `/products` no longer runs 2 unused queries. Signed-in pages: customer+address in one query, cart+items in one query (5 → 3 queries). Same data returned. | `PageController`, `Storefront::customer` / `cart` |
| 7 | Static assets versioned by file mtime (`?v=…`). | `app/Support/Assets.php`, layout + page blades |
| 8 | Fixed a latent bug found on the way: `stdClass` values in the cached catalog came back as `__PHP_Incomplete_Class` (Laravel 13 `serializable_classes=false`), which broke colour-specific images on every cached request. The catalog now contains plain arrays only. Also fixed `href=".//products"` links on the home page. | `Catalog::shape`, `index.blade.php` |

Not changed: authentication, checkout, coupons, orders, payments, stock handling, database schema. Checkout still prices from uncached queries (`Checkout::priceLines`).

## Results (median of 3 cold-cache runs; mobile = Slow 4G 150 ms RTT / 1.6 Mbps / 4× CPU; desktop = 10 Mbps / 40 ms)

Synthetic catalog of 37 products (217 variants, Cloudinary demo images), served by PHP's built-in server through a gzip proxy. Before → after:

| Page | Mode | LCP | CLS | FCP | Transferred | Images |
|---|---|---|---|---|---|---|
| Home (guest) | mobile | 17.7 s → **1.27 s** | 0.003 → 0.003 | 1.12 → 0.99 s | 3368 → 373 KB | 3217 → 221 KB |
| Home (guest) | desktop | 2.51 s → **0.67 s** | 0.245 → **0** | 0.58 → 0.58 s | 3368 → 324 KB | 3217 → 173 KB |
| Home (signed in) | mobile / desktop | 17.3 s → 1.81 s / 2.83 → 0.42 s | 0.003 → 0.003 / 0.245 → 0 | | 3370 → 424 / 350 KB | |
| /products | mobile | 8.66 s → **1.79 s** | 0 → 0 | 0.79 → 0.82 s | 2423 → 290 KB | 2267 → 134 KB |
| /products | desktop | 1.28 s → **0.71 s** | 0.08 → 0.08 | 0.58 → 0.54 s | 2422 → 210 KB | 2267 → 55 KB |
| Product page (guest) | mobile | 8.05 s → **1.44 s** | 0 → 0 | 0.97 → 0.97 s | 2322 → 252 KB | 2160 → 88 KB |
| Product page (guest) | desktop | 1.50 s → **0.63 s** | 0.052 → 0.052 | 0.58 → 0.59 s | 2322 → 228 KB | 2160 → 64 KB |
| Product page (signed in) | mobile / desktop | 13.1 s → 1.85 s / 2.14 → 0.46 s | 0.025 → 0 / 0.052 → 0.052 | | 2431 → 289 / 2430 → 240 KB | |

Other metrics:
- **Total blocking time** is 0 on desktop and 100–250 ms on throttled mobile (unchanged by this work; it is `app.js` execution).
- **HTML size** (home, gzip not applied): 130.9 KB → 72.5 KB. With 500 products: 1.42 MB → 0.68 MB.
- **Database queries:** catalog cache miss = 12 queries (unchanged, 34–67 ms locally); cache hit = 0. Signed-in boot: 5 → 3 (4 → 3 with an empty cart). `/products` no longer runs the 2 unused queries. EXPLAIN shows every one of these queries uses an index.
- **Server time:** 130–150 ms per request in this environment, dominated by PHP-in-dev-server cost, not the app (OPcache off, no caches built). Server TTFB did not change measurably (e.g. home 133.9 → 142.3 ms median, noise level) — these changes help the browser side, not PHP time. OPcache + framework caches (below) were measured separately: 194 ms → 130 ms per cold process.
- **Not helpful, not kept:** loading Google Fonts non-blocking (preload + `media=print` swap) gave no measurable FCP gain (0.99 → 0.94 s mobile, within noise), so it was reverted.

### Test conditions and the 1-second target
- Measured with headless Chrome (CDP throttling), local PHP dev server, loopback network, Cloudinary demo images from the real Cloudinary CDN, Google Fonts from the real internet.
- **Desktop: ~1 s target met** (LCP 0.4–0.7 s on every page tested).
- **Mobile Slow 4G: 1 s target not met** — LCP 1.1–1.9 s (FCP ≈ 0.8–1.1 s). The server-side figure on the real host (PHP-FPM + OPcache, nearby database) was not measured; the remote database adds about 51 ms per query round trip on a cache miss.

## Remaining bottlenecks
1. Mobile LCP is gated by JavaScript: the hero, cards and product page content are rendered by `app.js` (124 KB, 25 KB gzipped) after render-blocking CSS (162 KB, 25 KB gzipped) and the Google Fonts stylesheet. Fix would be server-rendering the first hero slide and product page core (larger change).
2. Layout shift remains on `/products` desktop (0.08, filter lists filled by JS) and the product page desktop (0.052); both are below the 0.1 "good" threshold.
3. No minification/bundling (no build step in the project); `sections.css` is 95 KB and contains every page's styles.
4. The whole catalog is embedded in every page (≈52 KB for 37 products, ≈0.5 MB for 500). Past a few hundred products this needs server-side pagination/search.
5. Static assets are only cache-busted, not cacheable until the web server sends long cache headers (see Apache section).

## Cache behaviour and stale data
- Key `storefront.catalog.v2` on the **file** cache store. It holds only public catalog data (products, categories, hero, brands, settings) — never cart, wishlist, account or order data. Customer data is built per request in `Storefront::boot()`.
- Fresh for 60 s. After that the previous copy is served for up to 5 more minutes while one request rebuilds it after sending its response (`Cache::flexible`); later than 6 minutes the next request rebuilds inline.
- The admin is a separate app and cannot invalidate this cache, so **admin edits (prices, stock flags, images, hero) can appear up to ~6 minutes late**. Prices at checkout are not affected: `Checkout::priceLines` always reads the database. Add-to-cart availability on the page can lag the same way; the server re-validates stock when the order is placed.
- Force a refresh: `php artisan tinker --execute="Cache::store('file')->forget('storefront.catalog.v2'); Cache::store('file')->forget('illuminate:cache:flexible:created:storefront.catalog.v2');"`. If the admin app should clear it, it needs a shared signal (e.g. bump a `site_settings` value and include it in the key) — not implemented.
- `.env` sets `CACHE_STORE=database` but there is no cache table in the schema; the code uses the `file` store explicitly, so this does not matter.

## Deployment checklist (production only — nothing here was applied)
```bash
# 1. dependencies / autoloader
composer install --no-dev --optimize-autoloader

# 2. .env (production values)
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
SESSION_SECURE_COOKIE=true      # only if the site is served over HTTPS
# keep CACHE_STORE as is; the catalog uses the file store explicitly

# 3. build Laravel's caches (re-run on every deploy; undo with: php artisan optimize:clear)
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
# (or all four: php artisan optimize)

# 4. make sure storage is writable (file cache + sessions + logs)
chmod -R ug+rw storage bootstrap/cache
```

OPcache (php.ini of the production PHP; on Laragon the line is `;zend_extension=opcache` in `C:\laragon\bin\php\php-8-4-5\php.ini`):
```ini
zend_extension=opcache
opcache.enable=1
opcache.memory_consumption=192
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0   ; then reload PHP-FPM / Apache after each deploy
; if you cannot reload on deploy, use validate_timestamps=1 and revalidate_freq=60 instead
realpath_cache_size=4096K
realpath_cache_ttl=600
```
Measured effect (cold process per request, local): 194 ms with nothing → 149 ms with OPcache → 130 ms with OPcache + config/route/event caches. Production numbers will differ.

### Apache compression and cache headers — not applied
`public/.htaccess` is Laravel's default, and the hosting stack (Apache vs Nginx, which modules) is **not confirmed**, so no rules were added. If the host is Apache with `mod_deflate`, `mod_expires` and `mod_headers`, this can be appended to `public/.htaccess` (CSS/JS can safely be cached for a year because their URLs now change with the file; images/logo are not versioned in every place, so keep those short):
```apache
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json image/svg+xml
</IfModule>
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css "access plus 1 year"
  ExpiresByType application/javascript "access plus 1 year"
  ExpiresByType image/png "access plus 1 week"
</IfModule>
```
On Nginx the equivalent is `gzip on; gzip_types …;` and `location ~* \.(css|js)$ { expires 1y; }`. If a CDN/proxy already compresses, skip compression here.

## Repeatable measurement method
The profiling scripts are deliberately **not** in the repo. They live in the Claude session scratchpad (`…\scratchpad\`) and use `puppeteer-core` against the installed Chrome:
- `gen_perf.py <n>` builds a synthetic n-product catalog SQL (load into a scratch MySQL database; never the shared one).
- `vitals.js <url> <runs> [mobile|desktop]` — median TTFB/FCP/LCP/CLS/TBT, bytes and request counts with CDP throttling (`AUTH=1` signs in as the test customer first).
- `cls2.js <url>` — layout-shift sources at 1350/820/412 px. `ttfb.js` — server response times over plain HTTP. `regress.js [base] [mobile]` — 22 functional checks (home, search, wishlist, cart, listing filters, product page, login, checkout order, account, bulk/request pages) plus console/network error collection.
- Run the app against a local copy of the database, take 3+ runs per page, compare medians. Re-run after any image, script or layout change.
