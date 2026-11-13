<?php

namespace Tests\Feature;

use App\Http\Middleware\RedirectTrailingSlash;
use App\Models\Listing;
use App\Models\Storefront;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/**
 * Part 7: crawlability and indexing on top of Part 4 (SeoTest). Product discovery through paginated listing URLs and
 * related-product links (no orphans), one canonical URL per page, crawl directives that do not hide noindex, structured
 * data that matches the visible page, the image sitemap, and indexing safety. Raw server HTML only (no JavaScript).
 */
class SeoCrawlabilityTest extends TestCase
{
    use DatabaseTransactions;
    use StorefrontFixtures;

    private const BASE = 'https://shop.example';

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
        config(['app.url' => self::BASE]);
    }

    /** Adds $n simple Fashion / T-Shirts products (the seed has only 5 products, fewer than one listing page). */
    private function addFashionProducts(int $n, float $price = 30.0): array
    {
        $slugs = [];
        for ($i = 1; $i <= $n; $i++) {
            $slug = "seo-crawl-tee-{$i}";
            $id = DB::table('products')->insertGetId([
                'category_id' => 1, 'subcategory_id' => 1, 'brand_id' => null, 'name' => "Crawl Tee {$i}", 'slug' => $slug, 'sku' => "CRAWL-{$i}",
                'short_description' => "Crawl test tee number {$i}", 'description' => 'Plain cotton tee.', 'price' => $price, 'selling_price' => $price,
                'has_variants' => 0, 'stock_qty' => 5, 'availability' => 'in_stock', 'visibility' => 'visible', 'status' => 'active',
            ]);
            DB::table('product_images')->insert(['product_id' => $id, 'image_url' => "https://res.cloudinary.com/demo/image/upload/crawl-{$i}.jpg", 'is_main' => 1, 'sort_order' => 1]);
            $slugs[] = $slug;
        }
        Storefront::refresh();

        return $slugs;
    }

    private function html(string $uri): string
    {
        return $this->get($uri)->assertOk()->getContent();
    }

    private function canonical(string $html): ?string
    {
        return preg_match('/<link rel="canonical" href="([^"]*)"/', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5) : null;
    }

    private function robots(string $html): ?string
    {
        return preg_match('/<meta name="robots" content="([^"]*)"/', $html, $m) ? $m[1] : null;
    }

    private function title(string $html): string
    {
        preg_match('/<title>(.*?)<\/title>/s', $html, $m);

        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
    }

    /** Decoded JSON-LD blocks; invalid JSON fails the test. */
    private function jsonLd(string $html): array
    {
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);

        return array_map(fn ($j) => json_decode($j, true, 512, JSON_THROW_ON_ERROR), $m[1]);
    }

    private function ld(string $html, string $type): ?array
    {
        return collect($this->jsonLd($html))->firstWhere('@type', $type);
    }

    /** Product paths linked from the page (decoded hrefs). */
    private function productLinks(string $html): array
    {
        preg_match_all('#href="(/products/[^"?\#]+)"#', $html, $m);

        return array_values(array_unique($m[1]));
    }

    private function relLink(string $html, string $rel): ?string
    {
        return preg_match('/<a href="([^"]*)" rel="'.$rel.'"/', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5) : null;
    }

    /* ── Pagination: crawlable, self-canonical, no soft 404s ── */

    public function test_listing_pages_beyond_the_first_have_crawlable_self_canonical_urls(): void
    {
        $this->addFashionProducts(12);   // 13 fashion products = 2 pages of 8
        $first = $this->html('/products?category=fashion');
        $this->assertSame(self::BASE.'/products?category=fashion', $this->canonical($first));
        $this->assertStringContainsString('id="listing-pagination"', $first);
        $this->assertSame('/products?category=fashion&page=2', $this->relLink($first, 'next'));
        $this->assertNull($this->relLink($first, 'prev'));

        $second = $this->html('/products?category=fashion&page=2');
        $this->assertSame(self::BASE.'/products?category=fashion&page=2', $this->canonical($second));
        $this->assertSame('index, follow', $this->robots($second));
        $this->assertStringContainsString('Page 2', $this->title($second));
        $this->assertNotSame($this->title($first), $this->title($second));
        $this->assertSame('/products?category=fashion', $this->relLink($second, 'prev'));   // page 1 has no ?page=1
        $this->assertNull($this->relLink($second, 'next'));
        $this->assertCount(5, $this->productLinksIn($second));
        $this->assertSame([], array_intersect($this->productLinksIn($first), $this->productLinksIn($second)));
    }

    /** Product links inside the listing grid only (not nav/related). */
    private function productLinksIn(string $html): array
    {
        $start = strpos($html, 'id="listing-products-grid"');
        $end = strpos($html, 'id="infinite-scroll-sentinel"');

        return $this->productLinks(substr($html, $start, $end - $start));
    }

    public function test_paging_parameters_cannot_create_duplicate_or_soft_404_urls(): void
    {
        $this->addFashionProducts(12);
        $this->get('/products?category=fashion&page=3')->assertNotFound();      // beyond the last page
        $this->get('/products?category=fashion&page=999999')->assertNotFound();
        foreach (['page=1', 'page=0', 'page=abc', 'page[]=2', 'page=-2', 'page=02'] as $q) {
            $html = $this->html("/products?category=fashion&{$q}");
            $this->assertSame(self::BASE.'/products?category=fashion', $this->canonical($html), $q);
        }
        // Sort and tracking parameters never reach the canonical; the page number does.
        $this->assertSame(self::BASE.'/products?category=fashion&page=2', $this->canonical($this->html('/products?sort=price-low&utm_source=x&page=2&category=fashion')));
        // Search pages stay noindex on every page.
        $this->assertSame('noindex, follow', $this->robots($this->html('/products?search=tee&page=2')));
    }

    /* ── Product discovery: no orphan products ── */

    public function test_every_listed_product_is_reachable_through_listing_pages(): void
    {
        $this->addFashionProducts(20);
        $listed = DB::table('products as p')->join('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.status', 'active')->where('p.visibility', 'visible')->where('c.status', 'active')->pluck('p.slug')
            ->map(fn ($s) => '/products/'.$s)->sort()->values()->all();

        $found = [];
        $url = '/products';
        for ($guard = 0; $url !== null && $guard < 20; $guard++) {
            $html = $this->html($url);
            $found = array_merge($found, $this->productLinksIn($html));
            $url = $this->relLink($html, 'next');
        }
        sort($found);
        $this->assertSame($listed, array_values(array_unique($found)));
    }

    public function test_related_products_rotate_through_the_category_so_no_product_is_orphaned(): void
    {
        $slugs = $this->addFashionProducts(12);
        $linkedFrom = [];
        $relatedSets = [];
        foreach ($slugs as $slug) {
            $html = $this->html('/products/'.$slug);
            $start = strpos($html, 'id="related-products-grid"');
            $grid = substr($html, $start, strpos($html, '</section>', $start) - $start);
            $related = array_slice($this->productLinks($grid), 0, 4);
            $this->assertCount(4, $related, $slug);
            $this->assertNotContains('/products/'.$slug, $related);
            $relatedSets[] = $related;
            foreach ($related as $r) {
                $linkedFrom[$r] = true;
            }
        }
        // Different products link to different neighbours, and every added product is linked from another product.
        $this->assertGreaterThan(1, count(array_unique(array_map('serialize', $relatedSets))));
        foreach ($slugs as $slug) {
            $this->assertArrayHasKey('/products/'.$slug, $linkedFrom, $slug);
        }
    }

    /* ── Structured data consistent with the visible page ── */

    public function test_listing_collection_page_lists_the_products_shown_on_that_page(): void
    {
        $this->addFashionProducts(12);
        foreach ([1 => '/products?category=fashion', 2 => '/products?category=fashion&page=2'] as $page => $uri) {
            $html = $this->html($uri);
            $col = $this->ld($html, 'CollectionPage');
            $this->assertNotNull($col, $uri);
            $this->assertSame($this->canonical($html), $col['url']);
            $list = $col['mainEntity'];
            $this->assertSame('ItemList', $list['@type']);
            $this->assertSame(13, $list['numberOfItems']);
            $urls = array_column($list['itemListElement'], 'url');
            $this->assertSame(array_map(fn ($p) => self::BASE.$p, $this->productLinksIn($html)), $urls);
            $this->assertSame(($page - 1) * Listing::PAGE_SIZE + 1, $list['itemListElement'][0]['position']);
        }
        $this->assertNull($this->ld($this->html('/products?search=tee'), 'CollectionPage'));   // noindex pages: none
    }

    public function test_category_listing_shares_the_category_image(): void
    {
        $html = $this->html('/products?category=fashion');
        preg_match('/<meta property="og:image" content="([^"]*)"/', $html, $m);
        $this->assertStringStartsWith('https://res.cloudinary.com/demo/image/upload/', html_entity_decode($m[1]));   // seed category image
        preg_match('/<meta property="og:image" content="([^"]*)"/', $this->html('/products'), $m);
        $this->assertSame(self::BASE.'/assets/logos/logo.png', html_entity_decode($m[1]));                          // no category: logo
    }

    public function test_subcategory_breadcrumb_is_visible_and_matches_the_json_ld(): void
    {
        $html = $this->html('/products?category=fashion&subcategory=t-shirts');
        $crumbs = $this->ld($html, 'BreadcrumbList')['itemListElement'];
        $this->assertSame(['Home', 'Shop', 'Fashion', 'T-Shirts'], array_column($crumbs, 'name'));
        preg_match('/<div class="breadcrumb">(.*?)<\/div>/s', $html, $m);
        $this->assertSame(['Home', 'Shop', 'Fashion', 'T-Shirts'], array_values(array_filter(array_map('trim', preg_split('/\s*\/\s*/', strip_tags($m[1]))))));
        $this->assertStringContainsString('href="/products?category=fashion" id="breadcrumb-parent"', $m[1]);
    }

    public function test_single_price_offers_carry_the_confirmed_australian_shipping_rule(): void
    {
        $offer = $this->ld($this->html('/products/nakshi-kantha-cushion'), 'Product')['offers'];   // A$55: flat fee
        $this->assertSame('Offer', $offer['@type']);
        $this->assertSame(['@type' => 'MonetaryAmount', 'value' => '12.00', 'currency' => 'AUD'], $offer['shippingDetails']['shippingRate']);
        $this->assertSame(['@type' => 'DefinedRegion', 'addressCountry' => 'AU'], $offer['shippingDetails']['shippingDestination']);
        $this->assertArrayNotHasKey('deliveryTime', $offer['shippingDetails']);   // no delivery time was approved
        $this->assertArrayNotHasKey('hasMerchantReturnPolicy', $offer);            // no returns policy approved

        $slug = $this->addFashionProducts(1, 299.00)[0];                             // exactly the threshold: free
        $this->assertSame('0.00', $this->ld($this->html('/products/'.$slug), 'Product')['offers']['shippingDetails']['shippingRate']['value']);
    }

    public function test_info_pages_get_canonical_and_page_schema_only_once_approved(): void
    {
        $draft = $this->html('/contact');
        $this->assertSame('noindex, follow', $this->robots($draft));
        $this->assertNull($this->canonical($draft));
        $this->assertSame([], $this->jsonLd($draft));

        config(['storefront.approved_pages' => ['contact', 'shipping']]);
        $contact = $this->html('/contact');
        $this->assertSame('index, follow', $this->robots($contact));
        $this->assertSame(self::BASE.'/contact', $this->canonical($contact));
        $this->assertSame(self::BASE.'/contact', $this->ld($contact, 'ContactPage')['url']);
        $this->assertSame(self::BASE.'/shipping', $this->ld($this->html('/shipping'), 'WebPage')['url']);
        $this->assertNull($this->canonical($this->html('/terms')));   // still a draft
    }

    public function test_organization_contact_point_only_from_configured_details(): void
    {
        $this->assertArrayNotHasKey('contactPoint', $this->ld($this->html('/'), 'Organization'));
        config(['storefront.contact.email' => 'hello@shop.example']);
        $point = $this->ld($this->html('/'), 'Organization')['contactPoint'];
        $this->assertSame(['@type' => 'ContactPoint', 'contactType' => 'customer service', 'email' => 'hello@shop.example', 'areaServed' => 'AU'], $point);
    }

    /* ── URL normalisation ── */

    private function slash(string $uri, string $method = 'GET'): Response
    {
        // Laravel's test client trims trailing slashes from URIs, so the middleware is called directly here (the live
        // server is checked in the browser verification).
        return (new RedirectTrailingSlash)->handle(Request::create(self::BASE.$uri, $method), fn () => response('next'));
    }

    public function test_trailing_slash_urls_redirect_permanently_to_the_canonical_form(): void
    {
        $kernel = app(Kernel::class);
        $this->assertInstanceOf(HttpKernel::class, $kernel);
        $this->assertTrue($kernel->hasMiddleware(RedirectTrailingSlash::class));   // registered globally
        foreach (['/products/' => '/products', '/products/?category=fashion&page=2' => '/products?category=fashion&page=2',
            '/products/essential-heavyweight-tee/' => '/products/essential-heavyweight-tee', '/shipping//' => '/shipping'] as $from => $to) {
            $res = $this->slash($from);
            $this->assertSame(301, $res->getStatusCode(), $from);
            $this->assertSame(self::BASE.$to, $res->headers->get('Location'), $from);
        }
        foreach (['/', '/products', '/products?category=fashion'] as $uri) {
            $this->assertSame('next', $this->slash($uri)->getContent(), $uri);
        }
        $this->assertSame('next', $this->slash('/newsletter/', 'POST')->getContent());   // never redirect a POST body
        $this->get('/Products')->assertNotFound();                                       // no catch-all redirect to home
        $this->get('/products/no-such-product')->assertNotFound();
    }

    public function test_trailing_slash_redirect_cannot_be_used_as_an_open_redirect(): void
    {
        foreach (['//evil.example/', '///evil.example/', '/%5Cevil.example/', '/%2F%2Fevil.example/'] as $path) {
            $location = (string) $this->slash($path)->headers->get('Location');
            $this->assertStringStartsWith(self::BASE.'/', $location, $path);
            $this->assertSame('shop.example', parse_url($location, PHP_URL_HOST), $path);
            $this->assertStringNotContainsString('//evil', substr($location, strlen('https://')), $path);
        }
    }

    /* ── Crawl directives ── */

    public function test_robots_txt_lets_crawlers_read_noindex_on_private_pages(): void
    {
        $body = $this->get('/robots.txt')->assertOk()->getContent();
        // Blocking /account or /checkout would hide their noindex; they stay crawlable but noindex + server-protected.
        $this->assertDoesNotMatchRegularExpression('#^Disallow: /(account|checkout)#m', $body);
        foreach (['Disallow: /auth/', 'Disallow: /size-guides/', 'Disallow: /*?*search=', 'Disallow: /*?*brand=', 'Sitemap: '.self::BASE.'/sitemap.xml'] as $line) {
            $this->assertStringContainsString($line."\n", $body);
        }
        $this->assertDoesNotMatchRegularExpression('#^Disallow: /(assets|products|catalog|storage)#m', $body);
        $this->get('/account')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/catalog/feed?page=1')->assertHeader('X-Robots-Tag', 'noindex');
    }

    /* ── Sitemap ── */

    public function test_product_sitemap_lists_the_main_image_of_each_product(): void
    {
        $res = $this->get('/sitemap-products-1.xml')->assertOk();
        $xml = simplexml_load_string($res->getContent());
        $this->assertNotFalse($xml);
        $this->assertStringContainsString('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', $res->getContent());
        $images = [];
        foreach ($xml->url as $u) {
            $img = $u->children('http://www.google.com/schemas/sitemap-image/1.1')->image;
            $images[(string) $u->loc] = $img ? (string) $img->loc : null;
        }
        $this->assertStringStartsWith('https://res.cloudinary.com/', $images[self::BASE.'/products/nakshi-kantha-cushion']);
        foreach (array_filter($images) as $loc) {
            $this->assertMatchesRegularExpression('#^https?://#', $loc);
        }
        // Paginated listing URLs are discovered by links, not listed in the sitemap.
        $this->assertStringNotContainsString('page=', $this->get('/sitemap-pages.xml')->getContent());
    }

    /* ── Indexing safety ── */

    public function test_no_customer_data_in_metadata_or_structured_data_of_public_pages(): void
    {
        $id = $this->makeCustomer('seo-secret@example.test');
        DB::table('customers')->where('id', $id)->update(['first_name' => 'Zebedee', 'phone' => '0499111222']);
        $session = $this->signedInSession($id);
        foreach (['/', '/products', '/products?category=fashion', '/products/essential-heavyweight-tee', '/request'] as $uri) {
            $html = $this->withSession($session)->get($uri)->assertOk()->getContent();
            $head = substr($html, 0, strpos($html, '</head>'));
            $ld = implode(' ', array_map('json_encode', $this->jsonLd($html)));
            foreach (['seo-secret@example.test', 'Zebedee', '0499111222'] as $secret) {
                $this->assertStringNotContainsString($secret, $head, $uri);
                $this->assertStringNotContainsString($secret, $ld, $uri);
            }
        }
    }

    public function test_every_public_page_has_valid_json_ld_without_duplicate_types(): void
    {
        $this->addFashionProducts(12);
        config(['storefront.approved_pages' => ['contact']]);
        foreach (['/', '/products', '/products?category=fashion&page=2', '/products?category=fashion&subcategory=t-shirts', '/products/essential-heavyweight-tee', '/contact'] as $uri) {
            $blocks = $this->jsonLd($this->html($uri));
            $this->assertNotEmpty($blocks, $uri);
            $types = array_column($blocks, '@type');
            $this->assertSame($types, array_unique($types), $uri);
            foreach ($blocks as $b) {
                $this->assertSame('https://schema.org', $b['@context'], $uri);
                array_walk_recursive($b, function ($v, $k) use ($uri) {
                    if (in_array($k, ['url', 'item', '@id', 'logo'], true)) {
                        $this->assertStringStartsWith(self::BASE.'/', $v, "{$uri} {$k}");
                    }
                });
            }
        }
    }

    public function test_deploy_check_rejects_a_local_or_test_app_url(): void
    {
        foreach (['https://localhost', 'https://127.0.0.1:8000', 'https://shaotal.test'] as $url) {
            config(['app.url' => $url]);
            $this->artisan('storefront:deploy-check')->expectsOutputToContain('local/test host')->assertFailed();
        }
    }
}
