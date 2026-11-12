<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/**
 * Part 5B/5C/5D: production configuration gate, proxies/HTTPS, delivery wording from one source, display time zone,
 * and server-rendered state that removes layout shifts.
 */
class ProductionReadinessTest extends TestCase
{
    use DatabaseTransactions;
    use StorefrontFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    private function productionConfig(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.debug' => false, 'app.url' => 'https://shop.example', 'app.timezone' => 'UTC',
            'session.secure' => true, 'session.driver' => 'file', 'cache.default' => 'file', 'queue.default' => 'sync',
            'logging.default' => 'daily', 'logging.channels.daily.level' => 'warning', 'mail.default' => 'smtp',
            'storefront.display_timezone' => 'Australia/Sydney', 'storefront.trusted_proxies' => '10.0.0.1',
        ]);
    }

    /* ── 5B: deploy gate ── */

    public function test_deploy_check_fails_on_development_settings(): void
    {
        config(['app.debug' => true, 'app.url' => 'http://localhost', 'session.secure' => false, 'cache.default' => 'database']);
        $this->artisan('storefront:deploy-check')->assertFailed();
    }

    public function test_deploy_check_passes_with_production_settings(): void
    {
        $this->productionConfig();
        $this->artisan('storefront:deploy-check')->expectsOutputToContain('No blocking problems.')->assertSuccessful();
    }

    public function test_deploy_check_flags_debug_and_http_individually(): void
    {
        $this->productionConfig();
        config(['app.debug' => true]);
        $this->artisan('storefront:deploy-check')->assertFailed();
        $this->productionConfig();
        config(['app.url' => 'http://shop.example']);
        $this->artisan('storefront:deploy-check')->assertFailed();
    }

    public function test_session_cookie_is_secure_by_default_for_an_https_app_url(): void
    {
        $load = function (?string $url) {
            $old = getenv('APP_URL');
            putenv($url === null ? 'APP_URL' : "APP_URL={$url}");
            $_ENV['APP_URL'] = $_SERVER['APP_URL'] = $url;
            try {
                return (require config_path('session.php'))['secure'];
            } finally {
                putenv($old === false ? 'APP_URL' : "APP_URL={$old}");
                $_ENV['APP_URL'] = $_SERVER['APP_URL'] = $old === false ? null : $old;
            }
        };
        $this->assertTrue($load('https://shop.example'));
        $this->assertFalse($load('http://localhost:8000'));
    }

    public function test_behind_a_trusted_tls_proxy_requests_are_secure_and_get_hsts(): void
    {
        $this->productionConfig();
        config(['storefront.trusted_proxies' => '*']);
        (new AppServiceProvider($this->app))->boot();

        $res = $this->get('/request', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9'])->assertOk();
        $res->assertHeader('Strict-Transport-Security');
        $this->assertStringStartsWith('https://', URL::to('/products'));
    }

    public function test_no_hsts_without_https(): void
    {
        $this->get('/request')->assertOk()->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_database_session_time_zone_is_utc(): void
    {
        $this->assertSame('+00:00', DB::selectOne('select @@session.time_zone as tz')->tz);
    }

    /* ── 5C: delivery wording ── */

    public function test_product_page_threshold_comes_from_the_charged_configuration(): void
    {
        $html = $this->get('/products/nakshi-kantha-cushion')->assertOk()->getContent();
        $this->assertStringContainsString('Free AU Shipping over A$100', $html);
        $this->assertStringContainsString('Flat rate A$10 shipping for orders under A$100.', $html);
        $this->assertStringNotContainsString('A$299', $html);

        config(['storefront.free_shipping_over' => 150, 'storefront.shipping_flat' => 12.5]);
        $html = $this->get('/products/nakshi-kantha-cushion')->getContent();
        $this->assertStringContainsString('Free AU Shipping over A$150', $html);
        $this->assertStringContainsString('Flat rate A$12.5 shipping', $html);
    }

    public function test_delivery_copy_is_configurable_and_used_consistently(): void
    {
        config(['storefront.delivery_copy.estimate' => 'Owner-approved estimate', 'storefront.delivery_copy.checkout_badge' => 'Owner badge',
            'storefront.delivery_copy.dispatch_status' => 'Ships soon', 'storefront.delivery_copy.returns_short' => 'Owner returns']);
        $id = $this->makeCustomer('copy@example.test');
        $this->makeAddress($id);

        $checkout = $this->withSession($this->signedInSession($id))->get('/checkout')->getContent();
        $this->assertStringContainsString('Owner-approved estimate', $checkout);
        $this->assertStringContainsString('Owner badge', $checkout);
        $this->assertStringNotContainsString('Australia Post Express', $checkout);

        $product = $this->get('/products/nakshi-kantha-cushion')->getContent();
        $this->assertStringContainsString('<span>Ships soon</span>', $product);
        $this->assertStringContainsString('"dispatchStatus":"Ships soon"', $product);   // the script uses the same line
        $this->assertStringContainsString('Owner returns', $product);
    }

    /* ── Time zone: stored UTC, displayed in the configured zone ── */

    public function test_order_times_are_shown_in_the_display_time_zone(): void
    {
        $id = $this->makeCustomer('tz@example.test');
        $oid = DB::table('orders')->insertGetId([
            'order_number' => 'INV-TZ0001', 'customer_id' => $id, 'shipping_recipient_name' => 'T', 'shipping_phone' => '0400000000',
            'shipping_street_address' => '1 St', 'grand_total' => 10, 'payment_method' => 'cod', 'placed_at' => '2026-11-02 00:00:00',
        ]);
        DB::table('order_timeline')->insert(['order_id' => $oid, 'new_status' => 'pending', 'created_at' => '2026-11-02 00:00:00']);

        $utc = $this->withSession($this->signedInSession($id))->get('/account/orders/INV-TZ0001')->getContent();
        $this->assertStringContainsString('2 Nov 2026, 12:00 am UTC', $utc);

        config(['storefront.display_timezone' => 'Australia/Sydney']);   // AEDT (+11) in November
        $syd = $this->withSession($this->signedInSession($id))->get('/account/orders/INV-TZ0001')->getContent();
        $this->assertStringContainsString('2 Nov 2026, 11:00 am', $syd);
        $this->assertStringNotContainsString('UTC', strip_tags(substr($syd, strpos($syd, 'Placed'), 200)));
    }

    /* ── 5D: server-rendered state (no layout shift) ── */

    public function test_account_page_renders_the_right_view_on_the_server(): void
    {
        $guest = $this->get('/account')->getContent();
        $this->assertStringContainsString('id="account-unauth-view" style="display:block;', $guest);
        $this->assertStringContainsString('id="account-auth-view" style="display:none;', $guest);
        $this->assertStringContainsString('Guest User', $guest);

        $id = $this->makeCustomer('ssr-account@example.test');
        $html = $this->withSession($this->signedInSession($id))->get('/account')->getContent();
        $this->assertStringContainsString('id="account-auth-view" style="display:grid;', $html);
        $this->assertStringContainsString("G'day, Test", $html);
    }

    public function test_product_options_and_rating_row_are_server_rendered(): void
    {
        $html = $this->get('/products/essential-heavyweight-tee')->getContent();
        $this->assertStringContainsString('<button class="detail-color-swatch-item active" style="background-color: #5C6B4F" data-color-hex="#5C6B4F"', $html);
        $this->assertSame(3, substr_count($html, 'class="detail-size-pill '));
        $this->assertStringContainsString('id="selected-color-name">Olive Green<', $html);
        $this->assertMatchesRegularExpression('/<div class="detail-rating-wrap"\s+style="display:none"/', $html);   // no reviews

        $simple = $this->get('/products/nakshi-kantha-cushion')->getContent();
        $this->assertMatchesRegularExpression('/id="detail-color-group"\s+style="display:none"/', $simple);
        $this->assertMatchesRegularExpression('/id="detail-size-group"\s+style="display:none"/', $simple);
    }

    public function test_header_navigation_is_server_rendered(): void
    {
        $html = $this->get('/request')->getContent();
        $this->assertStringContainsString('<a href="/products?category=fashion&amp;subcategory=t-shirts">T-Shirts</a>', $html);
        $this->assertStringContainsString('<a href="/products?sort=newest" class="nav-link">New Arrivals</a>', $html);
        $this->assertStringContainsString('<a href="/bulk-order" class="nav-link">Bulk Orders</a>', $html);
    }

    public function test_hero_has_a_mobile_source_and_media_specific_preloads(): void
    {
        DB::table('hero_slides')->update(['image_url' => 'https://res.cloudinary.com/demo/image/upload/sample.jpg', 'mobile_image_url' => null]);
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('href="https://res.cloudinary.com/demo/image/upload/f_auto,q_auto,c_limit,w_1280/sample.jpg" media="(min-width: 769px)"', $html);
        $this->assertStringContainsString('href="https://res.cloudinary.com/demo/image/upload/f_auto,q_auto,c_limit,w_768/sample.jpg" media="(max-width: 768px)"', $html);
        $this->assertStringContainsString("--hero-bg-m: url('https://res.cloudinary.com/demo/image/upload/f_auto,q_auto,c_limit,w_768/sample.jpg')", $html);
    }
}
