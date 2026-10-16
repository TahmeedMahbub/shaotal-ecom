<?php

namespace App\Models;

use App\Support\Img;
use Illuminate\Support\Facades\DB;

/**
 * Read-side catalog queries. Builds the product shape consumed by the storefront scripts
 * (public/assets/js/data.js) from the shared SQL schema, using a fixed number of queries.
 */
class Catalog
{
    private const BADGES = ['new', 'launch', 'lowstock', 'preorder'];

    public static function categories(): array
    {
        return DB::table('categories')->where('status', 'active')->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name', 'slug', 'description', 'image_url'])
            ->map(fn ($c) => [
                'id' => (int) $c->id, 'name' => $c->name, 'slug' => $c->slug,
                'desc' => $c->description ?? '', 'image' => $c->image_url, 'isDisabled' => false,
            ])->all();
    }

    public static function subcategories(): array
    {
        return DB::table('subcategories as s')->join('categories as c', 'c.id', '=', 's.category_id')
            ->where('s.status', 'active')->where('c.status', 'active')
            ->orderBy('s.sort_order')->orderBy('s.id')
            ->get(['s.id', 's.name', 's.slug', 'c.slug as category'])
            ->map(fn ($s) => ['id' => (int) $s->id, 'name' => $s->name, 'slug' => $s->slug, 'category' => $s->category])->all();
    }

