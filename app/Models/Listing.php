<?php

namespace App\Models;

/**
 * Product listing filters, sorting, paging, facets and quick search, applied on the server to the cached catalog
 * (Storefront::catalog()). Same rules as the listing and search scripts used to apply in the browser over the whole
 * embedded catalog, so the browser now receives one page at a time instead of every product.
 *
 * Scale note: the filtering runs in PHP over the cached product array (fast for a few thousand products). Past that,
 * move the filters into SQL (see STOREFRONT_PRODUCTION_READINESS_PLAN.md §14).
 */
class Listing
{
    /** First batch / page size; keep equal to listingState.pageSize in public/assets/js/products.js. */
    public const PAGE_SIZE = 8;

    public const MAX_PAGE_SIZE = 48;

    public const SORTS = ['featured', 'newest', 'price-low', 'price-high'];

    /** Normalise request input into a filter array (unknown values are ignored, as the scripts did). */
    public static function filters(array $in): array
    {
        $str = fn ($k) => is_string($in[$k] ?? null) ? mb_substr(trim($in[$k]), 0, 120) : '';
        $list = function ($k) use ($in) {
            $v = $in[$k] ?? [];
            $v = is_string($v) ? [$v] : (is_array($v) ? $v : []);

            return array_values(array_unique(array_filter(array_map(fn ($x) => is_string($x) ? mb_substr(trim($x), 0, 80) : '', array_slice($v, 0, 30)), fn ($x) => $x !== '')));
        };
        $brands = $list('brands');
        if ($str('brand') !== '') {
            $brands[] = $str('brand');   // ?brand=… from links
        }

        return [
            'category' => $str('category'),
            'subcategory' => $str('subcategory'),
            'brands' => array_values(array_unique($brands)),
            'prices' => array_values(array_intersect($list('prices'), ['under-30', '30-40', '40-50', '50-above'])),
            'sizes' => $list('sizes'),
            'colors' => $list('colors'),
            'availability' => array_values(array_intersect($list('availability'), ['instock', 'preorder', 'soldout'])),
            'search' => mb_strtolower($str('search')),
            'sort' => in_array($str('sort'), self::SORTS, true) ? $str('sort') : 'featured',
        ];
    }

    /** Matching products in display order (applyFiltersAndRender() in products.js, before this change). */
    public static function filter(array $products, array $f): array
    {
        $catalog = Storefront::catalog();
        $known = array_column($catalog['categories'], 'slug');
        $cat = $f['category'];
        $out = array_values(array_filter($products, function ($p) use ($f, $cat, $known) {
            if ($cat === 'preorder' && $p['availability'] !== 'preorder') {
                return false;
            }
            if ($cat !== '' && $cat !== 'all' && $cat !== 'preorder' && in_array($cat, $known, true) && $p['category'] !== $cat) {
                return false;
            }
            if ($f['subcategory'] !== '' && $p['subcategory'] !== $f['subcategory']) {
                return false;
            }
            if ($f['brands'] && ! in_array($p['brand'], $f['brands'], true)) {
                return false;
            }
            if ($f['prices']) {
                $price = (float) $p['price'];
                $ok = false;
                foreach ($f['prices'] as $r) {
                    $ok = $ok || match ($r) {
                        'under-30' => $price < 30,
                        '30-40' => $price >= 30 && $price <= 40,
                        '40-50' => $price > 40 && $price <= 50,
                        '50-above' => $price > 50,
                    };
                }
                if (! $ok) {
                    return false;
                }
            }
            if ($f['sizes'] && ! array_intersect($p['sizes'] ?? [], $f['sizes'])) {
                return false;
            }
            if ($f['colors'] && ! array_intersect($p['colors'] ?? [], $f['colors'])) {
                return false;
            }
            if ($f['availability'] && ! in_array($p['availability'] === 'lowstock' ? 'instock' : $p['availability'], $f['availability'], true)) {
                return false;
            }
            if ($f['search'] !== '') {
                $hay = mb_strtolower(($p['name'] ?? '').' '.($p['desc'] ?? '').' '.($p['category'] ?? ''));
                if (! str_contains($hay, $f['search'])) {
                    return false;
                }
            }

            return true;
        }));

        match ($f['sort']) {
            'price-low' => usort($out, fn ($a, $b) => $a['price'] <=> $b['price']),
            'price-high' => usort($out, fn ($a, $b) => $b['price'] <=> $a['price']),
            'newest' => usort($out, fn ($a, $b) => $b['id'] <=> $a['id']),
            default => null,
        };

        return $out;
    }

