# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**Bongo Curated** storefront (artisan goods curated for Australia, currency A$). Laravel 13 / PHP ^8.3. The prepared design in `design/` is ported to Blade (`resources/views/pages`, layout `layouts/store`); its CSS/JS are in `public/assets`. Catalog/nav/hero are read from MySQL by `App\Models\Catalog`/`Storefront` and injected as `window.__BOOT__`; `public/assets/js/data.js` adapts it for the design scripts. Pricing/stock/coupons/orders are computed server-side in `App\Models\Checkout`. Status and blockers: [docs/PROGRESS.md](docs/PROGRESS.md). Database: [docs/database.md](docs/database.md), [.claude/rules/database.md](.claude/rules/database.md). Proposed (unexecuted) schema/data changes: `sql/proposed_changes.sql`. Performance work, catalog cache (`storefront.catalog.v2`, up to ~6 min stale) and deployment checklist: [docs/performance.md](docs/performance.md). Image URLs go through `App\Support\Img` / `window.imgUrl` (keep PHP and JS in sync).

## Working rules

- **Preserve the existing design.** `design/` (static HTML/CSS/JS prototype) is the visual spec. Port pages to Blade 1:1 — same markup, classes and tokens (`design/assets/css/variables.css`). Don't restyle or redesign.
- **SQL-first database, no migrations.** `sql/ecom.sql` is the source of truth and is shared with a separate admin app. Never add or edit migrations or alter the schema; if it must change, say so and let the user update the SQL.
- **Simple MVC.** Route → controller → model/query → Blade view. No repository/service layers or abstractions unless clearly needed.
- **Reusable components.** Shared UI (header, footer, product card, etc.) goes in Blade components/partials, not copy-pasted per page.
- **Efficient queries.** Select only needed columns, eager-load or join to avoid N+1, paginate lists, use existing indexes (see docs/database.md).
- **Minimal tokens.** `design/assets/js/data.js` (~2.8k lines), `app.js` (~3.1k lines) and `sql/ecom.sql` are large — grep or read ranges, don't read whole files.

## Commands

- `composer setup` — install, copy `.env`, key, migrate, npm install/build (skip the `migrate` step here; schema is SQL-first).
- `composer dev` (`php artisan dev`) — dev processes; `php artisan dev:list` lists them.
- `composer test` / `php artisan test --filter=Name` / `php artisan test tests/Feature/ExampleTest.php`. PHPUnit with in-memory SQLite (`phpunit.xml`).
- `vendor/bin/pint` — code style. `npm run dev` / `npm run build` — Vite only.

## Testing locally

The `.env` DB is remote. For local testing create a scratch DB (`shaotal_local`) from `sql/ecom.sql` + `sql/local_test_seed.sql` and run with env overrides, e.g. `DB_HOST=127.0.0.1 DB_DATABASE=shaotal_local DB_USERNAME=root DB_PASSWORD= CACHE_STORE=file php artisan serve`.

## Environment notes

- `.env` uses MySQL (`DB_DATABASE=hishabiz_ecom`) on a **remote host**, not local. Treat it as live: no write or destructive queries without explicit approval. `.env.example` defaults to SQLite.
- `.env` sets `CACHE_STORE=database` and `QUEUE_CONNECTION=database`, but `sql/ecom.sql` has no `cache`/`jobs` tables (only the default Laravel migrations do). Resolve before relying on cache/queue.
- Laravel Boost is not installed.
