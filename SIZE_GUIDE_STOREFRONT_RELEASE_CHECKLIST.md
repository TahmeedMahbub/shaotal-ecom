# Size Guide Inch/CM — Storefront Release Checklist

## Data contract (no API; shared MySQL DB read directly by `Catalog::sizeGuide()`)
`GET /size-guides/{key}` → `{title, details, unit:'in'|'cm', attributes:[{id,name}], rows:{size:{attributeId:number}}}`.
`unit` = unit of the stored numbers (`size_guides.measurement_unit`; if the column is missing or invalid → legacy `size_guide_attributes.unit` → `cm`). Guides with no attributes or no values return 404 and the modal shows "unavailable". Per-attribute `unit` was dropped from the payload.

## Changed files
`app/Models/Catalog.php`, `public/assets/js/product-detail.js`, `public/assets/css/product-detail.css`, new `tests/Feature/SizeGuideUnitTest.php`. (`sections.css` needed no change.)

## Behaviour
Inch is shown on every open; INCH | CM toggle in the modal; values always derived from the stored original (no drift, 1000 toggles verified), max 2 decimals; missing/invalid cells show "—"; table scrolls horizontally inside the modal on narrow screens; products with no guide keep the button hidden.

## Deployment order
1. Back up, then run the Admin repo's `database/sql/2026-11-05_size-guide-dual-unit.sql` on the shared DB (manually).
2. Deploy Admin (saves need the column).
3. Deploy Storefront.
The Storefront is safe to deploy **before** the SQL/Admin (legacy fallback), but then every guide reads as its old attribute unit (cm), and Inch-first display is just conversion from that. Admin must not go first without the SQL.
**Caches:** the size guide endpoint isn't in `storefront.catalog.v2`; assets are mtime-versioned. No cache clearing needed.

## Verification performed
- Payload checked on the local MySQL scratch DB: existing cm guide (`unit:cm`), an Admin-shaped Inch guide with a missing cell, an empty guide (null→404), unknown key (404).
- Conversion logic run in Node: 36 in→91.44, 38.25 in→97.16, 55 cm→21.65, missing/invalid→"—", no drift after 1000 toggles.
- NOT run: `tests/Feature/SizeGuideUnitTest.php` (needs `pdo_sqlite`, absent on this machine; existing `ExampleTest` fails the same way). **Browser/mobile rendering not tested**; no browser tool was available.

## Production verification
Open a product with a guide: opens in Inch, toggle to CM and back (numbers return exactly), narrow the window to check scrolling, open a product without a guide (no Size Guide button).

## Remaining blockers
Run the new PHP test where SQLite is available; browser check on desktop and mobile; real Admin-created guide on production after the SQL runs.

---

## Update 2026-11-06 — ranges, per-attribute units, redesigned modal

**Payload (`GET /size-guides/{key}`)**: `{title, details, unit, attributes:[{id,name,unit}], rows:{size:{attributeId:"text"}}}`. Values are now **text** ("38", "71.1-76.2"), no longer cast to float (a range would have become its first number). `attributes[].unit` is each column's stored unit (mixed legacy guides); `unit` is the guide's stored unit with Admin's rule (all attributes share one recognised unit → that; else `measurement_unit`; else `cm`).

**Display rules** (`public/assets/js/size-guide-units.js`, the only place conversion lives; mirrors Admin `Measurement::display()`): default Inch; every value derived from the stored original (500 toggles verified, no drift); Inch = each range end converted then rounded to a whole number independently, shown `28"-30"` (both ends equal → `36"`); CM = max 2 decimals (`71.1-76.2`); empty/invalid/backwards range → "—". Nothing is saved.

**UI:** title "Size Guide" (guide name as subtitle), INCH | CM segmented control top-right of the header next to the close button (hidden until data is loaded, so no values or toggle flash while loading), warm beige tokens, restrained header, row separators only, sticky size-label column, horizontal scroll on narrow screens, 34px+ touch targets, `radiogroup`/`aria-checked`.

