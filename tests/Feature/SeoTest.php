<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/**
 * Part 4: server-rendered content, metadata, canonical URLs, sitemap, robots.txt and JSON-LD, checked on the raw HTML
 * the server sends (no JavaScript). Seed: 1 variant tee (fashion/t-shirts), 2 cushion (home-decor/cushions),
 * 3 jute tote (low stock), 4 leather journal (pre-order, lifestyle), 5 jute floor mat (admin-flagged out_of_stock).
 */
class SeoTest extends TestCase
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

    private function html(string $uri): string
    {
        return $this->get($uri)->assertOk()->getContent();
    }

    private function meta(string $html, string $attr, string $name): ?string
    {
        return preg_match('/<meta '.$attr.'="'.preg_quote($name, '/').'" content="([^"]*)"/', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5) : null;
    }

    private function canonical(string $html): ?string
    {
        return preg_match('/<link rel="canonical" href="([^"]*)"/', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5) : null;
    }

    private function title(string $html): string
    {
        preg_match('/<title>(.*?)<\/title>/s', $html, $m);

        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
    }

    /** @return array<int, array> every JSON-LD block, decoded (fails the test on invalid JSON) */
    private function jsonLd(string $html): array
    {
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);

        return array_map(function ($json) {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('https://schema.org', $data['@context']);

            return $data;
        }, $m[1]);
    }

    private function ld(string $html, string $type): ?array
    {
        return collect($this->jsonLd($html))->firstWhere('@type', $type);
    }

    /* ── Product pages ── */

    public function test_product_content_is_in_the_initial_html(): void
    {
        $html = $this->html('/products/nakshi-kantha-cushion');

        $this->assertMatchesRegularExpression('/<h1 class="detail-title" id="detail-title">Nakshi Kantha Cushion<\/h1>/', $html);
        $this->assertStringContainsString('Hand-embroidered cotton cushion cover', $html);
        $this->assertStringContainsString('Embroidered by artisans.', $html);   // full description accordion
        $this->assertStringContainsString('id="detail-offered-price">A$55.00<', $html);
        $this->assertStringContainsString('In Stock — Ready to dispatch from Sydney', $html);
        $this->assertMatchesRegularExpression('/<img id="main-product-image" src="https:\/\/res\.cloudinary\.com\/demo\/image\/upload\/w_800\/cld-sample-2\.jpg"[^>]*width="800" height="800" alt="Nakshi Kantha Cushion" fetchpriority="high"/', $html);
        $this->assertStringContainsString('<a href="/products?category=home-decor" id="detail-breadcrumb-category-link">Home &amp; Living</a>', $html);
        // Related products are real links (also in the card the script renders).
        $this->assertMatchesRegularExpression('/id="related-products-grid">.*<a href="\/products\/[a-z-]+">/s', $html);
        $this->assertStringNotContainsString('Loading Product...', $html);
    }

    public function test_product_metadata_canonical_and_social_tags(): void
    {
        $html = $this->html('/products/nakshi-kantha-cushion');

        $this->assertSame('Nakshi Kantha Cushion — Home & Living | Shaotal', $this->title($html));
        $this->assertSame('Hand-embroidered cotton cushion cover', $this->meta($html, 'name', 'description'));
        $this->assertSame('index, follow', $this->meta($html, 'name', 'robots'));
        $this->assertSame(self::BASE.'/products/nakshi-kantha-cushion', $this->canonical($html));
        $this->assertSame(self::BASE.'/products/nakshi-kantha-cushion', $this->meta($html, 'property', 'og:url'));
        $this->assertSame('product', $this->meta($html, 'property', 'og:type'));
        $this->assertStringStartsWith('https://res.cloudinary.com/', $this->meta($html, 'property', 'og:image'));
        $this->assertSame('summary_large_image', $this->meta($html, 'name', 'twitter:card'));
        $this->assertSame($this->title($html), $this->meta($html, 'name', 'twitter:title'));
    }

    public function test_admin_seo_fields_override_title_and_description(): void
    {
        DB::table('products')->where('id', 2)->update(['seo_title' => 'Embroidered Kantha Cushion Cover', 'seo_description' => 'Hand-stitched by artisans in Bangladesh.']);
        $html = $this->html('/products/nakshi-kantha-cushion');

        $this->assertSame('Embroidered Kantha Cushion Cover | Shaotal', $this->title($html));
        $this->assertSame('Hand-stitched by artisans in Bangladesh.', $this->meta($html, 'name', 'description'));
    }

    public function test_product_titles_and_descriptions_are_unique(): void
    {
        $titles = $descs = [];
        foreach (['essential-heavyweight-tee', 'nakshi-kantha-cushion', 'handwoven-jute-tote', 'leather-journal', 'jute-floor-mat'] as $slug) {
            $html = $this->html('/products/'.$slug);
            $titles[] = $this->title($html);
            $descs[] = $this->meta($html, 'name', 'description');
            $this->assertLessThanOrEqual(160, mb_strlen($descs[array_key_last($descs)]));
        }
        $this->assertSame($titles, array_unique($titles));
        $this->assertSame($descs, array_unique($descs));
    }

    public function test_product_json_ld_uses_real_data_and_no_ratings(): void
    {
        $html = $this->html('/products/nakshi-kantha-cushion');
        $p = $this->ld($html, 'Product');

        $this->assertSame('Nakshi Kantha Cushion', $p['name']);
        $this->assertSame(self::BASE.'/products/nakshi-kantha-cushion', $p['url']);
        $this->assertSame('CUSH-001', $p['sku']);
        $this->assertSame(['@type' => 'Brand', 'name' => 'Heritage Weaves'], $p['brand']);
        $this->assertSame('Offer', $p['offers']['@type']);
        $this->assertSame('55.00', $p['offers']['price']);
        $this->assertSame('AUD', $p['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $p['offers']['availability']);
        $this->assertStringStartsWith('https://', $p['image'][0]);
        foreach (['aggregateRating', 'review', 'itemCondition', 'gtin'] as $absent) {
            $this->assertArrayNotHasKey($absent, $p);
        }

        $crumbs = $this->ld($html, 'BreadcrumbList')['itemListElement'];
        $this->assertSame(['Home', 'Home & Living', 'Nakshi Kantha Cushion'], array_column($crumbs, 'name'));
        $this->assertSame(self::BASE.'/products?category=home-decor', $crumbs[1]['item']);
        $this->assertArrayNotHasKey('item', $crumbs[2]);
        $this->assertCount(2, $this->jsonLd($html));   // no duplicate/conflicting blocks
    }

    public function test_availability_follows_eligibility_not_stock_count(): void
    {
        DB::table('products')->where('id', 2)->update(['stock_qty' => 0]);
        $this->assertSame('https://schema.org/InStock', $this->ld($this->html('/products/nakshi-kantha-cushion'), 'Product')['offers']['availability']);
        $this->assertSame('https://schema.org/PreOrder', $this->ld($this->html('/products/leather-journal'), 'Product')['offers']['availability']);

        $mat = $this->html('/products/jute-floor-mat');
        $this->assertSame('https://schema.org/OutOfStock', $this->ld($mat, 'Product')['offers']['availability']);
        $this->assertStringContainsString('Currently unavailable', $mat);
    }

    public function test_variant_price_range_becomes_an_aggregate_offer(): void
    {
        $single = $this->ld($this->html('/products/essential-heavyweight-tee'), 'Product')['offers'];
        $this->assertSame(['Offer', '26.00'], [$single['@type'], $single['price']]);   // every variant sells at A$26

        DB::table('product_variants')->where('sku', 'TEE-001-BK-L')->update(['selling_price_override' => 30]);
        $agg = $this->ld($this->html('/products/essential-heavyweight-tee'), 'Product')['offers'];
        $this->assertSame(['AggregateOffer', '26.00', '30.00', 6], [$agg['@type'], $agg['lowPrice'], $agg['highPrice'], $agg['offerCount']]);
    }

    public function test_json_ld_and_meta_cannot_be_broken_out_of(): void
    {
        DB::table('products')->where('id', 2)->update(['name' => 'Cushion</script><script>alert(1)</script>"><b>', 'short_description' => '"><img src=x onerror=alert(1)>']);
        $html = $this->html('/products/nakshi-kantha-cushion');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertSame('Cushion</script><script>alert(1)</script>"><b>', $this->ld($html, 'Product')['name']);
    }

    public function test_slug_case_variants_redirect_to_the_canonical_url(): void
    {
        $this->get('/products/Nakshi-Kantha-Cushion')->assertStatus(301)->assertRedirect('/products/nakshi-kantha-cushion');
    }

    public function test_unlisted_products_are_404(): void
    {
        foreach ([['status' => 'draft'], ['status' => 'inactive'], ['visibility' => 'hidden']] as $change) {
            DB::table('products')->where('id', 2)->update($change + ['status' => 'active', 'visibility' => 'visible']);
            $this->get('/products/nakshi-kantha-cushion')->assertNotFound();
        }
    }

    public function test_product_without_images_uses_the_placeholder_not_null(): void
    {
        DB::table('product_images')->where('product_id', 2)->delete();
        $html = $this->html('/products/nakshi-kantha-cushion');

        $this->assertStringContainsString('<img id="main-product-image" src="data:image/svg+xml;utf8,', $html);
        $this->assertDoesNotMatchRegularExpression('/(src|href)="\/?null"/', $html);
        $this->assertArrayNotHasKey('image', $this->ld($html, 'Product'));
    }

    /* ── Listing / category pages ── */

    public function test_listing_renders_product_cards_and_category_heading_server_side(): void
    {
        $html = $this->html('/products?category=home-decor');

        $this->assertStringContainsString('<h1 class="listing-title" id="listing-title">Home &amp; Living</h1>', $html);
        $this->assertStringContainsString('<a href="/products/nakshi-kantha-cushion">Nakshi Kantha Cushion</a>', $html);
        $this->assertStringNotContainsString('/products/handwoven-jute-tote"', $html);   // other category
        $this->assertStringContainsString('Showing 1 product', $html);
        $this->assertSame('Home & Living | Shaotal', $this->title($html));
        $this->assertSame('Handcrafted accents, textiles, ceramics and natural materials.', $this->meta($html, 'name', 'description'));
    }

    public function test_all_products_listing_first_batch_has_links_and_lazy_images_below_the_fold(): void
    {
        $html = $this->html('/products');
        preg_match_all('/<div class="product-card-name"><a href="(\/products\/[^"]+)">/', $html, $m);
        $this->assertCount(5, $m[1]);
        $this->assertSame(4, substr_count($html, 'loading="eager" fetchpriority="high"'));   // first row only
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    public function test_listing_canonicals_ignore_sort_tracking_and_param_order(): void
    {
        $cases = [
            '/products' => '/products',
            '/products?sort=price-low&utm_source=mail&page=3' => '/products',
            '/products?category=fashion' => '/products?category=fashion',
            '/products?sort=newest&category=fashion&gclid=x' => '/products?category=fashion',
            '/products?subcategory=t-shirts&category=fashion' => '/products?category=fashion&subcategory=t-shirts',
            '/products?category=all' => '/products',
            '/products?category[]=x' => '/products',   // array parameters are ignored
        ];
        foreach ($cases as $uri => $expected) {
            $html = $this->html($uri);
            $this->assertSame(self::BASE.$expected, $this->canonical($html), $uri);
            $this->assertSame('index, follow', $this->meta($html, 'name', 'robots'), $uri);
        }
        $this->assertSame('T-Shirts — Fashion | Shaotal', $this->title($this->html('/products?category=fashion&subcategory=t-shirts')));
    }

    public function test_search_filter_and_unknown_listing_urls_are_noindex_without_canonical(): void
    {
        foreach ([
            '/products?search=jute', '/products?brand=Sonali%20Jute', '/products?category=fashion&search=tee',
            '/products?category=nope', '/products?category=fashion&subcategory=cushions', '/products?subcategory=t-shirts',
            '/products?category=preorder',
        ] as $uri) {
            $html = $this->html($uri);
            $this->assertSame('noindex, follow', $this->meta($html, 'name', 'robots'), $uri);
            $this->assertNull($this->canonical($html), $uri);
            $this->assertNull($this->meta($html, 'property', 'og:url'), $uri);
        }
    }

    public function test_category_breadcrumb_json_ld(): void
    {
        $crumbs = $this->ld($this->html('/products?category=fashion&subcategory=t-shirts'), 'BreadcrumbList')['itemListElement'];
        $this->assertSame(['Home', 'Shop', 'Fashion', 'T-Shirts'], array_column($crumbs, 'name'));
        $this->assertSame(self::BASE.'/products?category=fashion', $crumbs[2]['item']);
        $this->assertArrayNotHasKey('item', $crumbs[3]);
    }

    /* ── Home and site-wide ── */

    public function test_home_has_h1_canonical_organization_and_website(): void
    {
        $html = $this->html('/?utm_source=x');

        $this->assertStringContainsString('<h1 class="hero-headline">Thoughtfully Crafted. Curated for Australia.</h1>', $html);
        $this->assertSame(self::BASE.'/', $this->canonical($html));
        $org = $this->ld($html, 'Organization');
        $this->assertSame('Shaotal', $org['name']);
        $this->assertSame(self::BASE.'/assets/logos/logo.png', $org['logo']);
        $this->assertSame(self::BASE.'/', $this->ld($html, 'WebSite')['url']);
        $this->assertNull($this->ld($html, 'Product'));
    }

    public function test_footer_category_links_are_server_rendered(): void
    {
        $html = $this->html('/');
        foreach (['/products?category=fashion', '/products?category=home-decor', '/products?category=jute', '/products?category=lifestyle'] as $href) {
            $this->assertStringContainsString('<a href="'.$href.'">', $html);
        }
    }

    public function test_public_pages_have_unique_titles_and_canonicals(): void
    {
        $seen = [];
        foreach (['/' => '/', '/products' => '/products', '/bulk-order' => '/bulk-order', '/request' => '/request', '/products?category=jute' => '/products?category=jute'] as $uri => $canon) {
            $html = $this->html($uri);
            $this->assertSame(self::BASE.$canon, $this->canonical($html), $uri);
            $this->assertNotEmpty($this->meta($html, 'name', 'description'), $uri);
            $seen[] = $this->title($html);
        }
        $this->assertSame($seen, array_unique($seen));
    }

    /* ── Private pages ── */

    public function test_private_pages_are_noindex(): void
    {
        $account = $this->get('/account')->assertOk();
        $account->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertSame('noindex, nofollow', $this->meta($account->getContent(), 'name', 'robots'));
        $this->assertNull($this->canonical($account->getContent()));

        $id = $this->makeCustomer('seo-private@example.test');
        $checkout = $this->withSession($this->signedInSession($id))->get('/checkout')->assertOk();
        $checkout->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertSame('noindex, nofollow', $this->meta($checkout->getContent(), 'name', 'robots'));
        $this->assertStringNotContainsString('seo-private@example.test', (string) $this->meta($checkout->getContent(), 'name', 'description'));

        $this->get('/size-guides/apparel')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
    }

    /* ── robots.txt ── */

    public function test_robots_txt(): void
    {
        $res = $this->get('/robots.txt')->assertOk();
        $this->assertStringStartsWith('text/plain', $res->headers->get('Content-Type'));
        $body = $res->getContent();

        foreach (['Disallow: /account', 'Disallow: /checkout', 'Disallow: /auth/', 'Sitemap: '.self::BASE.'/sitemap.xml'] as $line) {
            $this->assertStringContainsString($line."\n", $body);
        }
        $this->assertDoesNotMatchRegularExpression('/^Disallow: \/(assets|products|css|js|$)/m', $body);
        $this->assertFileDoesNotExist(public_path('robots.txt'));   // a static file would shadow this route
    }

    /* ── Sitemap ── */

    private function xml(string $uri): \SimpleXMLElement
    {
        $res = $this->get($uri)->assertOk();
        $this->assertStringStartsWith('application/xml', $res->headers->get('Content-Type'));
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($res->getContent());
        $this->assertNotFalse($xml, 'invalid XML: '.$uri);
        $this->assertSame([], libxml_get_errors());

        return $xml;
    }

    private function locs(\SimpleXMLElement $xml): array
    {
        $out = [];
        foreach ($xml->children() as $node) {
            $out[] = (string) $node->loc;
        }

        return $out;
    }

    public function test_sitemap_index_and_files_are_valid_and_absolute(): void
    {
        $index = $this->xml('/sitemap.xml');
        $this->assertSame('sitemapindex', $index->getName());
        $this->assertSame([self::BASE.'/sitemap-pages.xml', self::BASE.'/sitemap-products-1.xml'], $this->locs($index));

        $pages = $this->locs($this->xml('/sitemap-pages.xml'));
        $this->assertSame([
            self::BASE.'/', self::BASE.'/products', self::BASE.'/bulk-order', self::BASE.'/request',
            self::BASE.'/products?category=fashion', self::BASE.'/products?category=home-decor', self::BASE.'/products?category=jute', self::BASE.'/products?category=lifestyle',
            self::BASE.'/products?category=fashion&subcategory=t-shirts', self::BASE.'/products?category=home-decor&subcategory=cushions',
        ], $pages);
        $this->assertStringContainsString('<loc>'.self::BASE.'/products?category=fashion&amp;subcategory=t-shirts</loc>', $this->get('/sitemap-pages.xml')->getContent());

        $products = $this->locs($this->xml('/sitemap-products-1.xml'));
        $this->assertSame(array_map(fn ($s) => self::BASE.'/products/'.$s, ['essential-heavyweight-tee', 'nakshi-kantha-cushion', 'handwoven-jute-tote', 'leather-journal', 'jute-floor-mat']), $products);
        $this->get('/sitemap-products-2.xml')->assertNotFound();
    }

    public function test_sitemap_excludes_unlisted_products_empty_categories_and_private_urls(): void
    {
        DB::table('products')->where('id', 2)->update(['status' => 'draft']);
        DB::table('products')->where('id', 3)->update(['visibility' => 'hidden']);
        DB::table('categories')->insert(['name' => 'Empty', 'slug' => 'empty-cat', 'status' => 'active']);
        DB::table('categories')->where('slug', 'lifestyle')->update(['status' => 'inactive']);   // hides product 4 too

        $pages = $this->locs($this->xml('/sitemap-pages.xml'));
        $products = $this->locs($this->xml('/sitemap-products-1.xml'));
        $all = implode("\n", array_merge($pages, $products));

        $this->assertSame([self::BASE.'/products/essential-heavyweight-tee', self::BASE.'/products/jute-floor-mat'], $products);
        foreach (['nakshi-kantha-cushion', 'handwoven-jute-tote', 'leather-journal', 'empty-cat', 'lifestyle', 'home-decor', '/account', '/checkout', 'search=', 'sort=', 'preorder', '/auth'] as $excluded) {
            $this->assertStringNotContainsString($excluded, $all);
        }
        $this->assertSame(array_values(array_unique(array_merge($pages, $products))), array_merge($pages, $products));
    }

    public function test_sitemap_is_cached_and_uses_a_fixed_number_of_queries(): void
    {
        DB::enableQueryLog();
        $this->get('/sitemap-pages.xml')->assertOk();
        $this->get('/sitemap-products-1.xml')->assertOk();
        $this->get('/sitemap.xml')->assertOk();
        $first = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->get('/sitemap-pages.xml')->assertOk();
        $this->get('/sitemap-products-1.xml')->assertOk();
        $this->get('/sitemap.xml')->assertOk();

        $this->assertLessThanOrEqual(6, $first);
        $this->assertSame(0, count(DB::getQueryLog()));
        Cache::store('file')->flush();
    }
}
