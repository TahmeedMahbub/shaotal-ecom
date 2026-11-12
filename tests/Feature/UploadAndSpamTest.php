<?php

namespace Tests\Feature;

use App\Support\SpamGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/** Part 6: sourcing-request uploads (content-checked, re-encoded) and spam protection on public forms. */
class UploadAndSpamTest extends TestCase
{
    use DatabaseTransactions;
    use StorefrontFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
        Storage::fake('public');
    }

    private function sourcing(array $extra = [], string $contact = 'upload@example.test'): TestResponse
    {
        return $this->post('/request', $extra + [
            'customerName' => 'Uploader', 'contactInfo' => $contact, 'postcode' => '3000', 'itemName' => 'Woven lamp', 'qty' => 2,
        ], ['Accept' => 'application/json']);
    }

    private function jpeg(int $w = 200, int $h = 150): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 120, 60));
        ob_start();
        imagejpeg($img, null, 90);

        return (string) ob_get_clean();
    }

    public function test_valid_image_is_re_encoded_and_stored_under_a_random_name(): void
    {
        // A real JPEG with a PHP payload appended (polyglot): accepted as an image, payload removed by re-encoding.
        $file = UploadedFile::fake()->createWithContent('../../evil name.php.jpg', $this->jpeg()."\n<?php system(\$_GET['c']); ?>");
        $this->sourcing(['images' => [$file]])->assertCreated();

        $url = DB::table('sourcing_request_images')->value('image_url');
        $this->assertMatchesRegularExpression('#/sourcing-requests/[0-9a-f-]{36}\.jpg$#', $url);
        $stored = Storage::disk('public')->files('sourcing-requests');
        $this->assertCount(1, $stored);
        $bytes = Storage::disk('public')->get($stored[0]);
        $this->assertStringNotContainsString('<?php', $bytes);
        $this->assertSame('image/jpeg', getimagesizefromstring($bytes)['mime']);
    }

    public function test_non_images_and_svg_are_rejected_whatever_their_extension(): void
    {
        $bad = [
            UploadedFile::fake()->createWithContent('shell.jpg', "<?php system(\$_GET['c']); ?>"),
            UploadedFile::fake()->createWithContent('page.png', '<html><script>alert(1)</script></html>'),
            UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->createWithContent('anim.gif', "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;"),
        ];
        foreach ($bad as $f) {
            $this->sourcing(['images' => [$f]])->assertStatus(422)->assertJsonValidationErrors('images.0');
        }
        $this->assertSame(0, DB::table('sourcing_requests')->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_oversized_huge_and_too_many_images_are_rejected(): void
    {
        $this->sourcing(['images' => [UploadedFile::fake()->image('big.jpg', 300, 300)->size(6000)]])->assertStatus(422)->assertJsonValidationErrors('images.0');
        $this->sourcing(['images' => [UploadedFile::fake()->image('wide.png', 7000, 10)]])->assertStatus(422)->assertJsonValidationErrors('images.0');
        $six = array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg", 100, 100), range(1, 6));
        $this->sourcing(['images' => $six])->assertStatus(422)->assertJsonValidationErrors('images');
        $this->assertSame(0, DB::table('sourcing_requests')->count());
    }

    public function test_combined_upload_size_is_capped(): void
    {
        $four = array_map(fn ($i) => UploadedFile::fake()->image("q{$i}.jpg", 100, 100)->size(4500), range(1, 4));   // 18 MB
        $this->sourcing(['images' => $four])->assertStatus(422)->assertJsonValidationErrors('images');
    }

    public function test_honeypot_link_spam_and_per_contact_limit(): void
    {
        $this->sourcing([SpamGuard::HONEYPOT => 'http://spam.example'])->assertStatus(422);
        $this->sourcing(['details' => 'buy http://a.example http://b.example http://c.example www.d.example'])->assertStatus(422);
        $this->assertSame(0, DB::table('sourcing_requests')->count());

        for ($i = 0; $i < SpamGuard::MAX_PER_CONTACT; $i++) {
            $this->sourcing([], 'same@example.test')->assertCreated();
        }
        $this->sourcing([], 'Same@Example.test')->assertStatus(429);   // case-insensitive, from any IP
        $this->sourcing([], 'other@example.test')->assertCreated();
    }

    public function test_bulk_order_has_the_same_spam_guard(): void
    {
        $body = ['customerName' => 'B', 'contactInfo' => 'bulk-spam@example.test', 'postcode' => '2000', 'items' => [['name' => 'X', 'variants' => [['qty' => 5]]]]];
        $this->postJson('/bulk-order', $body + [SpamGuard::HONEYPOT => 'x'])->assertStatus(422);
        $this->postJson('/bulk-order', $body)->assertCreated();
    }

    public function test_contact_form_validates_stores_and_guards(): void
    {
        $ok = ['name' => 'Mia', 'email' => 'Mia@Example.test', 'subject' => 'Delivery', 'orderNumber' => 'INV-123456', 'message' => 'Where is my parcel please?'];
        $this->postJson('/contact', $ok)->assertCreated();
        $row = DB::table('contact_messages')->first();
        $this->assertSame('mia@example.test', $row->email);
        $this->assertSame('Delivery · Order INV-123456', $row->subject);
        $this->assertSame('new', $row->status);

        $this->postJson('/contact', ['name' => '', 'email' => 'not-an-email', 'message' => 'short'])->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'message']);
        $this->postJson('/contact', array_merge($ok, ['orderNumber' => '<script>']))->assertStatus(422);
        $this->postJson('/contact', array_merge($ok, [SpamGuard::HONEYPOT => 'filled']))->assertStatus(422);
        $this->assertSame(1, DB::table('contact_messages')->count());
    }

    public function test_contact_route_is_rate_limited_per_ip(): void
    {
        $statuses = [];
        for ($i = 0; $i < 7; $i++) {
            $statuses[] = $this->postJson('/contact', ['name' => 'R', 'email' => "r{$i}@example.test", 'message' => 'Hello there, a question.'])->status();
        }
        $this->assertContains(429, $statuses);
    }
}
