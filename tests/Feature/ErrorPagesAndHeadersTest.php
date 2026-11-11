<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/** Task 10 (error pages, no leaks) and Task 7 (security headers). Runs without a database. */
class ErrorPagesAndHeadersTest extends TestCase
{
    private const SECRET = 'SQLSTATE secret-password-123 at C:\\laragon\\www\\shaotal\\app\\Models\\Checkout.php';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);   // production behaviour
        Route::get('/__test/abort/{code}', fn (int $code) => abort($code))->middleware('web');
        Route::get('/__test/boom', fn () => throw new RuntimeException(self::SECRET))->middleware('web');
    }

    public static function pages(): array
    {
        return [[403, 'Access denied'], [404, 'Page not found'], [419, 'Page expired'], [429, 'Too many requests'], [500, 'Something went wrong'], [503, 'We&#039;ll be back soon']];
    }

    #[DataProvider('pages')]
    public function test_branded_html_error_pages_keep_their_status(int $code, string $title): void
    {
        $this->get("/__test/abort/{$code}")->assertStatus($code)
            ->assertSee($title, false)->assertSee('href="/"', false)->assertSee('noindex', false)
            ->assertHeader('Content-Security-Policy');
    }

    public function test_unknown_url_gets_branded_404_with_links_back_to_shopping(): void
    {
        $this->get('/definitely-not-a-page')->assertNotFound()->assertSee('Page not found')->assertSee('href="/products"', false);
    }

    public function test_server_error_never_exposes_exception_details_in_html_or_json(): void
    {
        $html = $this->get('/__test/boom')->assertStatus(500)->assertSee('Something went wrong')->getContent();
        $this->assertStringNotContainsString('secret-password', $html);
        $this->assertStringNotContainsString('SQLSTATE', $html);
        $this->assertStringNotContainsString('laragon', $html);

        $this->getJson('/__test/boom')->assertStatus(500)->assertExactJson(['message' => 'Server Error']);
    }

    public function test_ajax_errors_are_json_not_html(): void
    {
        $this->getJson('/definitely-not-a-page')->assertNotFound()->assertJsonStructure(['message']);
        $this->getJson('/__test/abort/429')->assertStatus(429)->assertJsonStructure(['message']);
    }

    public function test_security_headers_on_normal_and_error_responses(): void
    {
        foreach (['/up', '/definitely-not-a-page'] as $url) {
            $res = $this->get($url);
            $csp = $res->headers->get('Content-Security-Policy');
            $this->assertStringContainsString("frame-ancestors 'self'", $csp);
            $this->assertStringContainsString("object-src 'none'", $csp);
            $this->assertStringContainsString("base-uri 'self'", $csp);
            $this->assertStringNotContainsString('unsafe-eval', $csp);
            $res->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy');
        }
    }

    public function test_hsts_only_on_https_in_production(): void
    {
        $this->get('/up')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/up')->assertHeaderMissing('Strict-Transport-Security');   // testing env

        $this->app['env'] = 'production';
        $this->get('http://localhost/up')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/up')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