    /** @return array{products: array, total: int, page: int, perPage: int, hasMore: bool} */
    public static function page(array $f, int $page = 1, int $perPage = self::PAGE_SIZE): array
    {
        $perPage = max(1, min($perPage, self::MAX_PAGE_SIZE));
        $page = max(1, min($page, 500));
        $all = self::filter(Storefront::catalog()['products'], $f);
        $slice = array_slice($all, ($page - 1) * $perPage, $perPage);

        return [
            'products' => array_map([self::class, 'card'], $slice),
            'total' => count($all),
            'page' => $page,
            'perPage' => $perPage,
            'hasMore' => $page * $perPage < count($all),
        ];
    }

    /** Filter options (renderFilterOptions() in products.js): over the whole catalog, as before. */
    public static function facets(): array
    {
        $products = Storefront::catalog()['products'];
        $brands = $sizes = $colors = [];
        $preorder = false;
        foreach ($products as $p) {
            if ($p['brand']) {
                $brands[$p['brand']] = true;
            }
            foreach ($p['sizes'] ?? [] as $s) {
                $sizes[$s] ??= true;
            }
            foreach ($p['colors'] ?? [] as $c) {
                if (preg_match('/^#[0-9a-f]{3,8}$/i', $c) && ! isset($colors[$c])) {
                    $colors[$c] = $p['colorNames'][$c] ?? $c;
                }
            }
            $preorder = $preorder || $p['availability'] === 'preorder';
        }
        $brands = array_keys($brands);
        sort($brands);

        return [
            'brands' => $brands,
            'sizes' => array_keys($sizes),
            'colors' => array_map(fn ($hex, $name) => ['hex' => $hex, 'name' => $name], array_keys($colors), $colors),
            'hasPreorder' => $preorder,
        ];
    }

    /**
     * Header quick search (handleSearchQuery() in app.js): name, category slug or name, brand or short description.
     * An empty query returns the newest products (the "suggested" list).
     *
     * @return array{products: array, total: int}
     */
    public static function search(string $q, int $limit = 20): array
    {
        $catalog = Storefront::catalog();
        $q = mb_strtolower(trim($q));
        $products = $catalog['products'];
        if ($q !== '') {
            $catNames = array_column($catalog['categories'], 'name', 'slug');
            $products = array_values(array_filter($products, fn ($p) => str_contains(mb_strtolower($p['name']), $q)
                || str_contains(mb_strtolower($p['category']), $q)
                || str_contains(mb_strtolower($catNames[$p['category']] ?? ''), $q)
                || str_contains(mb_strtolower((string) $p['brand']), $q)
                || str_contains(mb_strtolower((string) $p['desc']), $q)));
        }

        return ['products' => array_map([self::class, 'card'], array_slice($products, 0, $limit)), 'total' => count($products)];
    }

    /** Products by id (catalog order), for cart/wishlist/recently-viewed lookups. Unlisted ids are simply absent. */
    public static function byIds(array $ids): array
    {
        $want = array_flip(array_map('intval', $ids));
        $out = [];
        foreach (Storefront::catalog()['products'] as $p) {
            if (isset($want[$p['id']])) {
                $out[] = self::card($p);
            }
        }

        return $out;
    }

    /** Card/cart shape: the full product minus the long text that only the product page needs. */
    public static function card(array $p): array
    {
        unset($p['fullDesc'], $p['specs']);

        return $p;
    }
}
