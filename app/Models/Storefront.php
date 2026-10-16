<?php

namespace App\Models;

use App\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Data injected into every storefront page (window.__BOOT__) plus customer cart/wishlist helpers. */
class Storefront
{
    /**
     * Catalog data shared by all visitors (never user-specific). Cached on the file store: fresh for 60 s, then the
     * previous copy keeps being served for up to 5 more minutes while ONE request rebuilds it after its response is
     * sent (Cache::flexible). The admin is a separate app, so edits appear within ~1–6 minutes; to force a refresh
     * delete the key: php artisan tinker --execute="Cache::store('file')->forget('storefront.catalog.v2')".
     */
    public static function catalog(): array
    {
        return Cache::store('file')->flexible('storefront.catalog.v2', [60, 300], function () {
            $images = Catalog::settings(array_merge(array_keys(config('storefront.home_images')), ['social_facebook', 'social_instagram', 'social_tiktok']));
            $home = [];
            foreach (config('storefront.home_images') as $key => $name) {
                $home[$name] = is_string($images[$key] ?? null) ? $images[$key] : null;
            }

            return [
                'products' => Catalog::products(),
                'categories' => Catalog::categories(),
                'subcategories' => Catalog::subcategories(),
                'brands' => Catalog::brands(),
                'hero' => Catalog::heroSlides(),
                'homeImages' => $home,
                'social' => [
                    'facebook' => is_string($images['social_facebook'] ?? null) ? $images['social_facebook'] : null,
                    'instagram' => is_string($images['social_instagram'] ?? null) ? $images['social_instagram'] : null,
                    'tiktok' => is_string($images['social_tiktok'] ?? null) ? $images['social_tiktok'] : null,
                ],
            ];
        });
    }

    public static function boot(?int $customerId, ?string $productSlug = null): array
    {
        $cat = self::catalog();
        $user = $customerId ? self::customer($customerId) : null;
        $byId = [];
        foreach ($cat['products'] as $p) {
            $byId[$p['id']] = $p;
        }

        // Long text and specs are only needed on the product page of the product being viewed.
        $cat['products'] = array_map(function ($p) use ($productSlug) {
            if ($p['slug'] !== $productSlug) {
                unset($p['fullDesc'], $p['specs']);
            }

            return $p;
        }, $cat['products']);

        return $cat + [
            'site' => [
                'brandName' => config('storefront.brand'),
                'tagline' => config('storefront.tagline'),
                'currency' => config('storefront.currency_symbol'),
                'countryCode' => config('storefront.country_code'),
            ],
            'user' => $user,
            'cart' => $user ? self::cart($customerId, $byId) : [],
            'wishlist' => $user ? self::wishlist($customerId, $byId) : [],
            'payments' => config('storefront.payment_methods'),
            'googleLogin' => GoogleAuthController::enabled(),
            'flash' => ['error' => session('auth_error'), 'notice' => session('auth_notice')],
        ];
    }

    public static function customer(int $id): ?array
    {
        // Customer + default (else oldest) saved address in one query.
        $c = DB::table('customers as c')
            ->leftJoin('customer_addresses as a', 'a.id', '=', DB::raw('(select id from customer_addresses where customer_id = c.id order by is_default desc, id asc limit 1)'))
            ->where('c.id', $id)->where('c.status', 'active')
            ->first(['c.id', 'c.first_name', 'c.last_name', 'c.email', 'c.phone', 'c.created_at', 'a.street_address', 'a.suburb', 'a.state', 'a.postcode']);
        if (! $c) {
            return null;
        }
        $addr = $c->street_address !== null || $c->postcode !== null ? $c : null;

        return [
            'id' => (int) $c->id,
            'name' => trim($c->first_name.' '.($c->last_name ?? '')),
            'email' => $c->email,
            'phone' => $c->phone ?? '',
            'joinedDate' => substr((string) $c->created_at, 0, 10),
            'address' => $addr ? trim(implode(', ', array_filter([$addr->street_address, $addr->suburb, $addr->state]))) : '',
            'postcode' => $addr->postcode ?? '',
        ];
    }

    /** Customer's saved cart converted to the storefront cart-item shape. */
    public static function cart(int $customerId, array $productsById): array
    {
        // Latest active cart's items in one query (no separate lookup of the cart row).
        $rows = DB::table('cart_items as ci')
            ->leftJoin('product_variants as v', 'v.id', '=', 'ci.variant_id')
            ->leftJoin('product_colors as c', 'c.id', '=', 'v.color_id')
            ->leftJoin('product_sizes as s', 's.id', '=', 'v.size_id')
            ->whereRaw("ci.cart_id = (select id from carts where customer_id = ? and status = 'active' order by id desc limit 1)", [$customerId])
            ->orderBy('ci.id')
            ->get(['ci.product_id', 'ci.quantity', 'c.name as color_name', 'c.hex_code', 's.size_label']);
        $items = [];
        foreach ($rows as $r) {
            $p = $productsById[$r->product_id] ?? null;
            if (! $p) {
                continue;
            }
            $items[] = self::cartItem($p, $r->hex_code ? strtoupper($r->hex_code) : $r->color_name, $r->size_label, (int) $r->quantity);
        }

        return $items;
    }

