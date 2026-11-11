# Shaotal Storefront — Page & Flow Audit

Read-only inspection of the `shaotal` repo (Laravel 13). No application code, database or data was modified; this file is the only addition.
Sources: `routes/web.php` (+ `php artisan route:list`), `bootstrap/app.php`, all 7 controllers, `resources/views`, `public/assets/js/{app,data,products,product-detail}.js`, `public/assets/js/pages/*.js`.

## 0. How the app is built (affects every table below)

- **Only one route file is in use** (`routes/web.php`); `routes/console.php` is for commands. **No `api/*` routes. No custom middleware**, no route groups, no `auth` middleware. `bootstrap/app.php` registers nothing custom; only `throttle:` is applied per route.
- **Authentication is a hand-rolled session flag**, not a Laravel guard: login/register/Google callback do `session()->put('customer_id', …)`; controllers read it via `Controller::customerId()`. Authorization therefore lives **inside controllers** (`abort_unless($id, 401)`) and **in page JavaScript**, never in middleware.
- **Pages are thin Blade shells rendered by JS.** Catalog/nav/hero/user/cart/wishlist are injected as `window.__BOOT__` (`Storefront::boot()`), and pages are drawn client-side. "Pages" such as the login modal, cart drawer, wishlist drawer, search overlay and quick-view are overlays, not routes.
- **Cart and wishlist** live in `localStorage` for guests and sync to the DB (`PUT /account/cart|wishlist`) for signed-in customers; they are merged on login/register/Google sign-in.
- Catalog data is cached (`storefront.catalog.v2`, ~1–6 min stale); the Size Guide key and product existence are read from the DB directly.

Route totals: **30 routes** = 7 HTML pages (GET), 4 JSON GET endpoints, 17 POST/PUT/DELETE action endpoints (incl. 2 AJAX quote/place calls), 2 OAuth redirects/callbacks (GET), plus 1 framework health route `/up` (not in `route:list --except-vendor`). 0 redirect-only routes (one controller redirect, see `/checkout`).

## 1. Page inventory

Legend — **Page** = HTML document; **AJAX** = JSON endpoint called by page JS; **Action** = form/API write; **OAuth** = browser redirect.

### A. Public pages (same for everyone)

| Page Name | URL / Route | Access | Controller | Blade / View | Main Purpose |
|---|---|---|---|---|---|
| Homepage | `GET /` (`home`) | Public | `PageController@home` | `pages/index` | Hero, curated products, categories, home/jute/story sections, newsletter, footer |
| Product listing (also category, subcategory, brand, pre-order, new arrivals, search results) | `GET /products` (`products`) — filters via query: `?category=`, `?subcategory=`, `?brand=`, `?search=`, `?sort=` (`newest`), `?category=preorder` | Public | `PageController@products` | `pages/products` + `assets/js/products.js` | Filterable catalog; title/description derived server-side from the query |
| Product details | `GET /products/{slug}` (`product`) | Public (404 unless `status=active`, `visibility=visible`) | `PageController@product` | `pages/product-detail` + `product-detail.js` | Gallery, colour/size picker, add to cart/wishlist, buy now, size guide, related/recently viewed |
| Bulk & corporate order request | `GET /bulk-order` (`bulk-order`) | Public (submission also works as guest) | `PageController@bulkOrder` | `pages/bulk-order` + `pages/bulk-order.js` | Multi-product, multi-variant bulk quote request |
| Product sourcing request / "Contact" | `GET /request` (`request`) | Public (works as guest) | `PageController@sourcing` | `pages/request` + `pages/request.js` | Ask the team to source a product; also the target of every "Contact Us" link |

There are **no standalone category/subcategory/search/static pages** (no About, Contact, Shipping, Returns, Terms, Privacy, FAQ routes). Category/subcategory/search are query modes of `/products`.

### B. Authorized pages (login required)