    public static function brands(): array
    {
        return DB::table('brands')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'slug'])
            ->map(fn ($b) => ['id' => (int) $b->id, 'name' => $b->name, 'slug' => $b->slug])->all();
    }

    public static function heroSlides(): array
    {
        $now = now();

        return DB::table('hero_slides')->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'title', 'subtitle', 'image_url', 'mobile_image_url', 'button_text', 'button_url'])
            ->map(fn ($h) => [
                'id' => (int) $h->id, 'headline' => $h->title ?? '', 'subtext' => $h->subtitle ?? '',
                'cta' => $h->button_text ?? '', 'ctaLink' => $h->button_url ?: '/products',
                'image' => $h->image_url, 'imageSrc' => Img::url($h->image_url, 1280), 'mobileImage' => $h->mobile_image_url,
            ])->all();
    }

    /** JSON site_settings values keyed by setting_key. */
    public static function settings(array $keys): array
    {
        $out = [];
        foreach (DB::table('site_settings')->whereIn('setting_key', $keys)->get(['setting_key', 'setting_value']) as $r) {
            $out[$r->setting_key] = json_decode($r->setting_value ?? 'null', true);
        }

        return $out;
    }

    /** All purchasable catalog products (active + visible) in the storefront shape. */
    public static function products(?array $ids = null): array
    {
        $q = DB::table('products as p')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('subcategories as s', 's.id', '=', 'p.subcategory_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('product_specifications as sp', 'sp.product_id', '=', 'p.id')
            ->leftJoin('size_guides as g', 'g.id', '=', 'p.size_guide_id')
            ->where('p.status', 'active')->where('p.visibility', 'visible')->where('c.status', 'active')
            ->orderByDesc('p.id');
        if ($ids !== null) {
            $q->whereIn('p.id', $ids ?: [0]);
        }
        $rows = $q->get([
            'p.id', 'p.name', 'p.slug', 'p.sku', 'p.short_description', 'p.description', 'p.price', 'p.selling_price',
            'p.has_variants', 'p.variant_scenario', 'p.stock_qty', 'p.availability', 'p.badge', 'p.preorder_release_date',
            'p.low_stock_threshold', 'p.seo_title', 'p.seo_description', 'p.size_guide_id', 'p.created_at',
            'c.slug as category', 'c.name as category_name', 's.slug as subcategory', 'b.name as brand',
            'sp.material', 'sp.fabric', 'sp.dimensions', 'sp.fit', 'sp.pattern', 'sp.care_instructions',
            'g.key as size_guide_key',
        ]);
        if ($rows->isEmpty()) {
            return [];
        }
        $pids = $rows->pluck('id')->all();

        $colors = DB::table('product_colors')->whereIn('product_id', $pids)->where('status', 'active')
            ->orderBy('sort_order')->orderBy('id')->get(['id', 'product_id', 'name', 'hex_code'])->groupBy('product_id');
        $sizes = DB::table('product_sizes')->whereIn('product_id', $pids)->where('status', 'active')
            ->orderBy('sort_order')->orderBy('id')->get(['id', 'product_id', 'size_label'])->groupBy('product_id');
        $variants = DB::table('product_variants')->whereIn('product_id', $pids)->where('is_active', 1)
            ->get(['id', 'product_id', 'color_id', 'size_id', 'price_override', 'selling_price_override', 'stock_qty'])->groupBy('product_id');
        $images = DB::table('product_images')->whereIn('product_id', $pids)
            ->orderByDesc('is_main')->orderBy('sort_order')->orderBy('id')->get(['id', 'product_id', 'image_url'])->groupBy('product_id');
        $ratings = DB::table('product_reviews')->whereIn('product_id', $pids)->where('status', 'approved')
            ->groupBy('product_id')->selectRaw('product_id, AVG(rating) as avg_rating, COUNT(*) as review_count')->get()->keyBy('product_id');
        $imageColors = DB::table('product_image_colors')->whereIn('image_id', $images->flatten(1)->pluck('id')->all() ?: [0])
            ->get(['image_id', 'color_id'])->groupBy('image_id');

        return $rows->map(function ($r) use ($colors, $sizes, $variants, $images, $imageColors, $ratings) {
            return self::shape($r, $colors[$r->id] ?? collect(), $sizes[$r->id] ?? collect(), $variants[$r->id] ?? collect(),
                $images[$r->id] ?? collect(), $imageColors, $ratings[$r->id] ?? null);
        })->all();
    }

    public static function colorKey(object $c): string
    {
        return $c->hex_code ? strtoupper($c->hex_code) : $c->name;
    }

    private static function shape($r, $colors, $sizes, $variants, $images, $imageColors, $rating = null): array
    {
        $hasVariants = (bool) $r->has_variants;
        $price = (float) $r->price;
        $selling = $r->selling_price !== null ? (float) $r->selling_price : $price;

        $colorById = $colors->keyBy('id');
        $sizeById = $sizes->keyBy('id');

        // Sold-out sizes/colors: option exists but no active variant with stock offers it.
        $stockBySize = [];
        $stockByColor = [];
        $variantRows = [];
        foreach ($variants as $v) {
            $ck = $v->color_id && isset($colorById[$v->color_id]) ? self::colorKey($colorById[$v->color_id]) : null;
            $sl = $v->size_id && isset($sizeById[$v->size_id]) ? $sizeById[$v->size_id]->size_label : null;
            // effective price = COALESCE(price_override, price); selling = COALESCE(selling_override, product selling, effective)
            $list = $v->price_override !== null ? (float) $v->price_override : $price;
            $vp = $v->selling_price_override !== null ? (float) $v->selling_price_override
                : ($r->selling_price !== null ? (float) $r->selling_price : $list);
            $variantRows[] = ['id' => (int) $v->id, 'color' => $ck, 'size' => $sl, 'price' => $vp, 'list' => $list, 'stock' => (int) $v->stock_qty];
            if ($sl !== null) {
                $stockBySize[$sl] = ($stockBySize[$sl] ?? 0) + (int) $v->stock_qty;
            }
            if ($ck !== null) {
                $stockByColor[$ck] = ($stockByColor[$ck] ?? 0) + (int) $v->stock_qty;
            }
        }

        $stock = $hasVariants ? array_sum(array_column($variantRows, 'stock')) : (int) $r->stock_qty;
        $preorder = $r->availability === 'preorder';
        $soldOut = ! $preorder && ($r->availability === 'out_of_stock' || $r->availability === 'coming_soon' || $stock <= 0);

        $badge = '';
        $raw = json_decode($r->badge ?? 'null', true);
        foreach ((array) $raw as $b) {
            if (is_string($b) && in_array($b, self::BADGES, true)) {
                $badge = $b;
                break;
            }
        }
        if ($preorder) {
            $badge = 'preorder';
        } elseif ($badge === '' && ! $soldOut && $r->low_stock_threshold !== null && $stock <= (int) $r->low_stock_threshold) {
            $badge = 'lowstock';
        }

        $colorKeys = $colors->map(fn ($c) => self::colorKey($c))->values()->all();
        $imgUrls = $images->pluck('image_url')->values()->all();

        $colorImages = [];
        foreach ($images as $img) {
            foreach ($imageColors[$img->id] ?? [] as $ic) {
                if (isset($colorById[$ic->color_id])) {
                    $colorImages[self::colorKey($colorById[$ic->color_id])][] = $img->image_url;
                }
            }
        }

        return [
            'id' => (int) $r->id,
            'slug' => $r->slug,
            'sku' => $r->sku,
            'name' => $r->name,
            'desc' => $r->short_description ?? '',
            'fullDesc' => $r->description ?? '',
            'price' => $selling,
            'originalPrice' => $selling < $price ? $price : null,
            'image' => $imgUrls[0] ?? null,
            'images' => $imgUrls,
            // plain arrays only: the cache (serializable_classes=false) turns stdClass into __PHP_Incomplete_Class
            'colorImages' => $colorImages,
            'badge' => $badge,
            'category' => $r->category,
            'categoryName' => $r->category_name,
            'subcategory' => $r->subcategory,
            'brand' => $r->brand,
            'colors' => $colorKeys,
            'colorNames' => $colors->mapWithKeys(fn ($c) => [self::colorKey($c) => $c->name])->all(),
            'sizes' => $sizes->pluck('size_label')->values()->all(),
            'outOfStockSizes' => $hasVariants ? array_values(array_filter($sizes->pluck('size_label')->all(), fn ($s) => ($stockBySize[$s] ?? 0) <= 0)) : [],
            'outOfStockColors' => $hasVariants ? array_values(array_filter($colorKeys, fn ($c) => ($stockByColor[$c] ?? 0) <= 0)) : [],
            'variants' => $variantRows,
            'hasVariants' => $hasVariants,
            'availability' => $preorder ? 'preorder' : ($soldOut ? 'soldout' : ($badge === 'lowstock' ? 'lowstock' : 'instock')),
            'soldOut' => $soldOut,
            'stockQty' => $stock,
            'releaseDate' => $r->preorder_release_date,
            'sizeGuideKey' => $r->size_guide_key,
            'specs' => array_filter([
                'material' => $r->material, 'fabric' => $r->fabric, 'dimensions' => $r->dimensions,
                'fit' => $r->fit, 'pattern' => $r->pattern, 'care' => $r->care_instructions,
            ]),
            'rating' => $rating ? round((float) $rating->avg_rating, 1) : null,
            'reviewCount' => $rating ? (int) $rating->review_count : 0,
            'isDisabled' => false,
            'createdAt' => $r->created_at,
        ];
    }

    /** Size guide (attributes x sizes grid) for a size_guides.key. */
    public static function sizeGuide(?string $key): ?array
    {
        if (! $key) {
            return null;
        }
        $g = DB::table('size_guides')->where('key', $key)->where('status', 'active')->first(['id', 'title', 'customer_details']);
        if (! $g) {
            return null;
        }
        $attrs = DB::table('size_guide_attributes')->where('size_guide_id', $g->id)->orderBy('sort_order')->get(['id', 'attribute_name', 'unit']);
        $vals = DB::table('size_guide_values')->where('size_guide_id', $g->id)->orderBy('sort_order')->get(['size_label', 'attribute_id', 'measurement_value']);
        $rows = [];
        foreach ($vals as $v) {
            $rows[$v->size_label][$v->attribute_id] = (float) $v->measurement_value;
        }

        return [
            'title' => $g->title, 'details' => $g->customer_details,
            'attributes' => $attrs->map(fn ($a) => ['id' => (int) $a->id, 'name' => $a->attribute_name, 'unit' => $a->unit])->all(),
            'rows' => $rows,
        ];
    }
}