    public static function cartItem(array $p, ?string $color, ?string $size, int $qty): array
    {
        return [
            'productId' => $p['id'], 'name' => $p['name'], 'price' => $p['price'], 'originalPrice' => $p['originalPrice'] ?? $p['price'],
            'size' => $size, 'color' => $color, 'quantity' => $qty, 'image' => $p['image'], 'badge' => $p['badge'],
            'availability' => $p['availability'], 'isPreorder' => $p['availability'] === 'preorder',
        ];
    }

    public static function wishlist(int $customerId, array $productsById): array
    {
        return DB::table('wishlist_items')->where('customer_id', $customerId)->orderBy('id')->pluck('product_id')
            ->map(fn ($id) => (int) $id)->filter(fn ($id) => isset($productsById[$id]))->values()->all();
    }

    /** Index of the live catalog by product id. */
    public static function productsById(): array
    {
        $out = [];
        foreach (self::catalog()['products'] as $p) {
            $out[$p['id']] = $p;
        }

        return $out;
    }

    /**
     * Resolve the variant for a chosen colour/size. The client sends default sizes/colours even for
     * products that do not use them, so a variant dimension that is NULL matches anything.
     *
     * @return array{0: ?int, 1: bool} [variantId, ok]
     */
    public static function resolveVariant(array $p, ?string $color, ?string $size): array
    {
        if (! $p['hasVariants']) {
            return [null, true];
        }
        foreach ($p['variants'] as $v) {
            $colorOk = $v['color'] === null || ($color !== null && strcasecmp($v['color'], $color) === 0);
            $sizeOk = $v['size'] === null || ($size !== null && $v['size'] === $size);
            if ($colorOk && $sizeOk) {
                return [$v['id'], true];
            }
        }

        return [null, false];
    }

    /** Replace the customer's saved cart with $items [{productId,color,size,quantity}]. */
    public static function replaceCart(int $customerId, array $items): void
    {
        $byId = self::productsById();
        $lines = [];
        foreach ($items as $it) {
            $p = $byId[(int) ($it['productId'] ?? 0)] ?? null;
            if (! $p) {
                continue;
            }
            [$variantId, $ok] = self::resolveVariant($p, $it['color'] ?? null, $it['size'] ?? null);
            if (! $ok) {
                continue;
            }
            $key = $p['id'].':'.($variantId ?? 0);
            $lines[$key] = [
                'product_id' => $p['id'], 'variant_id' => $variantId,
                'quantity' => min(99, ($lines[$key]['quantity'] ?? 0) + max(1, (int) ($it['quantity'] ?? 1))),
            ];
        }

        DB::transaction(function () use ($customerId, $lines) {
            $cart = DB::table('carts')->where('customer_id', $customerId)->where('status', 'active')->orderByDesc('id')->first(['id']);
            $cartId = $cart->id ?? DB::table('carts')->insertGetId(['customer_id' => $customerId, 'status' => 'active', 'last_activity_at' => now()]);
            DB::table('carts')->where('id', $cartId)->update(['last_activity_at' => now()]);
            DB::table('cart_items')->where('cart_id', $cartId)->delete();
            if ($lines) {
                DB::table('cart_items')->insert(array_map(fn ($l) => $l + ['cart_id' => $cartId], array_values($lines)));
            }
        });
    }

    public static function replaceWishlist(int $customerId, array $ids): void
    {
        $byId = self::productsById();
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => isset($byId[$id]))));
        DB::transaction(function () use ($customerId, $ids) {
            DB::table('wishlist_items')->where('customer_id', $customerId)->whereNotIn('product_id', $ids ?: [0])->delete();
            if ($ids) {
                DB::table('wishlist_items')->insertOrIgnore(array_map(fn ($id) => ['customer_id' => $customerId, 'product_id' => $id], $ids));
            }
        });
    }

    /** Merge a guest's local cart/wishlist into the customer's saved ones after login/registration. */
    public static function mergeGuest(int $customerId, array $guestCart, array $guestWishlist): void
    {
        $byId = self::productsById();
        $saved = self::cart($customerId, $byId);
        $merged = array_map(fn ($i) => ['productId' => $i['productId'], 'color' => $i['color'], 'size' => $i['size'], 'quantity' => $i['quantity']], $saved);
        foreach ($guestCart as $g) {
            $merged[] = ['productId' => $g['productId'] ?? 0, 'color' => $g['color'] ?? null, 'size' => $g['size'] ?? null, 'quantity' => $g['quantity'] ?? 1];
        }
        if ($guestCart) {
            self::replaceCart($customerId, $merged);
        }
        if ($guestWishlist) {
            self::replaceWishlist($customerId, array_merge(self::wishlist($customerId, $byId), $guestWishlist));
        }
    }

    public static function sessionPayload(int $customerId): array
    {
        $byId = self::productsById();

        return [
            'user' => self::customer($customerId),
            'cart' => self::cart($customerId, $byId),
            'wishlist' => self::wishlist($customerId, $byId),
        ];
    }
}