| Page Name | URL / Route | Access | Controller | Blade / View | Main Purpose |
|---|---|---|---|---|---|
| Checkout | `GET /checkout` (`checkout`) | **Signed-in only, enforced server-side**: guest gets `302 /?signin=/checkout` | `PageController@checkout` | `pages/checkout` + `pages/checkout.js` | Address selection/management, payment method, coupon, order summary, place order, success dialog |

In-account areas below are **tabs of the Account page (client-side gated), not routes**: Profile, My Orders, Wishlist, Shopping Bag, Requests (`#profile|#orders|#wishlist|#cart|#requests`).

### C. Conditional / guest-accessible pages

| Page Name | URL / Route | Guest behaviour | Signed-in behaviour | Controller | Blade / View |
|---|---|---|---|---|---|
| Account | `GET /account` (`account`) | Page loads for anyone (no server check); JS shows "Guest User" + **Sign In / Register** panel, auth view hidden | Profile (edit name/phone/postcode/address), Orders list, Wishlist, Bag, Requests list, Logout | `PageController@account` | `pages/account` + `pages/account.js` |
| Any page: header/cart/wishlist/auth | n/a | Cart + wishlist kept in `localStorage`; "Proceed to Checkout" opens the login modal with return path `/checkout`; account icon opens modal | Cart/wishlist server-synced; account menu | `app.js` | `partials/*`, `layouts/store` |
| Bulk order / Sourcing | `/bulk-order`, `/request` | Submit with typed contact info | Request is linked to `customer_id`; appears in Account → Requests (`GET /account/requests`) | `RequestController` | — |

Overlays (not routes): auth modal (tabs **login / register / forgot / reset**), cart drawer, wishlist drawer, search overlay, quick-view, variant-selection modal, size-guide modal, checkout success dialog.

### D. Non-page endpoints (do **not** count as pages)

| Endpoint | Method | Type | Access | Controller@method | Purpose |
|---|---|---|---|---|---|
| `/size-guides/{key}` | GET | AJAX (JSON) | Public (404 if inactive/empty) | `PageController@sizeGuide` | Size guide data for the product modal |
| `/auth/register` | POST | Action (JSON) | Public, `throttle:10,1` | `AuthController@register` | Create customer + start session + merge guest cart/wishlist |
| `/auth/login` | POST | Action | Public, `throttle:10,1` | `AuthController@login` | Email/password sign-in (+merge); inactive accounts → 403 |
| `/auth/logout` | POST | Action | Public (no guard) | `AuthController@logout` | Clear session, new CSRF token |
| `/auth/forgot` | POST | Action | Public, `throttle:5,1` | `AuthController@forgot` | Emails a 6-digit code (15 min); same reply whether or not the account exists |
| `/auth/reset` | POST | Action | Public, `throttle:10,1` | `AuthController@reset` | Verify code (max 5 attempts) and set a new password |
| `/auth/google` | GET | OAuth redirect | Public, `throttle:20,1` | `GoogleAuthController@redirect` | Start Google sign-in; validates same-site `return` path |
| `/auth/google/callback` | GET | OAuth redirect | Public, `throttle:20,1` | `GoogleAuthController@callback` | Finish sign-in, redirect to saved return path with a flash notice |
| `/account/merge` | POST | Action | Signed-in (401) | `AccountController@merge` | Merge guest cart/wishlist after Google sign-in |
| `/account/profile` | PUT | Action | Signed-in (401) | `AccountController@profile` | Update name/phone (+ overwrite default address) |
| `/account/orders` | GET | AJAX | Signed-in (401) | `AccountController@orders` | Latest 100 orders with items |
| `/account/requests` | GET | AJAX | Signed-in (401) | `AccountController@requests` | Sourcing + bulk requests |
| `/account/cart` | PUT | Action | Signed-in (401) | `AccountController@saveCart` | Replace server cart |
| `/account/wishlist` | PUT | Action | Signed-in (401) | `AccountController@saveWishlist` | Replace server wishlist |
| `/account/addresses` | GET | AJAX | Signed-in (401) | `AccountController@addresses` | Saved addresses |
| `/account/addresses` | POST | Action | Signed-in (401) | `AccountController@storeAddress` | Add address (first one becomes default) |
| `/account/addresses/{id}` | PUT | Action | Signed-in (401), owner-scoped | `AccountController@updateAddress` | Edit address |
| `/account/addresses/{id}` | DELETE | Action | Signed-in (401), owner-scoped | `AccountController@deleteAddress` | Delete; promotes another to default |
| `/checkout/quote` | POST | AJAX | Signed-in (401) | `CheckoutController@quote` | Authoritative prices, shipping, coupon |
| `/checkout/place` | POST | Action | Signed-in (401), `throttle:20,1` | `CheckoutController@place` | Validate, price, create order (COD only), returns order number |
| `/bulk-order` | POST | Action | Public, `throttle:10,1` | `RequestController@bulk` | Store bulk request (customer id attached if signed in) |
| `/request` | POST | Action | Public, `throttle:10,1` | `RequestController@sourcing` | Store sourcing request |
| `/newsletter` | POST | Action | Public, `throttle:10,1` | `RequestController@newsletter` | Newsletter subscription |
| `/up` | GET | Framework health | Public | framework | Health check |

