# Shaotal storefront: deployment checklist

_Part 5 (2026-11-12). Commands are for the **production server**, run from the app directory as the deploy user, unless a step says otherwise. Nothing here was run against production. The commands were checked locally for syntax and behaviour only, never against the production database. Template: [`.env.production.example`](.env.production.example). Config check: `php artisan storefront:deploy-check` (read-only, prints no secrets, exit 1 on a blocking problem)._

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
| 0.10 | Owner decisions that change wording or behaviour | Delivery copy (`config/storefront.php` → `delivery_copy`, plan §16), `STOREFRONT_DISPLAY_TIMEZONE`, returns policy, Stripe. |

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

# 6. Storage link: not needed for the storefront pages. Run it only if sourcing-request uploads must be public.
# php artisan storage:link

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
# Trailing slash → no slash (Apache does this in .htaccess)
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
curl -sI -H 'Accept-Encoding: gzip' "$B/assets/css/sections.css" | grep -iE "content-encoding|cache-control"
```
Then in a browser, on a phone too:
1. Browse home → category → product → add to cart → sign in → checkout → place a COD order.
2. Open My Orders and check the new order's page.
3. Check the console: no errors.

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
  - No `public/robots.txt`, `.env`, `composer.json` or `storage` inside the web root.
  - Writable `storage/framework/{cache,sessions,views}`, `storage/logs` and `bootstrap/cache`.
- **Warnings:**
  - Queue connection, debug log level, log/array mailer.
  - DB session zone, display zone not set, `TRUSTED_PROXIES` empty.
