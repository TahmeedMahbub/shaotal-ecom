<?php

namespace App\Http\Controllers;

use App\Models\Catalog;
use App\Models\Listing;
use App\Models\Storefront;
use App\Payments\PaymentMethods;
use App\Support\Img;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PageController extends Controller
{
    private const DEFAULT_LISTING_DESC = 'Browse handcrafted fashion, home decor, natural jute products, artisan pieces and gifts curated for Australia.';

    public function home()
    {
        $catalog = Storefront::catalog();

        $homeIds = Storefront::homeProductIds();
        // "Popular Right Now": 5 random products of the home set, server-rendered; the script keeps this pick for the
        // visitor's session (getStablePopularProducts in app.js).
        $pool = Listing::byIds($homeIds);
        shuffle($pool);

        return view('pages.index', [
            'catalogProducts' => $homeIds,
            'popular' => array_slice($pool, 0, 5),
            'canonical' => Seo::abs('/'),
            'jsonLd' => [Seo::organization($catalog['social'] ?? []), Seo::website()],
        ]);
    }

    /**
     * Listing: /products, /products?category=x[&subcategory=y]. Only those two parameters define an indexable page;
     * sort, tracking and paging parameters are ignored by the canonical URL, and search/brand/pre-order views (and unknown
     * slugs) are noindex,follow so filter combinations cannot create unlimited indexable duplicates.
     */
    public function products(Request $request)
    {
        $catalog = Storefront::catalog();
        $slug = $this->param($request, 'category');
        $sub = $this->param($request, 'subcategory');
        $search = $this->param($request, 'search');
        $brand = $this->param($request, 'brand');

        $cat = $slug !== '' ? collect($catalog['categories'])->firstWhere('slug', $slug) : null;
        $subRow = $sub !== '' ? collect($catalog['subcategories'])->firstWhere('slug', $sub) : null;
        $indexable = $search === '' && $brand === '';

        $title = 'All Products';
        $desc = self::DEFAULT_LISTING_DESC;
        $crumbs = [['Home', '/'], ['Shop', '/products']];
        $canonicalPath = '/products';
        if ($slug === 'preorder') {
            $title = 'Pre-order';
            $desc = 'Reserve upcoming artisan pieces before they arrive.';
            $indexable = false;
        } elseif ($slug !== '' && $slug !== 'all') {
            if ($cat) {
                $title = $cat['name'];
                $desc = trim(strip_tags($cat['desc'])) ?: "Shop {$cat['name']} at ".config('storefront.brand').': '.lcfirst(self::DEFAULT_LISTING_DESC);
                $canonicalPath = Seo::listingUrl($cat['slug']);
                $crumbs[] = [$cat['name'], $canonicalPath];
                if ($sub !== '') {
                    if ($subRow && $subRow['category'] === $cat['slug']) {
                        $title = $subRow['name'].' — '.$cat['name'];
                        $desc = "Shop {$subRow['name']} in {$cat['name']} at ".config('storefront.brand').'. '.$desc;
                        $canonicalPath = Seo::listingUrl($cat['slug'], $subRow['slug']);
                        $crumbs[] = [$subRow['name'], $canonicalPath];
                    } else {
                        $indexable = false;
                    }
                }
            } else {
                $indexable = false;   // unknown category: the page shows all products, never a separate indexable URL
            }
        } elseif ($sub !== '') {
            $indexable = false;
        }
        $crumbs[array_key_last($crumbs)][1] = null;   // current page

        // First page server-side (same filters the script sends to /catalog/feed); only these products are embedded.
        $filters = Listing::filters($request->query());
        $first = Listing::page($filters, 1);

        return view('pages.products', [
            'pageTitle' => $title,
            'pageDescription' => Seo::text($desc),
            'robots' => $indexable ? 'index, follow' : 'noindex, follow',
            'canonical' => $indexable ? Seo::abs($canonicalPath) : null,
            'heading' => $subRow && $cat && $subRow['category'] === $cat['slug'] ? $subRow['name'] : $title,
            'initialProducts' => $first['products'],
            'totalCount' => $first['total'],
            'listingData' => $first + ['facets' => Listing::facets(), 'filters' => $filters],
            'catalogProducts' => array_column($first['products'], 'id'),
            'jsonLd' => $indexable ? [Seo::breadcrumbs($crumbs)] : [],
        ]);
    }

    public function product(string $slug)
    {
        // Read live from the DB (not the up-to-6-min-stale catalog cache): status/visibility, size guide and the SEO fields.
        $row = DB::table('products as p')
            ->join('categories as c', fn ($j) => $j->on('c.id', '=', 'p.category_id')->where('c.status', 'active'))
            ->leftJoin('size_guides as g', fn ($j) => $j->on('g.id', '=', 'p.size_guide_id')->where('g.status', 'active'))
            ->where('p.slug', $slug)->where('p.status', 'active')->where('p.visibility', 'visible')
            ->first(['p.id', 'p.slug', 'p.seo_title', 'p.seo_description', 'g.key as size_guide_key']);
        abort_unless($row, 404);
        // One URL per product: a differently-cased slug (the column collation is case-insensitive) redirects to the real one.
        if ($row->slug !== $slug) {
            return redirect(Seo::productUrl($row->slug), 301);
        }

        $product = Catalog::products([(int) $row->id])[0] ?? null;
        abort_unless($product, 404);

        $title = trim((string) $row->seo_title) ?: $product['name'].' — '.$product['categoryName'];
        $desc = Seo::text($row->seo_description) ?: Seo::text($product['desc']) ?: Seo::text($product['fullDesc'])
            ?: $product['name'].' — '.$product['categoryName'].' by '.config('storefront.brand').'.';
        $catUrl = Seo::listingUrl($product['category']);
        $gallery = $this->gallery($product);

        return view('pages.product-detail', [
            'page' => ['slug' => $product['slug']],
            'product' => $product,
            'gallery' => $gallery,
            'initialPrice' => $this->initialPrice($product),
            'initialOptions' => $this->initialOptions($product),
            'sizeOffered' => fn ($color, $size) => $this->sizeOffered($product, $color, $size),
            'related' => $related = $this->related($product),
            'catalogProducts' => array_merge([$product['id']], array_column($related, 'id')),
            'sizeGuideKey' => $row->size_guide_key,
            'preloadImage' => $gallery[0] ?? null,
            'pageTitle' => $title,
            'pageDescription' => $desc,
            'canonical' => Seo::abs(Seo::productUrl($product['slug'])),
            'ogImage' => Seo::abs(Img::url($gallery[0] ?? null, 1200)),
            'jsonLd' => [
                Seo::product($product),
                Seo::breadcrumbs([['Home', '/'], [$product['categoryName'], $catUrl], [$product['name'], null]]),
            ],
        ]);
    }

    /** Gallery order the detail page shows first (same rule as generateGalleryImages() in product-detail.js). */
    private function gallery(array $p): array
    {
        $byColor = $p['colorImages'] ?? [];
        $specific = array_merge([], ...array_values($byColor));
        $color = $p['colors'][0] ?? null;

        return ($color !== null && ! empty($byColor[$color]))
            ? array_values(array_merge(array_values(array_diff($p['images'], $specific)), $byColor[$color]))
            : array_values($p['images']);
    }

    /** Options selected on load: first colour + first size offered in it (getInitialSize() in product-detail.js). */
    private function initialOptions(array $p): array
    {
        $color = $p['colors'][0] ?? null;
        $size = null;
        foreach ($p['sizes'] ?? [] as $s) {
            if (! $p['hasVariants'] || $this->sizeOffered($p, $color, $s)) {
                $size = $s;
                break;
            }
        }

        return [$color, $size ?? ($p['sizes'][0] ?? null)];
    }

    /** sizeUnavailable() in product-detail.js, inverted. */
    private function sizeOffered(array $p, ?string $color, string $size): bool
    {
        if (empty($p['hasVariants'])) {
            return true;
        }
        foreach ($p['variants'] as $v) {
            if ($v['size'] === $size && ($v['color'] === null || $v['color'] === $color)) {
                return true;
            }
        }

        return false;
    }

    /** Price shown before any option is picked (variantPrice() in app.js for the initial options). */
    private function initialPrice(array $p): float
    {
        if (empty($p['hasVariants'])) {
            return (float) $p['price'];
        }
        [$color, $size] = $this->initialOptions($p);
        foreach ($p['variants'] as $v) {
            if ($v['color'] === $color && $v['size'] === $size) {
                return (float) $v['price'];
            }
        }

        return (float) $p['price'];
    }

    /** "You May Also Like": same rule as renderRelatedProducts() in product-detail.js. */
    private function related(array $current): array
    {
        $all = array_values(array_filter(Storefront::catalog()['products'], fn ($p) => $p['id'] !== $current['id']));
        $matches = array_slice(array_values(array_filter($all, fn ($p) => $p['category'] === $current['category'] || ($current['brand'] && $p['brand'] === $current['brand']))), 0, 4);
        if (count($matches) < 4) {
            $ids = array_column($matches, 'id');
            $matches = array_merge($matches, array_slice(array_values(array_filter($all, fn ($p) => ! in_array($p['id'], $ids, true))), 0, 4 - count($matches)));
        }

        return $matches;
    }

    /** A single string query parameter (arrays and over-long values are ignored). */
    private function param(Request $request, string $key): string
    {
        $v = $request->query($key);

        return is_string($v) ? Str::limit(trim($v), 120, '') : '';
    }

    public function sizeGuide(string $key)
    {
        $guide = Catalog::sizeGuide($key);
        abort_unless($guide, 404);

        return response()->json($guide)->header('X-Robots-Tag', 'noindex');
    }

    public function checkout()
    {
        // Checkout is for signed-in customers only; guests are sent to the sign-in prompt and brought back.
        if (! session('customer_id')) {
            return redirect('/?signin=/checkout');
        }

        // Saved addresses are embedded so the address step renders without a second round trip (no layout shift).
        return response()->view('pages.checkout', ['methods' => PaymentMethods::enabled(), 'addresses' => AccountController::addressList((int) session('customer_id'))])
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'private, no-store');
    }

    public function account()
    {
        return response()->view('pages.account')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function bulkOrder()
    {
        // The bulk order form lists every product with its variants, so this page alone embeds the whole catalog.
        return view('pages.bulk-order', ['canonical' => Seo::abs('/bulk-order'), 'catalogProducts' => 'all']);
    }

    public function sourcing()
    {
        return view('pages.request', ['canonical' => Seo::abs('/request')]);
    }
}
