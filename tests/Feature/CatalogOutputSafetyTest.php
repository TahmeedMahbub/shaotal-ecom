<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/**
 * Task 8: hostile admin-entered catalog data cannot break out of the server-rendered HTML. (Client-side sinks escape
 * with escHtml()/escapeHtml(); those are verified in a real browser, see the plan.)
 */
class CatalogOutputSafetyTest extends TestCase
{
    use DatabaseTransactions, StorefrontFixtures;

    private const NAME = '"><script>alert(1)</script><img src=x onerror=alert(2)>';

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
        DB::table('products')->insert([
            'id' => 9001, 'category_id' => 1, 'name' => self::NAME, 'slug' => 'xss-test-product', 'sku' => 'XSS-9001',
            'short_description' => '<b>Short</b><script>alert(3)</script>',
            'description' => '<p>Real <strong>text</strong></p><img src=x onerror=alert(4)><a href="javascript:alert(5)">bad</a><iframe src="https://evil.example"></iframe>',
            'price' => 10, 'selling_price' => 10, 'has_variants' => 0, 'stock_qty' => 5, 'availability' => 'in_stock',
            'status' => 'active', 'visibility' => 'visible',
        ]);
    }

    public function test_product_page_never_contains_executable_payloads(): void
    {
        $html = $this->get('/products/xss-test-product')->assertOk()->getContent();

        // Name in <title>/meta is escaped exactly once.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(2)>', $html);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('&amp;lt;script', $html);

        // Boot JSON cannot close the <script type="application/json"> block, and descriptions arrive sanitised.
        preg_match('#<script type="application/json" id="boot-data">(.*?)</script>#s', $html, $m);
        $this->assertNotEmpty($m, 'boot data block missing');
        $this->assertStringNotContainsString('</', $m[1]);
        $boot = json_decode($m[1], true);
        $p = collect($boot['products'])->firstWhere('slug', 'xss-test-product');
        $this->assertSame(self::NAME, $p['name']);   // data intact; escaping happens at each output
        $this->assertSame('<p>Real <strong>text</strong></p><a>bad</a>', $p['fullDesc']);
        $this->assertSame('<b>Short</b>', $p['desc']);
    }

    public function test_listing_meta_description_is_plain_text(): void
    {
        DB::table('categories')->where('id', 1)->update(['description' => '<p>Cat <script>alert(6)</script></p>']);

        $html = $this->get('/products?category='.DB::table('categories')->where('id', 1)->value('slug'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(6)', $html);
    }
}
