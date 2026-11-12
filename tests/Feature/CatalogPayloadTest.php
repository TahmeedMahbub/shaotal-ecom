<?php

namespace Tests\Feature;

use App\Models\Storefront;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/**
 * Part 5: page-scoped catalog payload, the public catalog endpoints, query budgets, cache privacy and catalog cache
 * invalidation. Seed: products 1–5 (sql/local_test_seed.sql); product 5 is admin-flagged out_of_stock.
 */
class CatalogPayloadTest extends TestCase
{
    use DatabaseTransactions;
    use StorefrontFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
    }

    private function boot(string $html): array
    {
        $this->assertMatchesRegularExpression('#<script type="application/json" id="boot-data">(.*?)</script>#s', $html);
        preg_match('#<script type="application/json" id="boot-data">(.*?)</script>#s', $html, $m);

        return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
    }

    private function ids(array $boot): array
    {
        $ids = array_column($boot['products'], 'id');
        sort($ids);

        return $ids;
    }

    /** Extra listed products so "embed everything" and "embed one page" differ. */
    private function addProducts(int $n, string $category = 'fashion'): array
    {
        $catId = DB::table('categories')->where('slug', $category)->value('id');
        $ids = [];
        for ($i = 0; $i < $n; $i++) {
            $ids[] = DB::table('products')->insertGetId([
                'category_id' => $catId, 'name' => "Extra Product {$i}", 'slug' => "extra-product-{$i}", 'sku' => "EXTRA-{$i}",
                'short_description' => 'Extra', 'price' => 20 + $i, 'has_variants' => 0, 'stock_qty' => 0, 'availability' => 'in_stock',
                'visibility' => 'visible', 'status' => 'active',
            ]);
        }

        return $ids;
    }

    /* ── Page-scoped payload ── */

    public function test_pages_embed_only_the_products_they_render(): void
    {
        $this->addProducts(20);

        $this->assertSame([], $this->boot($this->get('/request')->getContent())['products']);
        $this->assertSame([], $this->boot($this->get('/account')->getContent())['products']);

        $listing = $this->boot($this->get('/products')->getContent());
        $this->assertCount(8, $listing['products']);   // one page, not 25

        $product = $this->boot($this->get('/products/nakshi-kantha-cushion')->getContent());
        $this->assertLessThanOrEqual(5, count($product['products']));   // itself + up to 4 related
        $this->assertContains(2, array_column($product['products'], 'id'));
        $withText = array_filter($product['products'], fn ($p) => array_key_exists('fullDesc', $p));
        $this->assertSame([2], array_column($withText, 'id'));   // long text only for the product being viewed

        $this->assertCount(25, $this->boot($this->get('/bulk-order')->getContent())['products']);   // picker needs all
    }

    public function test_home_embeds_category_sections_and_preorders_and_renders_popular_server_side(): void
    {
        $this->addProducts(20, 'fashion');
        $html = $this->get('/')->getContent();
        $boot = $this->boot($html);
        $byCat = array_count_values(array_column($boot['products'], 'category'));

        $this->assertSame(Storefront::HOME_PER_CATEGORY, $byCat['fashion']);   // capped (21 exist)
        $this->assertContains(4, array_column($boot['products'], 'id'));         // pre-order preview
        preg_match('/data-popular-ids="([\d,]+)"/', $html, $m);
        $popular = array_map('intval', explode(',', $m[1]));
        $this->assertCount(5, $popular);
        $this->assertSame([], array_diff($popular, array_column($boot['products'], 'id')));
        preg_match('#id="product-grid"(.*?)id="categories-section"#s', $html, $grid);
        preg_match_all('/data-product-id="(\d+)"/', $grid[1], $cards);
        $this->assertSame($popular, array_map('intval', $cards[1]));   // the five cards are in the HTML
    }

    public function test_signed_in_customer_cart_and_wishlist_products_are_embedded_everywhere(): void
    {
        $id = $this->makeCustomer('payload@example.test');
        $cart = DB::table('carts')->insertGetId(['customer_id' => $id, 'status' => 'active']);
        DB::table('cart_items')->insert(['cart_id' => $cart, 'product_id' => 3, 'quantity' => 1]);
        DB::table('wishlist_items')->insert(['customer_id' => $id, 'product_id' => 4]);

        $boot = $this->boot($this->withSession($this->signedInSession($id))->get('/request')->getContent());
        $this->assertSame([3, 4], $this->ids($boot));
        $this->assertSame(3, $boot['cart'][0]['productId']);
    }

    public function test_sign_in_response_carries_the_products_of_the_saved_cart_and_wishlist(): void
    {
        $id = $this->makeCustomer('signin-payload@example.test');
        DB::table('wishlist_items')->insert(['customer_id' => $id, 'product_id' => 2]);

        $res = $this->postJson('/auth/login', ['email' => 'signin-payload@example.test', 'password' => 'Password123'])->assertOk();
        $this->assertSame([2], array_column($res->json('products'), 'id'));
    }

    /* ── Public catalog endpoints ── */

    public function test_catalog_products_returns_only_listed_products_and_caps_ids(): void
    {
        DB::table('products')->where('id', 3)->update(['visibility' => 'hidden']);
        $res = $this->get('/catalog/products?ids=1,2,3,999999,abc,-4')->assertOk();

        $this->assertSame([2, 1], array_column($res->json('products'), 'id'));   // catalog order (newest first)
        $this->assertArrayNotHasKey('fullDesc', $res->json('products.0'));
        $this->assertSame([], $this->get('/catalog/products')->json('products'));
        $many = implode(',', range(1, 500));
        $this->assertLessThanOrEqual(60, count($this->get('/catalog/products?ids='.$many)->json('products')));
    }

    public function test_catalog_feed_filters_sorts_and_pages_like_the_listing_script(): void
    {
        $this->addProducts(10, 'jute');   // jute now has 12 listed products

        $p1 = $this->getJson('/catalog/feed?category=jute&perPage=8')->assertOk();
        $this->assertSame(12, $p1->json('total'));
        $this->assertCount(8, $p1->json('products'));
        $this->assertTrue($p1->json('hasMore'));
        $p2 = $this->getJson('/catalog/feed?category=jute&perPage=8&page=2');
        $this->assertCount(4, $p2->json('products'));
        $this->assertFalse($p2->json('hasMore'));
        $this->assertSame([], array_intersect(array_column($p1->json('products'), 'id'), array_column($p2->json('products'), 'id')));

        $this->assertSame([1], array_column($this->getJson('/catalog/feed?category=fashion&subcategory=t-shirts')->json('products'), 'id'));
        $this->assertSame([3], array_column($this->getJson('/catalog/feed?brands[]=Sonali%20Jute')->json('products'), 'id'));
        // Heritage Weaves (1, 2, 4) + Sonali Jute (3), highest price first: 79, 55, 45, 26
        $this->assertSame([4, 2, 3, 1], array_column($this->getJson('/catalog/feed?brands[]=Heritage%20Weaves&brands[]=Sonali%20Jute&sort=price-high')->json('products'), 'id'));
        $this->assertSame([3], array_column($this->getJson('/catalog/feed?brand=Sonali%20Jute')->json('products'), 'id'));   // ?brand= link form
        $cheap = $this->getJson('/catalog/feed?prices[]=under-30&perPage=48')->json('products');
        $this->assertNotEmpty($cheap);
        $this->assertTrue(collect($cheap)->every(fn ($p) => $p['price'] < 30));
        $this->assertSame([4], array_column($this->getJson('/catalog/feed?availability[]=preorder')->json('products'), 'id'));
        $this->assertSame([5], array_column($this->getJson('/catalog/feed?availability[]=soldout')->json('products'), 'id'));
        $this->assertSame([1], array_column($this->getJson('/catalog/feed?sizes[]=L&colors[]=%235C6B4F')->json('products'), 'id'));
        $low = array_column($this->getJson('/catalog/feed?sort=price-low&perPage=48')->json('products'), 'price');
        $sorted = $low;
        sort($sorted);
        $this->assertSame($sorted, $low);
        $this->assertLessThanOrEqual(48, count($this->getJson('/catalog/feed?perPage=500')->json('products')));
    }

    public function test_catalog_search_matches_name_category_brand_and_description(): void
    {
        $this->assertContains(3, array_column($this->getJson('/catalog/search?q=tote')->json('products'), 'id'));           // name
        $this->assertContains(2, array_column($this->getJson('/catalog/search?q=home%20%26%20living')->json('products'), 'id'));   // category name
        $this->assertContains(3, array_column($this->getJson('/catalog/search?q=sonali')->json('products'), 'id'));         // brand
        $res = $this->getJson('/catalog/search?q=')->json();
        $this->assertSame(5, $res['total']);   // empty query: newest products as suggestions
        $this->assertSame(0, $this->getJson('/catalog/search?q=zzzz-no-match')->json('total'));
        $this->assertLessThanOrEqual(20, count($this->getJson('/catalog/search?q=e&limit=1000')->json('products')));
    }

    public function test_catalog_browsing_does_not_use_up_the_sign_in_rate_limit(): void
    {
        $this->makeCustomer('throttle@example.test');
        for ($i = 0; $i < 40; $i++) {
            $this->getJson('/catalog/feed?page='.(($i % 3) + 1))->assertOk();
        }
        for ($i = 0; $i < 15; $i++) {
            $this->getJson('/auth/csrf')->assertOk();
        }
        $this->postJson('/auth/login', ['email' => 'throttle@example.test', 'password' => 'Password123'])->assertOk();

        // The sign-in route still has its own limit (10 per minute per IP).
        $statuses = [];
        for ($i = 0; $i < 11; $i++) {
            $statuses[] = $this->postJson('/auth/login', ['email' => 'nobody'.$i.'@example.test', 'password' => 'x'])->status();
        }
        $this->assertContains(429, $statuses);
    }

    /* ── Cache headers: public data is cookie-free, private pages are never publicly cacheable ── */

    public function test_public_endpoints_send_no_cookies_and_are_publicly_cacheable(): void
    {
        foreach (['/robots.txt', '/sitemap.xml', '/sitemap-pages.xml', '/sitemap-products-1.xml', '/catalog/products?ids=1', '/catalog/search?q=jute', '/catalog/feed'] as $uri) {
            $res = $this->get($uri)->assertOk();
            $this->assertSame([], $res->headers->getCookies(), $uri);
            $this->assertStringContainsString('public', (string) $res->headers->get('Cache-Control'), $uri);
        }
    }

    public function test_customer_pages_and_data_are_never_publicly_cacheable(): void
    {
        $id = $this->makeCustomer('private-cache@example.test');
        $this->makeAddress($id);
        $s = $this->signedInSession($id);
        foreach (['/account', '/checkout', '/account/orders', '/account/addresses'] as $uri) {
            $cc = (string) $this->withSession($s)->get($uri)->assertOk()->headers->get('Cache-Control');
            $this->assertStringContainsString('private', $cc, $uri);
            $this->assertStringNotContainsString('public', $cc, $uri);
        }
        // Pages that show the signed-in customer (header, cart) are private too.
        $cc = (string) $this->withSession($s)->get('/products')->headers->get('Cache-Control');
        $this->assertStringNotContainsString('public', $cc);
    }

    public function test_checkout_embeds_only_the_customers_own_addresses(): void
    {
        $a = $this->makeCustomer('addr-a@example.test');
        $b = $this->makeCustomer('addr-b@example.test');
        $this->makeAddress($a, ['street_address' => '1 Mine St']);
        $this->makeAddress($b, ['street_address' => '9 Other St']);

        $html = $this->withSession($this->signedInSession($a))->get('/checkout')->assertOk()->getContent();
        $this->assertStringContainsString('1 Mine St', $html);
        $this->assertStringNotContainsString('9 Other St', $html);
    }

    /* ── Query budgets (warm catalog cache) ── */

    private function queries(string $uri, ?array $session = null): int
    {
        $this->get('/request');   // warm the catalog cache
        DB::flushQueryLog();
        DB::enableQueryLog();
        ($session ? $this->withSession($session) : $this)->get($uri)->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    public function test_query_budgets_with_a_warm_cache(): void
    {
        $this->assertSame(0, $this->queries('/'));
        $this->assertSame(0, $this->queries('/products?category=fashion'));
        $this->assertSame(0, $this->queries('/catalog/feed?category=jute'));
        $this->assertSame(0, $this->queries('/catalog/products?ids=1,2'));
        $this->assertLessThanOrEqual(9, $this->queries('/products/essential-heavyweight-tee'));   // live product read (fixed count)
        $id = $this->makeCustomer('budget@example.test');
        $this->assertLessThanOrEqual(6, $this->queries('/account', $this->signedInSession($id)));
    }

    public function test_product_page_query_count_does_not_grow_with_the_catalog(): void
    {
        $before = $this->queries('/products/essential-heavyweight-tee');
        $this->addProducts(30);
        Cache::store('file')->flush();
        $this->assertSame($before, $this->queries('/products/essential-heavyweight-tee'));
    }

    /* ── Catalog cache invalidation ── */

    public function test_catalog_cache_refresh_command_and_admin_version_bump(): void
    {
        $name = fn () => collect($this->get('/catalog/products?ids=2')->json('products'))->first()['name'];
        $this->assertSame('Nakshi Kantha Cushion', $name());

        DB::table('products')->where('id', 2)->update(['name' => 'Renamed Cushion']);
        $this->assertSame('Nakshi Kantha Cushion', $name());   // cached

        $this->artisan('storefront:refresh-catalog')->assertSuccessful();
        $this->assertSame('Renamed Cushion', $name());

        // Admin-side hook: bumping site_settings.catalog_version invalidates within the 10 s version window.
        DB::table('products')->where('id', 2)->update(['name' => 'Admin Renamed']);
        DB::table('site_settings')->updateOrInsert(['setting_key' => 'catalog_version'], ['setting_value' => json_encode('v2')]);
        $this->assertSame('Renamed Cushion', $name());
        $this->travel(11)->seconds();
        $this->assertSame('Admin Renamed', $name());
    }

    /* ── Images ── */

    public function test_no_page_requests_null_or_empty_images(): void
    {
        DB::table('product_images')->where('product_id', 2)->delete();
        Cache::store('file')->flush();
        foreach (['/', '/products', '/products?category=home-decor', '/products/nakshi-kantha-cushion', '/bulk-order', '/request'] as $uri) {
            $html = $this->get($uri)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/\s(src|href|srcset)="(\/?null|)"/', $html, $uri);
            $this->assertDoesNotMatchRegularExpression('/url\(\'(null)?\'\)/', $html, $uri);
        }
    }
}
