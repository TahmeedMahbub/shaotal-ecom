<?php

namespace Tests\Feature;

use App\Http\Controllers\GoogleAuthController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/** Part 6: strict script CSP, no inline JavaScript, pinned + SRI CDN assets, open-redirect and link-XSS fixes. */
class SecurityHardeningTest extends TestCase
{
    use DatabaseTransactions;
    use StorefrontFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
    }

    private function csp(string $uri): string
    {
        return (string) $this->get($uri)->headers->get('Content-Security-Policy');
    }

    private function directive(string $csp, string $name): string
    {
        preg_match('/(?:^|; )'.preg_quote($name, '/').' ([^;]*)/', $csp, $m);

        return $m[1] ?? '';
    }

    public function test_script_csp_has_no_unsafe_inline_and_blocks_inline_handlers(): void
    {
        foreach (['/', '/products', '/products/nakshi-kantha-cushion', '/account', '/request', '/contact', '/nonexistent-page'] as $uri) {
            $csp = $this->csp($uri);
            $this->assertSame("'self'", $this->directive($csp, 'script-src'), $uri);
            $this->assertSame("'none'", $this->directive($csp, 'script-src-attr'), $uri);
            $this->assertStringNotContainsString('code.jquery.com', $csp, $uri);
            $this->assertStringNotContainsString("'unsafe-eval'", $csp, $uri);
        }
    }

    public function test_bulk_order_page_alone_may_load_the_two_pinned_cdn_files_with_sri(): void
    {
        $res = $this->get('/bulk-order')->assertOk();
        $script = $this->directive((string) $res->headers->get('Content-Security-Policy'), 'script-src');
        $this->assertSame("'self' https://code.jquery.com/jquery-3.7.1.min.js https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js", $script);

        $html = $res->getContent();
        // Hashes computed from the downloaded files (jQuery's matches its official published hash; Select2 identical on jsDelivr and unpkg).
        $this->assertStringContainsString('src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" crossorigin="anonymous"', $html);
        $this->assertStringContainsString('select2.min.js" integrity="sha384-d3UHjPdzJkZuk5H3qKYMLRyWLAQBJbby2yr2Q58hXXtAGF8RSNO9jpLDlKKPv5v3" crossorigin="anonymous"', $html);
        $this->assertStringContainsString('select2.min.css" integrity="sha384-OXVF05DQEe311p6ohU11NwlnX08FzMCsyoXzGOaL+83dKAb3qS17yZJxESl8YrJQ" crossorigin="anonymous"', $html);
    }

    public function test_rendered_pages_contain_no_inline_script_or_event_handlers(): void
    {
        $id = $this->makeCustomer('csp-pages@example.test');
        $this->makeAddress($id);
        $s = $this->signedInSession($id);
        foreach (['/', '/products', '/products/essential-heavyweight-tee', '/bulk-order', '/request', '/contact', '/shipping', '/returns', '/terms', '/privacy'] as $uri) {
            $this->assertNoInlineJs($this->get($uri)->getContent(), $uri);
        }
        DB::table('orders')->insert(['order_number' => 'INV-CSP001', 'customer_id' => $id, 'shipping_recipient_name' => 'C', 'shipping_phone' => '0400000000',
            'shipping_street_address' => '1 St', 'grand_total' => 10, 'payment_method' => 'cod', 'placed_at' => now()]);
        foreach (['/account', '/checkout', '/account/orders/INV-CSP001'] as $uri) {
            $this->assertNoInlineJs($this->withSession($s)->get($uri)->assertOk()->getContent(), $uri);
        }
        $this->assertNoInlineJs($this->get('/nonexistent-page')->getContent(), '404');
    }

    private function assertNoInlineJs(string $html, string $where): void
    {
        $this->assertDoesNotMatchRegularExpression('/<[a-z][^>]*\son[a-z]+\s*=/i', $html, "inline handler on {$where}");
        $this->assertDoesNotMatchRegularExpression('/href\s*=\s*["\']\s*javascript:/i', $html, "javascript: URL on {$where}");
        preg_match_all('/<script\b([^>]*)>(.*?)<\/script>/is', $html, $m, PREG_SET_ORDER);
        foreach ($m as [$all, $attrs, $body]) {
            if (str_contains($attrs, 'src=')) {
                $this->assertSame('', trim($body), "script with src and body on {$where}");

                continue;
            }
            $this->assertMatchesRegularExpression('#type="application/(ld\+)?json"#', $attrs, "executable inline <script> on {$where}");
        }
    }

    public function test_return_paths_cannot_leave_the_site(): void
    {
        foreach (['/\\evil.example', "/\t/evil.example", "/\n/evil.example", '//evil.example', 'https://evil.example', 'evil.example', '', null] as $bad) {
            $this->assertSame('/', GoogleAuthController::safeReturnPath($bad), json_encode($bad));
        }
        $this->assertSame('/products?category=jute#top', GoogleAuthController::safeReturnPath('/products?category=jute#top'));
    }

    public function test_product_links_in_requests_must_be_http_urls(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,<b>x</b>', 'vbscript:x'] as $link) {
            $this->postJson('/request', ['customerName' => 'A', 'contactInfo' => 'link@example.test', 'postcode' => '3000', 'itemName' => 'Lamp', 'qty' => 1, 'productLink' => $link])
                ->assertStatus(422)->assertJsonValidationErrors('productLink');
            $this->postJson('/bulk-order', ['customerName' => 'A', 'contactInfo' => 'link@example.test', 'postcode' => '3000', 'items' => [['name' => 'X', 'productLink' => $link, 'variants' => [['qty' => 1]]]]])
                ->assertStatus(422)->assertJsonValidationErrors('items.0.productLink');
        }
        $this->postJson('/request', ['customerName' => 'A', 'contactInfo' => 'link@example.test', 'postcode' => '3000', 'itemName' => 'Lamp', 'qty' => 1, 'productLink' => 'https://shop.example/lamp'])
            ->assertCreated();
    }

    public function test_error_page_go_back_uses_an_external_script(): void
    {
        $html = view('errors.419')->render();
        $this->assertStringContainsString('data-action="history-back"', $html);
        $this->assertStringContainsString('/assets/js/error-page.js', $html);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+=/i', $html);
    }
}
