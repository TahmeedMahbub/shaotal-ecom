# Session handoff — after Part 5 of 8

_2026-11-12. Detail: [STOREFRONT_PRODUCTION_READINESS_PLAN.md](STOREFRONT_PRODUCTION_READINESS_PLAN.md):
- §11 Part 3, §12 Part 4
- §14 performance, §15 admin and production config, §16 delivery and time zone
- §17 Part 6 start

Deployment: [STOREFRONT_DEPLOYMENT_CHECKLIST.md](STOREFRONT_DEPLOYMENT_CHECKLIST.md). Admin spec: [docs/admin-order-integration.md](docs/admin-order-integration.md)._

## State of the working tree
- Branch `main`, based on `fcc3a44 Part 1 & 2`. **Nothing is committed or pushed.** All Part 3, 4 and 5 changes are uncommitted. `public/robots.txt` is deleted (replaced by a route).
- **Local `.env`:** created from `.env.example` + `key:generate` (gitignored, SQLite default, no remote credentials). It was not changed for production settings; `storefront:deploy-check` correctly fails on it.
- **Database:** no migration or schema change. The remote `hishabiz_ecom` was never contacted. All DB work was on `127.0.0.1`.

## Local environment on this Mac
- `composer install` (lockfile only). MySQL 8.4 via Homebrew is running (`brew services stop mysql@8.4` to stop it).
- **`shaotal_storefront_test`** (PHPUnit). Rebuild it with the CI recipe:
  ```sh
  M=/opt/homebrew/opt/mysql@8.4/bin/mysql
  $M -uroot -h127.0.0.1 -e "CREATE DATABASE shaotal_storefront_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
  for f in sql/ecom.sql sql/google_login.sql tests/Support/ci-schema-drift.sql sql/local_test_seed.sql; do $M -uroot -h127.0.0.1 shaotal_storefront_test < $f; done
  ```
- **`shaotal_perf_test`** (kept for Part 6 comparisons): the same four files, then
  `php tests/Support/perf-catalog-seed.php 400 | $M -uroot -h127.0.0.1 shaotal_perf_test`.
  It holds `perf@example.test` / `Password123` with one order (created by `perf-server.php`).

## How to verify
| Command | Last result |
|---|---|
| `composer test` (MySQL) | **206/206**, exit 0 |
| `php vendor/bin/phpunit` (SQLite) | 57 pass, 149 skipped, 0 fail |
| `node --test tests/js/api-client.test.js tests/js/size-guide-units.test.js` | 17/17 |
| Pint on all changed and new PHP files | pass |
| `node tests/browser/smoke.mjs` (server on perf DB, see below) | **37/37** |
| `node tests/browser/perf.mjs` + `php tests/Support/perf-server.php` | before/after in plan §14, raw data in `docs/perf/` |

**Serve for browser runs** (run from `public/`; gzips PHP output like production; add `PERF_GZIP_STATIC=1` to gzip CSS/JS too):
```sh
cd public && PHP_CLI_SERVER_WORKERS=4 APP_DEBUG=false DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=shaotal_perf_test \
  DB_USERNAME=root DB_PASSWORD= CACHE_STORE=file SESSION_DRIVER=file APP_URL=http://127.0.0.1:8124 \
  php -S 127.0.0.1:8124 ../tests/Support/perf-router.php
```

## What Part 5 changed (short)
- **Catalog payload:**
  - Pages embed only the products they render; listing, search and lookups use `/catalog/feed|search|products` (public, cookie-free).
  - A guest's cart is never dropped by a missing lookup.
- **Server-rendered on load:** nav, home popular/categories/hero (mobile source), product options/accordions/thumbnails, account view, checkout addresses. CLS is ≤ 0.05 everywhere measured.
- **Catalog cache:** versioned (`site_settings.catalog_version` admin hook, `storefront:refresh-catalog`), with a stampede lock.
- **Security fixes found:**
  - All throttled routes shared one per-IP bucket (catalog browsing could block sign-in). Fixed with per-route prefixes.
  - robots/sitemap were `public` with a session cookie. They are now cookie-free.
- **Production:**
  - `storefront:deploy-check`, `.env.production.example`, deployment checklist.
  - Secure-cookie default, `TRUSTED_PROXIES`, https URLs, MySQL session in UTC.
  - Guarded gzip and cache rules in `public/.htaccess`.
- **Delivery wording:**
  - Moved into `storefront.delivery_copy`.
  - "A$299" now uses the A$100 threshold actually charged.
  - The other claims are unchanged and listed for the owner.
- **Display time zone:** `STOREFRONT_DISPLAY_TIMEZONE`, default UTC.

## Not verified / blocked
- **Admin app not on this machine.** Admin → storefront sync and the admin-side transition rules are **specified, not verified** (the spec includes 8 manual steps).
- Performance numbers are local (PHP built-in server, synthetic 405-product catalog, simulated mobile). There is no production or field data and no INP. Unthrottled desktop LCP rose by ~60 ms with no identified cause (plan §14.2).
- Not tested: real devices, production HTTPS/proxy, Apache/Nginx compression, CI on GitHub.

## Owner decisions waiting
- Delivery copy, threshold and NZ (plan §16)
- Display time zone
- Returns policy and flow
- Hero images without a fixed width or with a mobile image (F8)
- Policy pages (E1)
- Stripe (H2/H3)
- Line endings (I7) and schema drift (I8)

## Start Part 6 here
Plan §17:
1. Production `.env` + `deploy-check` green + server compression/caching (F5).
2. Apply and verify the admin spec.
3. Owner decisions.
4. A12 CSP nonces, A10 SRI, A7 upload limits, browser smoke in CI.