## 2. Route → controller → view map

```
GET  /                     PageController@home          → pages.index
GET  /products             PageController@products      → pages.products        (+ query filters)
GET  /products/{slug}      PageController@product       → pages.product-detail  (404 if not active+visible)
GET  /size-guides/{key}    PageController@sizeGuide     → JSON
GET  /checkout             PageController@checkout      → pages.checkout   | 302 /?signin=/checkout for guests
GET  /account              PageController@account       → pages.account
GET  /bulk-order           PageController@bulkOrder     → pages.bulk-order
GET  /request              PageController@sourcing      → pages.request
POST /auth/{register,login,logout,forgot,reset}   AuthController
GET  /auth/google[/callback]                      GoogleAuthController
POST /account/merge · PUT /account/{profile,cart,wishlist} · GET /account/{orders,requests,addresses}
POST/PUT/DELETE /account/addresses[/{id}]         AccountController
POST /checkout/{quote,place}                      CheckoutController
POST /bulk-order · POST /request · POST /newsletter   RequestController
Layout: layouts.store (+ partials header, footer, overlays). Unrouted view: welcome.blade.php
```

## 3. Customer journeys (only flows that exist)

### Guest journey
1. **Homepage** → hero/sections/categories, or header mega-menu / footer links → **`/products`** (filtered by `?category=`, `?subcategory=`, `?sort=newest`, `?category=preorder`, or by the search overlay → `?search=`).
2. **Product details** (`/products/{slug}`) → pick colour/size → **Add to cart** (drawer; localStorage) or **wishlist** (localStorage, no login needed) → optional **Size Guide** modal (Inch/CM).
3. **Cart drawer** → "Proceed to Checkout" → not signed in → **auth modal** (login / register / forgot→reset / "Continue with Google" when enabled) → on success the guest cart/wishlist is merged and the browser goes to `/checkout`. (Visiting `/checkout` directly redirects to `/?signin=/checkout`, which opens the same modal.)
4. **Guest-only extras:** `/bulk-order` and `/request` can be submitted without an account (contact info typed in); newsletter signup in the footer.
5. Guest **cannot** place an order: checkout is signed-in only (page redirect + `401` on both checkout APIs).

### Authenticated customer journey
1. **Sign in** (modal email/password, Google, or just registered — register logs in immediately, min password 6) → **Forgot password**: email → 6-digit code → set new password → sign in again manually (reset does not sign in).
2. Browse (`/`, `/products`, product page) with cart/wishlist synced to the DB on every change.
3. **Account** (`/account`, tabs): edit profile (name/phone/postcode/address), view Orders (list only), Wishlist, Bag, Requests (sourcing + bulk) ; **Logout** (sidebar).
4. **Cart → `/checkout`**: choose or add/edit/delete a delivery address (inside checkout), choose payment (only **Cash on Delivery** enabled), optional coupon (`/checkout/quote`), **Place Order** → success dialog with order number, address, amount; buttons **Continue Shopping** (`/products`) and **View My Orders** (`/account`). The local cart is cleared.
5. **Order History** → Account → My Orders: list with items and status. There is **no order detail page, tracking or cancellation** (see §6).

