<?php

namespace Tests\Feature;

use App\Models\CustomerReturns;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/**
 * Part 6: return requests. The proposed tables (tests/Support/proposed-schema.sql) exist only in test databases; the
 * feature is OFF unless configured. Only decided rules are tested: own order, delivered, once, optional window.
 */
class ReturnRequestTest extends TestCase
{
    use DatabaseTransactions;
    use StorefrontFixtures;

    private int $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
        if (! Schema::hasTable('order_return_requests')) {
            $this->markTestSkipped('Apply tests/Support/proposed-schema.sql to the test database.');
        }
        $this->customer = $this->makeCustomer('returns@example.test');
        \App\Support\StoreConfig::fake(['returns_enabled' => true, 'returns_window_days' => null]);
    }

    private function order(string $status, ?string $deliveredAt = '2026-11-01 10:00:00', ?int $customer = null): string
    {
        $number = 'INV-R'.random_int(10000, 99999);
        $id = DB::table('orders')->insertGetId([
            'order_number' => $number, 'customer_id' => $customer ?? $this->customer, 'shipping_recipient_name' => 'R', 'shipping_phone' => '0400000000',
            'shipping_street_address' => '1 St', 'grand_total' => 55, 'payment_method' => 'cod', 'order_status' => $status, 'payment_status' => 'paid',
        ]);
        DB::table('order_items')->insert(['order_id' => $id, 'product_id' => 2, 'product_name' => 'Cushion', 'unit_price' => 55, 'unit_selling_price' => 55, 'quantity' => 1, 'line_total' => 55]);
        if ($status === 'delivered' && $deliveredAt) {
            DB::table('order_timeline')->insert(['order_id' => $id, 'old_status' => 'shipped', 'new_status' => 'delivered', 'created_at' => $deliveredAt]);
        }

        return $number;
    }

    private function ask(string $number, ?int $as = null, array $body = ['reason' => 'Damaged or faulty', 'details' => 'Cracked on arrival'])
    {
        return $this->withSession($this->signedInSession($as ?? $this->customer))->postJson("/account/orders/{$number}/return", $body);
    }

    public function test_customer_can_request_a_return_once_for_a_delivered_order(): void
    {
        $number = $this->order('delivered');
        $stock = DB::table('products')->where('id', 2)->value('stock_qty');

        $this->ask($number)->assertCreated();
        $req = DB::table('order_return_requests')->first();
        $this->assertSame('requested', $req->status);
        $this->assertSame('Damaged or faulty', $req->reason);
        $this->assertSame(1, DB::table('order_return_request_events')->where('return_request_id', $req->id)->where('new_status', 'requested')->count());

        // No refund, payment, order-status or stock change.
        $o = DB::table('orders')->where('order_number', $number)->first();
        $this->assertSame(['delivered', 'paid'], [$o->order_status, $o->payment_status]);
        $this->assertSame($stock, DB::table('products')->where('id', 2)->value('stock_qty'));

        $this->ask($number)->assertStatus(422)->assertJsonPath('message', 'A return has already been requested for this order.');
        $this->assertSame(1, DB::table('order_return_requests')->count());

        $html = $this->withSession($this->signedInSession($this->customer))->get("/account/orders/{$number}")->getContent();
        $this->assertStringContainsString('Return Requested', $html);
        $this->assertStringNotContainsString('id="order-return-form"', $html);
    }

    public function test_only_delivered_orders_are_eligible(): void
    {
        foreach (['pending', 'confirmed', 'shipped', 'cancelled', 'returned'] as $status) {
            $this->ask($this->order($status))->assertStatus(422);
        }
        $this->assertSame(0, DB::table('order_return_requests')->count());
    }

    public function test_other_customers_and_guests_cannot_request(): void
    {
        $number = $this->order('delivered');
        $other = $this->makeCustomer('returns-other@example.test');
        $this->ask($number, $other)->assertNotFound();
        $this->flushSession();
        $this->postJson("/account/orders/{$number}/return", ['reason' => 'Other'])->assertUnauthorized();
        $this->assertSame(0, DB::table('order_return_requests')->count());
    }

    public function test_reason_must_be_one_of_the_configured_reasons(): void
    {
        $this->ask($this->order('delivered'), null, ['reason' => '<script>x</script>'])->assertStatus(422)->assertJsonValidationErrors('reason');
    }

    public function test_configured_window_needs_a_recorded_delivery_time(): void
    {
        \App\Support\StoreConfig::fake(['returns_window_days' => 14]);
        $this->travelTo('2026-11-10 00:00:00');
        $this->ask($this->order('delivered', '2026-11-01 10:00:00'))->assertCreated();       // 9 days after delivery
        $this->ask($this->order('delivered', '2026-10-20 10:00:00'))->assertStatus(422);     // 21 days
        $this->ask($this->order('delivered', null))->assertStatus(422);                       // delivery time unknown
    }

    public function test_feature_is_off_by_default_and_hidden(): void
    {
        \App\Support\StoreConfig::fake(['returns_enabled' => false]);
        $number = $this->order('delivered');
        $this->ask($number)->assertStatus(422);
        $html = $this->withSession($this->signedInSession($this->customer))->get("/account/orders/{$number}")->getContent();
        $this->assertStringNotContainsString('id="order-return-form"', $html);
        $this->assertStringNotContainsString('send a return request', $this->get('/returns')->getContent());
    }

    public function test_admin_transition_table_is_forward_only(): void
    {
        $this->assertTrue(CustomerReturns::canTransition('requested', 'approved'));
        $this->assertTrue(CustomerReturns::canTransition('approved', 'completed'));
        $this->assertFalse(CustomerReturns::canTransition('rejected', 'approved'));
        $this->assertFalse(CustomerReturns::canTransition('completed', 'requested'));
    }
}