**Files:** `app/Models/Catalog.php`, `resources/views/pages/product-detail.blade.php` (header markup + script tag), `public/assets/js/size-guide-units.js` (new), `public/assets/js/product-detail.js`, `public/assets/css/product-detail.css`, `tests/js/size-guide-units.test.js` (new), `tests/Feature/SizeGuideUnitTest.php`. `sections.css` unchanged.

**Tests:** `node --test tests/js/size-guide-units.test.js` → 10/10 pass. PHP `SizeGuideUnitTest` (SQLite) not runnable here (no `pdo_sqlite`); the same payload cases were verified against the local MySQL scratch DB (cm legacy guide, Inch range guide, mixed-unit guide, unknown key → 404) and over HTTP (product page contains the new markup/scripts, endpoint 200/404, assets served).
**Browser rendering (desktop/mobile) is still NOT verified** — no browser tool available.

**Deployment:** Storefront can ship independently, but ranges only exist once the Admin range SQL (`2026-11-06_size-guide-range-values.sql`) has run; until then values are plain numbers and everything still works. Static assets are mtime-versioned, so no cache clearing.

---

## Bug fix 2026-11-06 — Size Guide trigger not clickable

**Root cause (reproduced in headless Chrome):** `product-detail.js` began with `if (!product) return;`, where `product` comes from the cached catalog (`storefront.catalog.v2`, up to ~6 min stale) — the page route checks the DB, the JS data comes from the cache. If the product is missing from that data (or an earlier render step throws), `setupSizeGuideModal()` never ran, leaving the static "Size Guide" button with no handler. Not CSS (no overlay/pointer-events/z-index issue found) and not the extension warning (`runtime.lastError` comes from a browser extension).
**Fix:** `PageController::product()` now reads the guide key from the DB (joined, active guides only) and renders it on the button (`data-guide-key`, `hidden` when none); `setupSizeGuideModal()` runs first, inside try/catch, binds once (`data-sg-bound` guard) with a delegated click listener, and no longer depends on catalog data. Added `.size-guide-trigger-btn[hidden]{display:none}`.
**Verified** with real mouse clicks over CDP on `fl-trouser-carbanard` (live data, read-only) at 1280px and 390px: opens, Inch default `28"-30"`, CM toggle `71.1-76.2`, closes via ✕; also with the catalog data stripped (stale-cache simulation); no console errors. Files: `PageController.php`, `product-detail.blade.php`, `product-detail.js`, `product-detail.css`.
**Not covered:** other browsers/real devices; keyboard/Escape-to-close (not part of the original modal).

---

## Update 2026-11-06 — measurement formatting: one decimal, truncated

All converted/displayed size guide measurements go through one formatter, `fmt1()` in `public/assets/js/size-guide-units.js`: exactly one decimal, extra digits **dropped (truncated), never rounded** (13.82→13.8, 14.21→14.2, 35.96→35.9, 37.04→37.0, 14→14.0). This replaces the earlier whole-number Inch rounding and the 2-decimal CM format; Inch shows e.g. `27.9"-30.0"`, CM `71.1-76.2`. Conversion itself is unchanged (stored original × or ÷ 2.54 first, then truncate; no drift). A `toFixed(6)` guard stops float noise from truncating an exact tenth down (e.g. 76.2/2.54 shows 30.0). Prices, quantities and other numbers don't use it (it's only called from the size guide table).
Tests: `node --test tests/js/size-guide-units.test.js` 9/9 pass, including the five required examples. Verified on `fl-trouser-carbanard` at 1280px and 390px: Inch `27.9"-30.0"  12.9"  37.0"`, CM `71.1-76.2  33.0  94.0`.
Note: Admin's `Measurement::display()` still rounds Inch to whole numbers and was not changed (Storefront-only task) — the two now differ.

