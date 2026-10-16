<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RequestController;
use Illuminate\Support\Facades\Route;

// Pages
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/products', [PageController::class, 'products'])->name('products');
Route::get('/products/{slug}', [PageController::class, 'product'])->name('product');
Route::get('/size-guides/{key}', [PageController::class, 'sizeGuide'])->name('size-guide');
Route::get('/checkout', [PageController::class, 'checkout'])->name('checkout');
Route::get('/account', [PageController::class, 'account'])->name('account');
Route::get('/bulk-order', [PageController::class, 'bulkOrder'])->name('bulk-order');
Route::get('/request', [PageController::class, 'sourcing'])->name('request');

// Auth
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::post('/auth/forgot', [AuthController::class, 'forgot'])->middleware('throttle:5,1');
Route::post('/auth/reset', [AuthController::class, 'reset'])->middleware('throttle:10,1');

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->middleware('throttle:20,1');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:20,1');
Route::post('/account/merge', [AccountController::class, 'merge']);

// Customer account (session customer required inside controller)
Route::put('/account/profile', [AccountController::class, 'profile']);
Route::get('/account/orders', [AccountController::class, 'orders']);
Route::get('/account/requests', [AccountController::class, 'requests']);
Route::put('/account/cart', [AccountController::class, 'saveCart']);
Route::put('/account/wishlist', [AccountController::class, 'saveWishlist']);
Route::get('/account/addresses', [AccountController::class, 'addresses']);
Route::post('/account/addresses', [AccountController::class, 'storeAddress']);
Route::put('/account/addresses/{id}', [AccountController::class, 'updateAddress'])->whereNumber('id');
Route::delete('/account/addresses/{id}', [AccountController::class, 'deleteAddress'])->whereNumber('id');

// Checkout
Route::post('/checkout/quote', [CheckoutController::class, 'quote']);
Route::post('/checkout/place', [CheckoutController::class, 'place'])->middleware('throttle:20,1');

// Requests & newsletter
Route::post('/bulk-order', [RequestController::class, 'bulk'])->middleware('throttle:10,1');
Route::post('/request', [RequestController::class, 'sourcing'])->middleware('throttle:10,1');
Route::post('/newsletter', [RequestController::class, 'newsletter'])->middleware('throttle:10,1');
