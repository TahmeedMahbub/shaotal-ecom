<?php

namespace Tests\Unit;

use App\Support\SafeHtml;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SafeHtmlTest extends TestCase
{
    public static function payloads(): array
    {
        return [
            'script tag' => ['<p>Hi</p><script>alert(1)</script>'],
            'img onerror' => ['<img src=x onerror=alert(1)>'],
            'svg onload' => ['<svg onload=alert(1)><circle/></svg>'],
            'javascript link' => ['<a href="javascript:alert(1)">x</a>'],
            'obfuscated javascript link' => ['<a href=" JaVa&#x0A;sCriPt:alert(1)">x</a>'],
            'data url link' => ['<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">x</a>'],
            'event handler on allowed tag' => ['<p onclick="alert(1)" onmouseover=alert(1)>x</p>'],
            'iframe' => ['<iframe src="https://evil.example"></iframe>'],
            'style tag + attribute' => ['<style>body{display:none}</style><p style="background:url(javascript:alert(1))">x</p>'],
            'nested unknown tag' => ['<custom-el><img src=x onerror=alert(1)></custom-el>'],
            'unclosed script' => ['<p>x<script>alert(1)'],
            'comment' => ['<!--<img src=x onerror=alert(1)>--><p>x</p>'],
            'protocol-relative img' => ['<img src="//evil.example/x.png">'],
            'form' => ['<form action="https://evil.example"><input name=p></form>'],
            'object/embed' => ['<object data="x.swf"></object><embed src="x.swf">'],
            'math' => ['<math><mtext><img src=x onerror=alert(1)></mtext></math>'],
        ];
    }

    #[DataProvider('payloads')]
    public function test_xss_payloads_are_neutralised(string $payload): void
    {
        $out = SafeHtml::clean($payload);

        foreach (['<script', 'onerror', 'onload', 'onclick', 'onmouseover', 'javascript:', 'data:text', '<iframe', '<style', 'style=', '<svg', '<form', '<object', '<embed', '<math', '//evil.example'] as $bad) {
            $this->assertStringNotContainsStringIgnoringCase($bad, $out, "Payload produced: {$out}");
        }
    }

    public function test_legitimate_formatting_is_kept(): void
    {
        $html = '<h3>Craft</h3><p>Hand-woven <strong>jute</strong> &amp; <em>cotton</em>.</p><ul><li>One</li><li>Two</li></ul>'
            .'<p><a href="https://shaotal.example/care" target="_blank">Care guide</a> · <a href="/products?category=home">Home</a></p>';

        $out = SafeHtml::clean($html);

        $this->assertStringContainsString('<h3>Craft</h3>', $out);
        $this->assertStringContainsString('<strong>jute</strong> &amp; <em>cotton</em>', $out);
        $this->assertStringContainsString('<ul><li>One</li><li>Two</li></ul>', $out);
        $this->assertStringContainsString('<a href="https://shaotal.example/care" rel="noopener noreferrer nofollow">Care guide</a>', $out);
        $this->assertStringContainsString('<a href="/products?category=home" rel="noopener noreferrer nofollow">Home</a>', $out);
    }

    public function test_plain_text_is_returned_unchanged_and_unicode_survives(): void
    {
        $this->assertSame("Size < 5 & \"quoted\"\nline two", SafeHtml::clean("Size < 5 & \"quoted\"\nline two"));
        $this->assertSame('<p>Café — naïve 👍</p>', SafeHtml::clean('<p>Café — naïve 👍</p>'));
        $this->assertSame('', SafeHtml::clean(null));
    }

    public function test_output_that_lost_all_tags_is_still_marked_as_html(): void
    {
        // Only text left after stripping: wrapped so the page renders it as HTML (no double escaping of &amp;).
        $this->assertSame('<p>Fish &amp; chips</p>', SafeHtml::clean('<custom>Fish &amp; chips</custom>'));
    }

    public function test_sanitising_is_idempotent(): void
    {
        $once = SafeHtml::clean('<p onclick="x">A <b>B</b> <a href="javascript:x">C</a></p><script>x</script>');
        $this->assertSame($once, SafeHtml::clean($once));
    }
}
