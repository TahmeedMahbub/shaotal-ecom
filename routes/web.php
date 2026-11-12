<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\SeoController;
use App\Http\Middleware\ValidateCustomerSession;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

$sessionless = [
    EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class,
    PreventRequestForgery::class, ValidateCustomerSession::class,
];

// Public, cacheable, identical for every visitor: no session, cookies or CSRF token, so a shared cache (CDN/proxy) can
// never store one visitor's Set-Cookie and hand it to others.
Route::withoutMiddleware($sessionless)->group(function () {
    // Crawlers (public/robots.txt must not exist: the web server would serve it instead of this route)
    Route::get('/robots.txt', [SeoController::class, 'robots']);
    Route::get('/sitemap.xml', [SeoController::class, 'index']);
    Route::get('/sitemap-pages.xml', [SeoController::class, 'pages']);
    Route::get('/sitemap-products-{page}.xml', [SeoController::class, 'products'])->whereNumber('page');

    // Catalog JSON for the page scripts (pages embed only the products they render)
    Route::get('/catalog/products', [CatalogController::class, 'products'])->middleware('throttle:120,1,catalog');
    Route::get('/catalog/search', [CatalogController::class, 'search'])->middleware('throttle:120,1,catalog');
    Route::get('/catalog/feed', [CatalogController::class, 'feed'])->middleware('throttle:120,1,catalog');
});

// Every throttle has its own key prefix (3rd argument): without it Laravel counts all throttled routes in ONE per-IP
// bucket, so catalog browsing or CSRF refreshes would use up the sign-in and checkout allowances.

// Pages
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/products', [PageController::class, 'products'])->name('products');
Route::get('/products/{slug}', [PageController::class, 'product'])->name('product');
Route::get('/size-guides/{key}', [PageController::class, 'sizeGuide'])->name('size-guide');
Route::get('/checkout', [PageController::class, 'checkout'])->middleware('customer')->name('checkout');
Route::get('/account', [PageController::class, 'account'])->name('account');
Route::get('/bulk-order', [PageController::class, 'bulkOrder'])->name('bulk-order');
Route::get('/request', [PageController::class, 'sourcing'])->name('request');
Route::get('/{slug}', [PageController::class, 'info'])->whereIn('slug', array_keys(PageController::INFO_PAGES))->name('info');

// Auth
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1,auth-register');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1,auth-login');
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::get('/auth/csrf', [AuthController::class, 'csrf'])->middleware('throttle:30,1,auth-csrf');
Route::post('/auth/forgot', [AuthController::class, 'forgot'])->middleware('throttle:5,1,auth-forgot');
Route::post('/auth/reset', [AuthController::class, 'reset'])->middleware('throttle:10,1,auth-reset');

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->middleware('throttle:20,1,auth-google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:20,1,auth-google-callback');
// Signed-in, still-active customers only ("customer" middleware: 401 JSON, account re-checked on every request).
// Controllers also call their own sign-in check as a second line of defence.
Route::middleware('customer')->group(function () {
    Route::post('/account/merge', [AccountController::class, 'merge']);

    Route::put('/account/profile', [AccountController::class, 'profile']);
    Route::get('/account/orders', [OrderController::class, 'index']);
    // Order numbers are short tokens (INV-123456); the pattern keeps odd paths out of the DB lookup.
    Route::get('/account/orders/{number}', [OrderController::class, 'show'])->where('number', '[A-Za-z0-9\-]{1,40}')->name('account.order');
    Route::post('/account/orders/{number}/return', [OrderController::class, 'requestReturn'])->where('number', '[A-Za-z0-9\-]{1,40}')->middleware('throttle:5,1,order-return');
    Route::post('/account/orders/{number}/cancel', [OrderController::class, 'cancel'])->where('number', '[A-Za-z0-9\-]{1,40}')->middleware('throttle:10,1,order-cancel');
    Route::get('/account/requests', [AccountController::class, 'requests']);
    Route::put('/account/cart', [AccountController::class, 'saveCart']);
    Route::put('/account/wishlist', [AccountController::class, 'saveWishlist']);
    Route::get('/account/addresses', [AccountController::class, 'addresses']);
    Route::post('/account/addresses', [AccountController::class, 'storeAddress']);
    Route::put('/account/addresses/{id}', [AccountController::class, 'updateAddress'])->whereNumber('id');
    Route::delete('/account/addresses/{id}', [AccountController::class, 'deleteAddress'])->whereNumber('id');

    // Checkout
    Route::post('/checkout/quote', [CheckoutController::class, 'quote']);
    Route::post('/checkout/place', [CheckoutController::class, 'place'])->middleware('throttle:20,1,checkout-place');
});

// Requests & newsletter
Route::post('/bulk-order', [RequestController::class, 'bulk'])->middleware('throttle:10,1,bulk-order');
Route::post('/request', [RequestController::class, 'sourcing'])->middleware('throttle:10,1,sourcing');
Route::post('/contact', [RequestController::class, 'contact'])->middleware('throttle:5,1,contact');
Route::post('/newsletter', [RequestController::class, 'newsletter'])->middleware('throttle:10,1,newsletter');
