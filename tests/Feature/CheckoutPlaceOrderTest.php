<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Order placement: server-side pricing, atomic order rows, and duplicate-submission protection.
 * Uses seeded product 2 (simple, A$55, in stock) and 5 (out of stock) from sql/local_test_seed.sql.
 */
class CheckoutPlaceOrderTest extends TestCase
{
    use DatabaseTransactions;

    private int $customerId;

    private int $addressId;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires the MySQL test database.');
        }
        $this->customerId = DB::table('customers')->insertGetId(['first_name' => 'Buyer', 'email' => 'buyer@example.test', 'status' => 'active', 'email_verified_at' => now()]);
        $this->addressId = DB::table('customer_addresses')->insertGetId([
            'customer_id' => $this->customerId, 'label' => 'Home', 'recipient_name' => 'Buyer', 'phone' => '0400000000',
            'street_address' => '1 Test St', 'suburb' => 'Carlton', 'state' => 'VIC', 'postcode' => '3053', 'is_default' => 1,
        ]);
        $this->withSession(['customer_id' => $this->customerId]);
    }

    private function body(int $qty = 2): array
    {
        // Client-sent prices are ignored by the server; include one to prove it.
        return ['items' => [['productId' => 2, 'quantity' => $qty, 'price' => 0.01]], 'paymentMethod' => 'cod', 'addressId' => $this->addressId];
    }

    private function orders()
    {
        return DB::table('orders')->where('customer_id', $this->customerId);
    }

    public function test_places_order_with_server_prices_and_all_related_rows(): void
    {
        $res = $this->postJson('/checkout/place', $this->body())->assertCreated();

        $order = $this->orders()->first();
        $this->assertSame($res->json('orderNumber'), $order->order_number);
        $this->assertSame('110.00', $order->subtotal);           // 2 × A$55 from the DB, not the client's 0.01
        $this->assertSame('12.00', $order->shipping_total);      // below A$299: flat A$12
        $this->assertSame('122.00', $order->grand_total);
        $this->assertSame('pending', $order->order_status);
        $this->assertSame('cod', $order->payment_method);
        $this->assertSame(1, DB::table('order_items')->where('order_id', $order->id)->count());
        $this->assertSame(1, DB::table('payments')->where('order_id', $order->id)->where('status', 'pending')->count());
        $this->assertSame(2, DB::table('order_timeline')->where('order_id', $order->id)->count());   // placed + delivery method
        $this->assertSame(1, DB::table('order_timeline')->where('order_id', $order->id)->where('note', 'Delivery method: Delivery within Australia (A$12.00)')->count());
    }

    public function test_repeated_identical_submission_returns_same_order_instead_of_a_duplicate(): void
    {
        $first = $this->postJson('/checkout/place', $this->body())->assertCreated();
        $second = $this->postJson('/checkout/place', $this->body())->assertOk();

        $this->assertSame($first->json('orderNumber'), $second->json('orderNumber'));
        $this->assertTrue($second->json('duplicate'));
        $this->assertSame(1, $this->orders()->count());
    }

    public function test_different_cart_after_an_order_creates_a_new_order(): void
    {
        $this->postJson('/checkout/place', $this->body(2))->assertCreated();
        $this->postJson('/checkout/place', $this->body(3))->assertCreated();

        $this->assertSame(2, $this->orders()->count());
    }

    public function test_concurrent_submission_while_one_is_in_progress_is_rejected(): void
    {
        $lock = Cache::store('file')->lock('checkout:place:'.$this->customerId, 30);
        $this->assertTrue($lock->get());   // another request is mid-placement

        try {
            $start = microtime(true);
            $this->postJson('/checkout/place', $this->body())->assertStatus(429);
            $this->assertGreaterThanOrEqual(9, microtime(true) - $start);   // waited for the lock first
        } finally {
            $lock->release();
        }
        $this->assertSame(0, $this->orders()->count());
    }

    public function test_out_of_stock_item_is_rejected_and_nothing_is_written(): void
    {
        $body = $this->body();
        $body['items'] = [['productId' => 5, 'quantity' => 1]];

        $this->postJson('/checkout/place', $body)->assertStatus(422);
        $this->assertSame(0, $this->orders()->count());
    }

    public function test_unknown_payment_method_is_rejected(): void
    {
        $body = $this->body();
        $body['paymentMethod'] = 'paypal';   // not implemented by the server

        $this->postJson('/checkout/place', $body)->assertStatus(422);
        $this->assertSame(0, $this->orders()->count());
    }

    public function test_there_is_no_order_note_any_more(): void
    {
        // The cart/checkout Order Note was removed: nothing is stored even if an old client still posts one.
        $res = $this->postJson('/checkout/place', $this->body(3) + ['note' => 'Please leave at the side gate.'])->assertCreated();
        $this->assertNull($this->orders()->where('order_number', $res->json('orderNumber'))->value('customer_note'));
    }

    public function test_cod_clears_the_saved_bag_but_a_failed_order_keeps_it(): void
    {
        $cartId = DB::table('carts')->insertGetId(['customer_id' => $this->customerId, 'status' => 'active']);
        DB::table('cart_items')->insert(['cart_id' => $cartId, 'product_id' => 2, 'quantity' => 2]);
        $items = fn () => DB::table('cart_items')->where('cart_id', $cartId)->count();

        $bad = $this->body();
        $bad['items'] = [['productId' => 5, 'quantity' => 1]];   // out of stock
        $this->postJson('/checkout/place', $bad)->assertStatus(422);
        $this->assertSame(1, $items());
        $this->assertSame('active', DB::table('carts')->where('id', $cartId)->value('status'));

        $this->postJson('/checkout/place', $this->body())->assertCreated();
        $this->assertSame(0, $items());
        $this->assertSame('converted', DB::table('carts')->where('id', $cartId)->value('status'));
    }
}
