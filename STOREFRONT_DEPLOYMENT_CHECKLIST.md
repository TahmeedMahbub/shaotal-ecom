# Shaotal storefront: deployment checklist

_Part 5 (2026-11-12). Commands are for the **production server**, run from the app directory as the deploy user, unless a step says otherwise. Nothing here was run against production. The commands were checked locally for syntax and behaviour only, never against the production database. Template: [`.env.production.example`](.env.production.example). Config check: `php artisan storefront:deploy-check` (read-only, prints no secrets, exit 1 on a blocking problem)._

_Updated for Part 8 (2026-11-12): CI is green on GitHub; new item 0.14 (`composer audit`, a release blocker until the patch updates land); the release blockers are listed in plan §20.6._

_Updated for Part 6 (2026-11-12): `storage:link` and the PHP `gd` extension are now **required**, new `STOREFRONT_*` settings (§7), return requests stay **off**, and new post-deploy checks. **Release is still blocked** by the admin app integration (plan §17, Part 6 blockers): staff must be able to see and act on orders, sourcing/bulk requests and contact messages before launch._

## 0. Before the first deploy (once)
| # | Item | How |
|---|---|---|
| 0.1 | Web root is `public/` only | `.env`, `storage/`, `vendor/` and `sql/` must be outside the document root. |
| 0.2 | HTTPS with a valid certificate; HTTP → HTTPS redirect at the web server | Apache: `RewriteCond %{HTTPS} off` → `RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]`. Nginx: `return 301 https://$host$request_uri;` in the port-80 server. |
| 0.3 | Production `.env` created **on the server** from `.env.production.example` | `cp .env.production.example .env`, fill in the placeholders, then `chmod 640 .env`. Never commit it, never copy it to a laptop. |
| 0.4 | `APP_KEY` generated once | `php artisan key:generate`. Rotating it signs everyone out and invalidates password-reset links. |
| 0.5 | DB user has least privilege | `SELECT, INSERT, UPDATE, DELETE` on the shared schema. No DDL: the schema is SQL-first, owned with the admin app. |
| 0.6 | Mail (password-reset codes) | Real SMTP in `MAIL_*`, then a test reset to a staff address. |
| 0.7 | Behind a load balancer/CDN that terminates TLS? | Set `TRUSTED_PROXIES` (its IPs/CIDRs, or `*` if the app is reachable only through it). Otherwise Laravel sees plain http: cookies, HSTS and URLs go wrong. |
| 0.8 | Several app servers? | Use `CACHE_STORE=redis` and `SESSION_DRIVER=redis`. The checkout lock, rate limits and catalog cache must be shared across servers. |
| 0.9 | Permissions | Owner = deploy user, group = web server user: `chmod -R ug+rwX storage bootstrap/cache`, everything else read-only for the web server. |
| 0.10 | Owner decisions that change wording or behaviour | Delivery copy (`config/storefront.php` → `delivery_copy`, plan §16), policy-page approval (`STOREFRONT_APPROVED_PAGES`), contact details (`STOREFRONT_CONTACT_*`), returns policy and window, Stripe (future). See §7. Never fill these in without written owner approval. |
| 0.11 | PHP extensions | `gd` with JPEG, PNG and WebP support is **required**: sourcing-request photos are decoded and re-encoded with GD, and every upload is refused without it. Check: `php -r 'var_dump(function_exists("imagewebp"));'` → `true` (also reported by `storefront:deploy-check`). |
| 0.12 | Uploaded photos are served as static files only | `storage/app/public` is exposed at `/storage` by `storage:link`. Uploads are already re-encoded images with random `.jpg/.png/.webp` names, but as defence in depth make sure the web server never executes PHP there. Nginx: `location ^~ /storage/ { try_files $uri =404; }` placed before the `\.php$` block. Apache: `php_flag engine off` (or `RemoveHandler .php`) for that directory. |
| 0.13 | CI green on the release commit | `.github/workflows/tests.yml`: PHPUnit (MySQL) + JS, and the browser job (smoke, SEO, and since Part 8 the customer journey). Green on GitHub for `c95ec27` (run #2). The journey step runs from the next push on: check it before release. |
| 0.14 | No known-vulnerable dependencies | `composer audit` must report no advisories on the release commit. **Part 8 found 8** (Symfony 8.1.7–8.1.9, phpseclib 4.0.2), fixed by patch updates: `composer update -W "symfony/*" phpseclib/phpseclib`, then the full test suite and CI, then commit `composer.lock` (plan §20.3, RB2). |

## 1. Deploy
```sh
# 1. Rollback point (keep the previous release directory, or note the current commit)
git rev-parse HEAD > ../shaotal-previous-release.txt

# 2. Code + dependencies (no dev packages; optimised autoloader)
git fetch --tags && git checkout <release-tag-or-commit>
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# 3. Database: NO `php artisan migrate`. The schema is sql/ecom.sql, shared with the admin app.
#    Apply only SQL the owner has reviewed (e.g. sql/proposed_changes.sql), with a backup first:
#    mysqldump --single-transaction --routines hishabiz_ecom > ../backup-$(date +%F-%H%M).sql

# 4. Assets: nothing to build. The storefront's CSS/JS are plain files in public/assets, versioned by mtime.
#    (Vite is only used by the unused welcome.blade.php; run `npm ci && npm run build` only if that changes.)

# 5. Laravel caches (re-run on every deploy; config:cache freezes .env values)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Storage link: REQUIRED. Sourcing-request photos are stored in storage/app/public/sourcing-requests and their
#    saved URLs point to /storage/...; without the link staff get 404 for every photo. Once per server (re-run is safe).
php artisan storage:link        # creates public/storage -> storage/app/public (and nothing else in public/)

# 7. Catalog cache: rebuild from the database now (otherwise within ~1–6 minutes)
php artisan storefront:refresh-catalog

# 8. Configuration gate: must print "No blocking problems."
php artisan storefront:deploy-check

# 9. PHP-FPM / OPcache: reload so new code is picked up
sudo systemctl reload php8.4-fpm   # or the host's equivalent
```

## 2. Web server: compression and caching (performance)
`public/.htaccess` already contains guarded `mod_deflate` and `mod_headers` rules for Apache. **Nginx** equivalent, inside the site's `server {}`:
```nginx
root /path/to/shaotal/public;
index index.php;
gzip on;
gzip_types text/plain text/css application/javascript application/json application/xml image/svg+xml;
gzip_min_length 1024;

location /assets/ {
    try_files $uri =404;
    add_header Cache-Control "public, max-age=31536000, immutable";   # URLs carry ?v=<mtime>
}
location / { try_files $uri $uri/ /index.php?$query_string; }
# Trailing slash → no slash. Optional since Part 7: the app's RedirectTrailingSlash middleware already 301s these
# (Apache's .htaccess also does). Keeping it saves a PHP request per redirect.
rewrite ^/(.+)/$ /$1 permanent;
location ~ \.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php8.4-fpm.sock; }
location ~ /\.(?!well-known) { deny all; }
```
- **OPcache:** `opcache.enable=1`, `opcache.validate_timestamps=0` (then reload FPM on every deploy), `opcache.memory_consumption=192`.
- Measured locally (plan §14, throttled mobile profile, local perf catalog of 405 products):
  - Serving CSS/JS gzipped cut transfer from ~300 KB to ~65 KB per page.
  - Mobile LCP dropped from ~1.7–2.0 s to ~0.6–0.9 s on listing, request, account and checkout pages, and from 2.6 s to 2.2 s on the product page.
  - This is the single biggest remaining win for phones. The numbers are local, not production.

## 3. Post-deployment verification (2–5 minutes)
```sh
B=https://<public-domain>
curl -sI $B/ | grep -iE "HTTP/|strict-transport|content-security|x-frame"     # 200, HSTS, CSP, X-Frame-Options
curl -sI http://<public-domain>/ | grep -i location                             # redirects to https
curl -s  $B/robots.txt | grep Sitemap                                           # Sitemap: https://<domain>/sitemap.xml
curl -sI $B/robots.txt | grep -ic set-cookie                                    # 0 (public, cookie-free)
curl -s  $B/sitemap.xml | head -5                                               # sitemapindex with https locs
curl -s  $B/products | grep -o '<link rel="canonical"[^>]*>'                    # https canonical
curl -sI $B/account | grep -iE "x-robots-tag"                                   # noindex, nofollow
curl -sI $B/nonexistent-page | head -1                                          # 404 branded page, no stack trace
curl -sI "$B/products/?category=fashion" | grep -iE "HTTP/|location"            # 301 → $B/products?category=fashion
curl -sI http://www.<domain-or-apex>/ | grep -i location                         # the non-preferred host 301s to APP_URL
curl -s  $B/sitemap-products-1.xml | grep -c "<image:loc>"                      # > 0: product images listed
curl -s  "$B/products?page=2" | grep -o '<link rel="canonical"[^>]*>'           # self-canonical ...?page=2 (if > 8 products)
curl -sI -H 'Accept-Encoding: gzip' "$B/assets/css/sections.css" | grep -iE "content-encoding|cache-control"
```
```sh
curl -sI $B/bulk-order | grep -i content-security   # script-src lists ONLY 'self' + the two pinned CDN files
curl -sI $B/ | grep -i content-security             # script-src 'self'; script-src-attr 'none' (no CDN, no unsafe-inline)
curl -s  $B/returns | grep -o '<meta name="robots"[^>]*>'   # noindex while the page is not approved
curl -sI $B/storage/ | head -1                      # 403/404: the directory itself is not listable
```
Then in a browser, on a phone too:
1. Browse home → category → product → add to cart → sign in → checkout → place a COD order.
2. Open My Orders and check the new order's page: dates show AEST/AEDT. No "Request a return" form (returns are off).
3. `/request`: send a test sourcing request with one photo. Open the stored photo URL from the admin app (or the `sourcing_request_images.image_url` value): it loads. Mark the test request as spam/closed in the admin app afterwards.
4. `/contact`: send a test message; it appears in `contact_messages` (admin app).
5. `/bulk-order`: the product picker (Select2) works. If it does not, the CDN file changed and failed its SRI check: see plan Part 6 §CSP.
6. Check the console on each page: no errors and no CSP violations.

Check `storage/logs/laravel-*.log` for new errors.

## 4. Rollback
1. `git checkout $(cat ../shaotal-previous-release.txt)`, then `composer install --no-dev --optimize-autoloader`, then repeat §1 steps 5, 7 and 9.
2. Database: storefront releases contain no schema changes. If owner-approved SQL was applied in this release, restore it from the backup taken in §1.3, coordinated with the admin app.
3. Sessions survive a rollback as long as `APP_KEY` is unchanged.

## 5. Logs and personal data
- `LOG_LEVEL=warning`, daily files kept 14 days (`LOG_DAILY_DAYS`).
- The storefront logs no request bodies.
  - Passwords are only ever compared with `password_hash` and are never logged.
  - Laravel never flashes `password` fields back to the session.
  - OTPs and reset codes are stored hashed.
  - There are no payment secrets yet (COD only; Stripe will need its keys in `.env` only).
- `report($e)` in Google sign-in logs the exception message, never the token.
- `APP_DEBUG=false` is required: with debug on, error pages expose settings. `storefront:deploy-check` fails on it.

## 6. What `storefront:deploy-check` verifies
- **Hard failures:**
  - `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` set, https `APP_URL`.
  - Secure session cookie.
  - A session driver that exists (not `database`: no table in `sql/ecom.sql`).
  - `CACHE_STORE` = file or redis.
  - `APP_URL host` (Part 7) is not local or test (`localhost`, `127.*`, `0.0.0.0`, `*.test`, `*.local`, `*.localhost`, `*.invalid`): canonicals, the sitemap and JSON-LD are built from it.
  - No `public/robots.txt`, `.env` or `composer.json` inside the web root.
  - `public/storage` exists and is **only** the `storage:link` symlink to `storage/app/public` (missing, a copy, or a link to the whole `storage/` all fail).
  - Writable `storage/framework/{cache,sessions,views}`, `storage/logs`, `storage/app/public` and `bootstrap/cache`.
  - PHP `gd` loaded with JPEG, PNG and WebP functions.
  - `STOREFRONT_DISPLAY_TIMEZONE` is a valid time zone name.
  - `STOREFRONT_APPROVED_PAGES` lists only known pages (`contact, shipping, returns, terms, privacy`).
  - `STOREFRONT_RETURNS_WINDOW_DAYS`, when set, is a positive number.
  - Returns switched on while the return-request tables are missing. The database is queried only when the switch is on.
- **Warnings:**
  - Queue connection, debug log level, log/array mailer.
  - DB session zone, display zone other than `Australia/Sydney`, `TRUSTED_PROXIES` empty.
  - Policy pages not yet approved, no public contact details.
  - Returns switched on (confirm the policy approval and admin review before leaving it on).
- **Not checked** (do these by hand): HTTPS/redirects, the web server not executing PHP under `/storage`, SMTP delivery, CI status, `composer audit` (0.14), owner/legal approvals.
- **Part 8 run with simulated production settings** (`APP_ENV=production`, https `APP_URL`, secure cookie, file cache and session, smtp, warning logs): every row OK except `public/storage` FAIL (`storage:link` not run on that machine; deploy step 6). Expected WARNs: `TRUSTED_PROXIES`, policy pages not approved, no contact details.

## 7. Storefront settings added in Part 6
All are read in `config/storefront.php`. Defaults are safe for launch. Remember `php artisan config:cache` after any change.

| Variable | Default (unset) | Meaning | Who decides |
|---|---|---|---|
| `STOREFRONT_DISPLAY_TIMEZONE` | `Australia/Sydney` | Zone for dates shown to customers (AEST/AEDT label). Storage stays UTC (`APP_TIMEZONE=UTC`, `DB_TIMEZONE=+00:00`). | Owner (decided: Sydney) |
| `STOREFRONT_APPROVED_PAGES` | empty | Comma-separated subset of `contact,shipping,returns,terms,privacy`. Unlisted pages show "being finalised", send `noindex` and are left out of the sitemap. | Owner; `terms`/`privacy` also legal review |
| `STOREFRONT_CONTACT_EMAIL` / `_PHONE` / `_HOURS` | empty | Public contact details on `/contact`. Empty = form only (messages go to `contact_messages`). | Owner (not provided yet) |
| `STOREFRONT_RETURNS_ENABLED` | `false` | Shows the "Request a return" form on the customer's own delivered orders. **Keep false** (see below). | Owner + DB owner + admin team |
| `STOREFRONT_RETURNS_WINDOW_DAYS` | unset = no limit | Days after the recorded delivery time during which a request may be sent. Do **not** write it as an empty value (`=`): that reads as 0 and deploy-check fails. | Owner (not decided) |

Unchanged code constants (not env): shipping A$12 below A$299, free at A$299 or more (`shipping_flat`, `free_shipping_over`); delivery wording (`delivery_copy`); return reasons (`returns.reasons`); payment methods (`cod` only).

**Switching on return requests: all four are required, in this order.**
1. The owner approves a returns policy in writing, including whether there is a time limit. The `/returns` page wording is then updated to match and approved (`STOREFRONT_APPROVED_PAGES`). The storefront does not invent eligibility rules: today it only enforces "own order, delivered, once", plus the optional window.
2. The DB owner reviews and applies `sql/proposed_changes.sql` §4 (`order_return_requests`, `order_return_request_events`) to the shared database, with a backup first. The storefront never runs DDL.
3. The admin app can list requests and move them `requested → approved/rejected → completed`, recording each change as an event ([docs/admin-order-integration.md](docs/admin-order-integration.md) §7). Refunds and restocking stay manual, staff-side.
4. Then set `STOREFRONT_RETURNS_ENABLED=true`, run `php artisan config:cache`, run `storefront:deploy-check` (expect the returns WARN, not FAIL), and send one test request on a staff test order.

To switch it off again: set it to `false` and run `config:cache`. Existing requests stay in the tables for staff.

## 8. After launch: search engines (Part 7)
- **Preferred domain:** choose www or non-www **before** launch. Set `APP_URL` to it, and 301 the other host and `http` to it at the web server or DNS.
- **Google Search Console** (needs the owner's account; nothing has been set up or verified):
  1. Domain property, verified by DNS TXT record.
  2. Submit `https://<domain>/sitemap.xml`.
  3. URL Inspection on home, a category, a product.
  4. Rich Results Test on a product.

  Step-by-step and expected warnings: [docs/SEO_AUDIT.md](docs/SEO_AUDIT.md) §12. Don't add verification tokens to the code without the owner providing them.
- Policy pages stay noindex until listed in `STOREFRONT_APPROVED_PAGES` (§7).
