<?php

namespace Tests\Feature;

use App\Models\Checkout;
use App\Support\DisplayTime;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/** Part 6: confirmed business decisions (shipping, delivery wording, Sydney time) and the information pages. */
class BusinessPolicyTest extends TestCase
{
    use DatabaseTransactions;
    use StorefrontFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
    }

    /* ── Shipping: flat fee below A$299, free at or above ── */

    public function test_free_shipping_starts_at_exactly_the_threshold(): void
    {
        $line = fn ($price) => [['productId' => 2, 'categoryId' => 2, 'price' => $price, 'quantity' => 1]];
        $this->assertSame(12.0, Checkout::totals($line(298.99), null, null)['shipping']);
        $this->assertSame(0.0, Checkout::totals($line(299.00), null, null)['shipping']);
        $this->assertSame(0.0, Checkout::totals($line(299.01), null, null)['shipping']);
        $this->assertSame(310.99, Checkout::totals($line(298.99), null, null)['total']);
    }

    public function test_threshold_applies_before_discount_as_the_shipping_page_says(): void
    {
        $this->makeCoupon('HALF', ['discount_value' => 50]);
        $t = Checkout::totals([['productId' => 2, 'categoryId' => 2, 'price' => 300.00, 'quantity' => 1]], 'HALF', null);
        $this->assertSame(0.0, $t['shipping']);   // 300 >= 299 before the coupon; 150 after it is still free
        $this->assertSame(150.0, $t['total']);
    }

    /* ── Delivery wording: no express, NZ, carrier, tracking or returns promises ── */

    public function test_no_unsupported_delivery_or_returns_claims_on_any_page(): void
    {
        $id = $this->makeCustomer('claims@example.test');
        $this->makeAddress($id);
        $s = $this->signedInSession($id);
        $pages = [];
        foreach (['/', '/products', '/products/essential-heavyweight-tee', '/products/nakshi-kantha-cushion', '/bulk-order', '/request', '/shipping', '/returns', '/terms', '/privacy', '/contact'] as $uri) {
            $pages[$uri] = $this->get($uri)->assertOk()->getContent();
        }
        foreach (['/account', '/checkout'] as $uri) {
            $pages[$uri] = $this->withSession($s)->get($uri)->assertOk()->getContent();
        }
        foreach ($pages as $uri => $html) {
            $text = html_entity_decode(strip_tags(preg_replace('#<script\b.*?</script>#is', '', $html)));
            foreach (['/Express (Delivery|Shipping)/i', '/Australia Post/i', '/\bNZ\b|New Zealand/i', '/30-Day/i', '/Free Returns/i', '/door-to-door tracking/i', '/customs clearance/i', '/Sydney hub|West Croydon|dispatch from Sydney/i', '/Business Days/i', '/customer guarantee/i'] as $claim) {
                $this->assertDoesNotMatchRegularExpression($claim, $text, "{$claim} on {$uri}");
            }
        }
        $this->assertStringContainsString('Free shipping on orders of A$299 or more', $pages['/products/nakshi-kantha-cushion']);
        $this->assertStringNotContainsString('Estimated Delivery', $pages['/checkout']);   // no confirmed estimate
    }

    /* ── Sydney time (storage UTC) ── */

    public function test_display_time_handles_daylight_saving_both_ways(): void
    {
        config(['storefront.display_timezone' => 'Australia/Sydney']);
        // DST starts 4 Oct 2026 02:00 AEST → 03:00 AEDT
        $this->assertSame('4 Oct 2026, 1:59 am AEST', DisplayTime::dateTime('2026-10-03 15:59:00'));
        $this->assertSame('4 Oct 2026, 3:00 am AEDT', DisplayTime::dateTime('2026-10-03 16:00:00'));
        // DST ends 5 Apr 2026 03:00 AEDT → 02:00 AEST (the 2 am hour happens twice)
        $this->assertSame('5 Apr 2026, 2:59 am AEDT', DisplayTime::dateTime('2026-04-04 15:59:00'));
        $this->assertSame('5 Apr 2026, 2:00 am AEST', DisplayTime::dateTime('2026-04-04 16:00:00'));
        // Calendar date can differ from the UTC date
        $this->assertSame('2026-11-03', DisplayTime::date('2026-11-02 14:30:00'));
        $this->assertNull(DisplayTime::dateTime(null));
    }

    public function test_order_history_and_details_use_sydney_dates_without_double_conversion(): void
    {
        $id = $this->makeCustomer('sydney@example.test');
        $oid = DB::table('orders')->insertGetId([
            'order_number' => 'INV-SYD001', 'customer_id' => $id, 'shipping_recipient_name' => 'S', 'shipping_phone' => '0400000000',
            'shipping_street_address' => '1 St', 'grand_total' => 10, 'payment_method' => 'cod', 'placed_at' => '2026-11-02 14:30:00',
        ]);
        DB::table('order_timeline')->insert(['order_id' => $oid, 'new_status' => 'pending', 'created_at' => '2026-11-02 14:30:00']);
        $s = $this->signedInSession($id);

        $this->assertSame('2026-11-03', $this->withSession($s)->getJson('/account/orders')->json('orders.0.date'));
        $html = $this->withSession($s)->get('/account/orders/INV-SYD001')->getContent();
        $this->assertSame(2, substr_count($html, '3 Nov 2026, 1:30 am AEDT'));   // "Placed …" and the timeline step
        $this->assertStringNotContainsString('4 Nov 2026', $html);              // not converted twice
    }

    /* ── Information pages ── */

    public function test_information_pages_render_as_drafts_until_approved(): void
    {
        foreach (['contact', 'shipping', 'returns', 'terms', 'privacy'] as $slug) {
            $html = $this->get('/'.$slug)->assertOk()->getContent();
            $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html, $slug);
            $this->assertStringContainsString('Being finalised.', $html, $slug);
            $this->assertStringNotContainsString('rel="canonical"', $html, $slug);
        }
        $this->assertStringContainsString('reviewed by a legal adviser', $this->get('/terms')->getContent());
        $this->assertStringNotContainsString('/shipping', $this->get('/sitemap-pages.xml')->getContent());

        config(['storefront.approved_pages' => ['shipping']]);
        Cache::store('file')->flush();
        $ship = $this->get('/shipping')->getContent();
        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $ship);
        $this->assertStringNotContainsString('Being finalised.', $ship);
        $this->assertStringContainsString('/shipping</loc>', $this->get('/sitemap-pages.xml')->getContent());
    }

    public function test_shipping_page_states_the_configured_rules(): void
    {
        $html = $this->get('/shipping')->getContent();
        $this->assertStringContainsString('within Australia only', $html);
        $this->assertStringContainsString('Orders of A$299 or more: free shipping.', $html);
        $this->assertStringContainsString('a flat A$12 shipping fee', $html);
        $this->assertStringContainsString('Live courier tracking is not available', $html);
    }

    public function test_contact_details_are_shown_only_when_configured(): void
    {
        $html = $this->get('/contact')->getContent();
        $this->assertStringNotContainsString('mailto:', $html);
        $this->assertStringNotContainsString('tel:', $html);
        $this->assertStringContainsString('id="contact-form"', $html);

        config(['storefront.contact.email' => 'hello@shop.example', 'storefront.contact.phone' => '+61 2 9000 0000']);
        $html = $this->get('/contact')->getContent();
        $this->assertStringContainsString('href="mailto:hello@shop.example"', $html);
        $this->assertStringContainsString('href="tel:+61290000000"', $html);
    }

    public function test_footer_links_to_the_information_pages(): void
    {
        $html = $this->get('/request')->getContent();
        foreach (['/contact', '/shipping', '/returns', '/terms', '/privacy'] as $href) {
            $this->assertStringContainsString('<a href="'.$href.'">', $html);
        }
        $this->get('/about-us')->assertNotFound();
    }
}