## 4. Access control matrix

| Page / Feature | Guest | Authenticated Customer | Conditions (verified in code) |
|---|---|---|---|
| `/`, `/products`, `/products/{slug}` | ✅ | ✅ | Product: active + visible only, else 404 |
| Size guide data `/size-guides/{key}` | ✅ | ✅ | Active guide with measurements, else 404 |
| Search overlay / filters | ✅ | ✅ | Client-side over boot catalog |
| Cart, wishlist | ✅ (localStorage) | ✅ (server-synced) | Merged on login/register/Google |
| `/bulk-order`, `/request` (view + submit) | ✅ | ✅ | Signed-in submissions store `customer_id`; contact info required |
| Newsletter | ✅ | ✅ | `throttle:10,1` |
| Register / Login / Forgot / Reset | ✅ | ✅ (not blocked) | Throttled; inactive account → 403 at login |
| Google sign-in | ✅ | — | Only when Google login is configured (`GoogleAuthController::enabled()`) |
| Logout | ✅ (harmless) | ✅ | No guard |
| `/account` page shell | ✅ loads, shows sign-in panel | ✅ full UI | Gated **client-side only**; no server redirect |
| `/account/*` JSON (profile, orders, requests, cart, wishlist, addresses, merge) | ❌ 401 | ✅ | `abort_unless(customerId, 401)`; address ops scoped to owner |
| `/checkout` page | ❌ 302 → `/?signin=/checkout` | ✅ | `session('customer_id')` check in controller |
| `/checkout/quote`, `/checkout/place` | ❌ 401 | ✅ | Payment method must be in `storefront.payment_methods` (`cod` only); coupon/items validated server-side |
| Order history / requests list | ❌ | ✅ | Only the customer's own rows (`customer_id`) |
| Order detail / tracking / cancel / invoice | ❌ | ❌ | **Not implemented** |

## 5. Implemented vs partial vs unavailable

| Feature | Status |
|---|---|
| Browse, filter, search, product page, cart, wishlist, size guide | Implemented |
| Registration, login, logout, Google login, forgot/reset (email code) | Implemented (email delivery depends on mail config, not verifiable here) |
| Profile edit | Implemented, partial: one free-text address field that overwrites the default address |
| Address management | Partial: only inside checkout (add/edit/delete); no Account tab; no "set default" action |
| Checkout (quote, coupon, place order, success dialog) | Implemented; **COD only** |
| Order history | Partial: list only |
| Bulk-order and sourcing requests (+ history in Account) | Implemented |
| Guest checkout | Unavailable (by design) |
| Order detail, tracking, cancellation, invoices/receipt | Unavailable |
| Email verification, change password/email while signed in, account deletion | Unavailable |
| Card / PayPal payment | Unavailable (markup is conditional on config; disabled) |
| Static/legal/help pages (About, Contact, Shipping, Returns, Terms, Privacy, FAQ) | Unavailable |

## 6. Missing or incomplete flows

