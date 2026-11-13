<?php

namespace App\Http\Controllers;

use App\Models\Catalog;
use App\Models\Listing;
use App\Models\Storefront;
use App\Payments\PaymentMethods;
use App\Support\EmailVerification;
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
        $parentCrumb = count($crumbs) === 4 ? ['name' => $crumbs[2][0], 'url' => $crumbs[2][1]] : null;   // subcategory page
        $crumbs[array_key_last($crumbs)][1] = null;   // current page

        // The requested page server-side (same filters the script sends to /catalog/feed); only these products are
        // embedded. ?page=N makes every product reachable through plain links: the script continues from page N+1.
        $filters = Listing::filters($request->query());
        $pageNo = $this->pageNumber($request);
        $data = Listing::page($filters, $pageNo);
        $last = max(1, (int) ceil($data['total'] / Listing::PAGE_SIZE));
        abort_if($pageNo > $last, 404);   // no empty (soft 404) pages beyond the end

        // Links point at the canonical form of the listing (no sort/tracking parameters); noindex views keep their query.
        $linkBase = $indexable ? $canonicalPath : '/products'.(($q = http_build_query(array_diff_key($request->query(), ['page' => 1]))) !== '' ? '?'.$q : '');
        $pageUrl = fn (int $n) => $n === 1 ? $linkBase : $linkBase.(str_contains($linkBase, '?') ? '&' : '?').'page='.$n;
        $selfPath = $pageUrl($pageNo);
        $pageDesc = Seo::text(($pageNo > 1 ? "Page {$pageNo} of {$last}. " : '').$desc);
        $heading = $subRow && $cat && $subRow['category'] === $cat['slug'] ? $subRow['name'] : $title;
        if ($pageNo > 1) {
            $title .= " — Page {$pageNo}";
        }

        return view('pages.products', [
            'pageTitle' => $title,
            'pageDescription' => $pageDesc,
            'robots' => $indexable ? 'index, follow' : 'noindex, follow',
            'canonical' => $indexable ? Seo::abs($selfPath) : null,
            // Share image: the category's own admin image when it has one (else the layout's logo default).
            'ogImage' => $cat && ! empty($cat['image']) ? Seo::abs(Img::url($cat['image'], 1200)) : null,
            'heading' => $heading,
            'parentCrumb' => $parentCrumb,
            'initialProducts' => $data['products'],
            'totalCount' => $data['total'],
            'pagination' => [
                'page' => $pageNo, 'last' => $last,
                'prev' => $pageNo > 1 ? $pageUrl($pageNo - 1) : null,
                'next' => $pageNo < $last ? $pageUrl($pageNo + 1) : null,
                'links' => array_map(fn ($n) => ['n' => $n, 'url' => $pageUrl($n)], $this->pageWindow($pageNo, $last)),
            ],
            'listingData' => $data + ['facets' => Listing::facets(), 'filters' => $filters],
            'catalogProducts' => array_column($data['products'], 'id'),
            'jsonLd' => $indexable ? [
                Seo::breadcrumbs($crumbs),
                Seo::collectionPage(Seo::abs($selfPath), $title, $pageDesc, $data['products'], ($pageNo - 1) * Listing::PAGE_SIZE, $data['total']),
            ] : [],
        ]);
    }

    /** ?page=N as a positive integer without leading zeros; anything else is page 1 (same canonical as no parameter). */
    private function pageNumber(Request $request): int
    {
        $v = $request->query('page');

        return is_string($v) && preg_match('/^[1-9][0-9]{0,5}$/', $v) ? (int) $v : 1;
    }

    /** Page numbers to link: all when few, else first, last and two either side of the current one. */
    private function pageWindow(int $page, int $last): array
    {
        if ($last <= 9) {
            return range(1, $last);
        }

        return array_values(array_unique(array_merge([1], range(max(2, $page - 2), min($last - 1, $page + 2)), [$last])));
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

    /**
     * "You May Also Like": the next 4 products after this one in its category (or brand), wrapping around. Rotating
     * instead of always taking the first 4 means every product is linked from the product before it, so no product page
     * is an orphan for crawlers. The script (renderRelatedProducts in product-detail.js) shows the products embedded here.
     */
    private function related(array $current): array
    {
        $catalog = Storefront::catalog()['products'];
        $pool = array_values(array_filter($catalog, fn ($p) => $p['id'] === $current['id'] || $p['category'] === $current['category'] || ($current['brand'] && $p['brand'] === $current['brand'])));
        $at = array_search($current['id'], array_column($pool, 'id'), true);
        $rotated = $at === false ? $pool : array_merge(array_slice($pool, $at + 1), array_slice($pool, 0, $at));
        $all = array_values(array_filter($catalog, fn ($p) => $p['id'] !== $current['id']));
        $matches = array_slice(array_values(array_filter($rotated, fn ($p) => $p['id'] !== $current['id'])), 0, 4);
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
        // Mid-verification: the account page waits until the email is confirmed.
        if (! session('customer_id') && session(EmailVerification::PENDING)) {
            return redirect('/verify-email');
        }

        return response()->view('pages.account')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** Information pages: slug => [heading, meta description]. */
    public const INFO_PAGES = [
        'contact' => ['Contact Us', 'Send a message to the Shaotal team about an order, a product or a request.'],
        'shipping' => ['Shipping', 'Where Shaotal delivers, shipping costs and how to follow your order.'],
        'returns' => ['Returns', 'What to do if something is not right with your Shaotal order.'],
        'terms' => ['Terms of Sale', 'How buying from Shaotal works: accounts, prices, payment and orders.'],
        'privacy' => ['Privacy', 'What personal information the Shaotal website collects and how it is used.'],
    ];

    /** Pages whose wording the owner has approved (indexable, in the sitemap). */
    public static function approvedInfoPages(): array
    {
        return array_values(array_intersect(array_keys(self::INFO_PAGES), (array) config('storefront.approved_pages')));
    }

    public function info(string $slug)
    {
        abort_unless(isset(self::INFO_PAGES[$slug]), 404);
        [$heading, $desc] = self::INFO_PAGES[$slug];
        $approved = in_array($slug, self::approvedInfoPages(), true);

        return view('pages.info.'.$slug, [
            'slug' => $slug, 'heading' => $heading, 'approved' => $approved,
            'pageTitle' => $heading, 'pageDescription' => $desc,
            // Drafts are not indexed until approved (they are still public and linked for customers).
            'robots' => $approved ? 'index, follow' : 'noindex, follow',
            'canonical' => $approved ? Seo::abs('/'.$slug) : null,
            'jsonLd' => $approved ? [Seo::webPage($slug === 'contact' ? 'ContactPage' : 'WebPage', '/'.$slug, $heading, $desc)] : [],
        ]);
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