### Confirmed issues
1. **Orphan view `resources/views/welcome.blade.php`** — no route renders it, and it references `route('login')`, `route('register')` and `url('/dashboard')`, none of which exist (it would throw if ever rendered).
2. **No order detail / tracking / cancel / receipt.** Orders are only a list in Account; the success dialog's "View My Orders" links to plain `/account` (opens the **Profile** tab, not Orders; the `#orders` hash is supported but not used there).
3. **"Our Story" button on the homepage points to `#products-section`** (`pages/index.blade.php` ~L139) instead of the story section (`#bangladesh-made-section`), which does exist and is what the footer's story links target.
4. **Footer duplicates / placeholders** (`FOOTER_LINKS` in `data.js`): "Contact Us" and "Request Product Sourcing" and "Custom Sourcing" all go to `/request`; "Our Story" and "Artisan Heritage" are the same anchor; "Bulk & Corporate Orders" and "Bulk Orders" are the same page. There is no real Contact, Shipping, Returns, Terms or Privacy page, although the product page promises "30-day hassle-free returns".
5. **Dead guest-checkout code**: `checkout.js` keeps guest address storage (`bongo_saved_addresses`, `body.address = selected`) and `/checkout/place` still accepts an inline `address`, yet guests can never reach checkout (302 + 401). No effect on users, but misleading to maintainers.
6. **Address management is not available from Account**, and no endpoint sets/changes the default address; `PUT /account/profile` silently overwrites the default address row with the free-text field.
7. **Auth is not middleware-based**: the only protection of `/account` is client-side JS and, for APIs, per-method `require()` calls. Every new endpoint must remember to call it (no safety net).
8. **No automated tests** cover routes, auth, account, or checkout (`tests/Feature` has only Google login, Size Guide and the default example).

### Potential issues (not proven by code alone)
1. **Expired session handling**: `bongoApi` has no special handling of `401`/`419` (no match found in `app.js`), so an expired session during checkout probably shows only a toast.
2. **`/account` serves its shell to guests** (inconsistent with `/checkout`'s server redirect); it works, but a guest briefly sees the page before the sign-in panel.
3. **Checkout copy shows a "256-bit Encrypted" badge** while only COD is enabled.
4. **Product page depends on cached catalog data** (`if (!product) return;` in `product-detail.js`): a just-published product exists in the DB (page 200) but can render without gallery/cart for up to ~6 minutes. (Size Guide was made independent of this; the rest still is not.)
5. **Password-reset emails** need working mail settings in production; there is no UI/route fallback if mail fails.
6. **`/auth/logout` and other state-changing routes** rely on the default CSRF protection; client sends a token (`bongoApi`), not re-verified here.
7. **No custom 404/500 views** (`resources/views/errors` absent), so unknown URLs show Laravel's default error page, unstyled relative to the store.

Not found: duplicate route definitions (the only repeated URIs are GET/POST pairs `/bulk-order` and `/request`, and `/account/addresses` GET/POST, which are intentional); routes without controllers; routed views without templates.

## 7. Key findings and recommendations

1. The storefront is a **small, coherent single-flow shop**: 5 public pages, 1 server-protected page (`/checkout`), 1 conditional page (`/account`), 23 JSON/action endpoints. Everything else (login, cart, wishlist, search, quick view, size guide) is an overlay.
2. **Highest-value gaps**: order detail/tracking/cancel, address management in Account, and the missing policy/contact pages that the UI already implies (returns promise, "Contact Us" link).
3. **Cheap fixes**: repoint the homepage "Our Story" button; make "View My Orders" open `/account#orders`; delete or archive `welcome.blade.php`; de-duplicate footer links.
4. **Hardening**: add an `auth`-style middleware (or a small `customer` middleware) for `/account/*` and `/checkout*` instead of per-method checks; handle 401/419 centrally in `bongoApi`; add feature tests for auth, address ownership and checkout; add branded 404/500 pages.
5. **Decide product direction**: whether guest checkout and card/PayPal are planned — if not, remove the dead guest-address code and the unused payment markup.

## 8. Summary counts

| Item | Count |
|---|---|
| Public pages | **5** (`/`, `/products`, `/products/{slug}`, `/bulk-order`, `/request`) |
| Authorized pages | **1** (`/checkout`, server-enforced) — plus 5 in-account tabs (not routes) |
| Conditional pages | **1** (`/account`) — plus cart/wishlist/header behaviour that varies by login |
| Non-page endpoints | 24 (4 JSON GET incl. size guide, 17 POST/PUT/DELETE actions, 2 OAuth, 1 framework health) |
| Confirmed issues | **8** |
| Potential issues | **7** |
