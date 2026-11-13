<?php

namespace Tests\Feature;

use App\Models\CustomerOrders;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Gift wrapping through quote, COD / Stripe placement and the order page. Seeded product 2 is a simple A$55 item
 * (sql/local_test_seed.sql); two-plus items of it keep every figure easy to check by hand.
 */
class GiftWrapCheckoutTest extends TestCase
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
        config([
            'services.stripe' => [
                'enabled' => true, 'mode' => 'test', 'key' => 'pk_test_abc', 'secret' => 'sk_test_abc', 'webhook_secret' => 'whsec_test',
                'currency' => 'aud', 'api_version' => '2024-06-20', 'session_minutes' => 60, 'webhook_tolerance' => 300,
            ],
            'storefront.payment_methods' => ['card', 'cod'],
            'app.url' => 'https://shop.example.test',
        ]);
        $this->customerId = DB::table('customers')->insertGetId(['first_name' => 'Buyer', 'email' => 'gift@example.test', 'status' => 'active', 'email_verified_at' => now()]);
        $this->addressId = DB::table('customer_addresses')->insertGetId([
            'customer_id' => $this->customerId, 'label' => 'Home', 'recipient_name' => 'Buyer', 'phone' => '0400000000',
            'street_address' => '1 Test St', 'suburb' => 'Carlton', 'state' => 'VIC', 'postcode' => '3053', 'is_default' => 1,
        ]);
        $this->withSession(['customer_id' => $this->customerId]);
    }

    private function body(int $qty, ?array $gift, string $method = 'cod'): array
    {
        return ['items' => [['productId' => 2, 'quantity' => $qty]], 'paymentMethod' => $method, 'addressId' => $this->addressId, 'gift' => $gift];
    }

    private function gift(int $qty, string $message = ''): array
    {
        return ['enabled' => true, 'message' => $message, 'items' => [['productId' => 2, 'quantity' => $qty]]];
    }

    private function order(?string $number = null)
    {
        return DB::table('orders')->where('customer_id', $this->customerId)->when($number, fn ($q) => $q->where('order_number', $number))->orderByDesc('id')->first();
    }

    public function test_quote_without_gift_is_unchanged(): void
    {
        $q = $this->postJson('/checkout/quote', ['items' => [['productId' => 2, 'quantity' => 2]]])->assertOk();
        $this->assertSame([110, 0, 122, null], [$q->json('subtotal'), $q->json('giftFee'), $q->json('total'), $q->json('gift')]);

        // Present but switched off = same as absent.
        $q = $this->postJson('/checkout/quote', ['items' => [['productId' => 2, 'quantity' => 2]], 'gift' => ['enabled' => false, 'items' => [['productId' => 2, 'quantity' => 2]]]])->assertOk();
        $this->assertSame([0, 122], [$q->json('giftFee'), $q->json('total')]);
    }

    public function test_quote_prices_wrapped_items_not_the_whole_cart(): void
    {
        // 5 in the cart; the wrapping count (and so the fee) follows the selection. COD total = 5 x 55 + A$12 shipping + fee.
        foreach ([[1, 1.99], [2, 1.99], [3, 2.99], [4, 3.99], [5, 4.99]] as [$wrapped, $fee]) {
            $q = $this->postJson('/checkout/quote', $this->body(5, $this->gift($wrapped)))->assertOk();
            $this->assertEquals($fee, $q->json('giftFee'), "wrapping $wrapped");
            $this->assertEquals(287 + $fee, $q->json('total'), "COD total, wrapping $wrapped");
            $this->assertEquals(275, $q->json('subtotal'), 'subtotal stays the goods only');
        }
        // Wrapped quantity is clamped to the cart.
        $this->assertEquals(2.99, $this->postJson('/checkout/quote', $this->body(3, $this->gift(40)))->json('giftFee'));
    }

    public function test_cart_change_recalculates_the_fee(): void
    {
        $this->assertEquals(2.99, $this->postJson('/checkout/quote', $this->body(3, $this->gift(3)))->json('giftFee'));
        // Customer lowers the cart to 2 (the browser clamps its selection; the server clamps too): fee follows.
        $this->assertEquals(1.99, $this->postJson('/checkout/quote', $this->body(2, $this->gift(3)))->json('giftFee'));
        // Removing the gifted line leaves nothing to wrap: no fee.
        $q = $this->postJson('/checkout/quote', ['items' => [['productId' => 3, 'quantity' => 1]], 'gift' => $this->gift(2)])->assertOk();
        $this->assertSame(0, $q->json('giftFee'));
    }

    public function test_fee_never_counts_toward_free_shipping_and_is_not_trusted_from_the_browser(): void
    {
        // 6 x 55 = 330 >= 299: free shipping; the browser-sent "fee" is ignored (server prices 1 wrapped item at 1.99).
        $q = $this->postJson('/checkout/quote', $this->body(6, $this->gift(1) + ['fee' => 0.01]))->assertOk();
        $this->assertEquals([0, 1.99, 331.99], [$q->json('shipping'), $q->json('giftFee'), $q->json('total')]);

        // 5 x 55 = 275 < 299 stays below it however many items are wrapped.
        $this->assertSame(12, $this->postJson('/checkout/quote', $this->body(5, $this->gift(5)))->json('shipping'));
    }

    public function test_cod_order_stores_fee_selection_and_message_and_stays_admin_consistent(): void
    {
        $res = $this->postJson('/checkout/place', $this->body(3, $this->gift(2, "Happy birthday!\nLove, A")) + ['note' => 'Leave at the gate'])->assertCreated();
        $o = $this->order($res->json('orderNumber'));

        $this->assertSame(['cod', 'pending'], [$o->payment_method, $o->payment_status]);
        $this->assertSame('166.99', $o->subtotal);                 // 165.00 goods + 1.99 gift line
        $this->assertSame('0.00', $o->discount_total);
        $this->assertSame('12.00', $o->shipping_total);            // 165 < 299
        $this->assertSame('178.99', $o->grand_total);
        // The admin app's identity: subtotal - discount + shipping + tax = grand total, and subtotal = sum of lines.
        $this->assertEquals($o->grand_total, $o->subtotal - $o->discount_total + $o->shipping_total + $o->tax_total);
        $items = DB::table('order_items')->where('order_id', $o->id)->orderBy('id')->get();
        $this->assertEquals($o->subtotal, $items->sum('line_total'));
        $this->assertSame(['Gift wrapping (2 items)', 'GIFT-WRAP', null, '1.99'], [$items[1]->product_name, $items[1]->sku, $items[1]->product_id, $items[1]->line_total]);
        $this->assertSame('178.99', DB::table('payments')->where('order_id', $o->id)->value('amount'));

        $this->assertStringContainsString('Leave at the gate', $o->customer_note);
        $this->assertStringContainsString('2 x ', $o->customer_note);
        $this->assertStringContainsString("Happy birthday!\nLove, A", $o->customer_note);
    }

    public function test_order_page_shows_gift_separately_from_the_note_and_items(): void
    {
        $res = $this->postJson('/checkout/place', $this->body(3, $this->gift(3, 'With love')) + ['note' => 'Leave at the gate'])->assertCreated();
        $number = $res->json('orderNumber');

        $view = CustomerOrders::find($this->customerId, $number);
        $this->assertSame('Leave at the gate', $view['note']);
        $this->assertCount(1, $view['items'], 'the fee line is not listed as a product');
        $this->assertSame(3, array_sum(array_column($view['items'], 'quantity')));
        $this->assertSame(2.99, $view['gift']['fee']);
        $this->assertSame('With love', $view['gift']['message']);
        $this->assertSame([165.0, 2.99, 12.0, 179.99], [$view['totals']['subtotal'], $view['totals']['giftFee'], $view['totals']['shipping'], $view['totals']['total']]);

        $this->get("/account/orders/$number")->assertOk()->assertSee('Gift wrapping')->assertSee('With love')->assertSee('Leave at the gate');
        $list = CustomerOrders::page($this->customerId, 1)['orders'][0];
        $this->assertSame(3, $list['itemCount']);
    }

    public function test_gift_without_message_and_orders_without_gift(): void
    {
        $res = $this->postJson('/checkout/place', $this->body(1, $this->gift(1)))->assertCreated();
        $view = CustomerOrders::find($this->customerId, $res->json('orderNumber'));
        $this->assertSame('', $view['gift']['message']);
        $this->assertNull($view['note']);

        $plain = $this->postJson('/checkout/place', $this->body(2, null))->assertCreated();
        $view = CustomerOrders::find($this->customerId, $plain->json('orderNumber'));
        $this->assertNull($view['gift']);
        $this->assertSame(0.0, $view['totals']['giftFee']);
        $this->assertSame(1, DB::table('order_items')->where('order_id', $this->order($plain->json('orderNumber'))->id)->count());
    }

    public function test_enabled_but_nothing_selected_is_refused_and_writes_nothing(): void
    {
        $this->postJson('/checkout/place', $this->body(2, ['enabled' => true, 'items' => []]))->assertStatus(422);
        $this->assertSame(0, DB::table('orders')->where('customer_id', $this->customerId)->count());
        $this->postJson('/checkout/place', $this->body(2, $this->gift(1, str_repeat('x', 301))))->assertStatus(422);
    }

    public function test_changing_the_gift_makes_a_new_order_not_a_replay(): void
    {
        $a = $this->postJson('/checkout/place', $this->body(2, $this->gift(1)))->assertCreated();
        $b = $this->postJson('/checkout/place', $this->body(2, $this->gift(2)))->assertCreated();
        $this->assertNotSame($a->json('orderNumber'), $b->json('orderNumber'));
        $this->postJson('/checkout/place', $this->body(2, $this->gift(2)))->assertOk()->assertJson(['duplicate' => true]);
    }

    public function test_online_discount_and_deposit_include_the_fee_and_add_up(): void
    {
        // 3 x 55 = 165 + 2.99 wrapping = 167.99 eligible; 2% = 3.36 (half-up); + 12 shipping => 176.63; 20% deposit = 35.33.
        $q = $this->postJson('/checkout/quote', $this->body(3, $this->gift(3)))->assertOk();
        $online = $q->json('online');
        $this->assertEquals([167.99, 3.36, 12, 176.63, 35.33, 141.30], [$online['merchandise'], $online['discount'], $online['shipping'], $online['total'], $online['deposit'], $online['balance']]);
        $this->assertEquals(179.99, $q->json('total'), 'COD figure: no online discount');
        $this->assertEquals($online['total'], round($online['deposit'] + $online['balance'], 2));
    }

    public function test_stripe_order_snapshots_the_plan_and_charges_the_deposit_of_the_total_with_the_fee(): void
    {
        $charged = null;
        Http::fake(function ($req) use (&$charged) {
            $d = $req->data();
            $charged = (int) $d['line_items'][0]['price_data']['unit_amount'];

            return Http::response(['id' => 'cs_test_gift', 'object' => 'checkout.session', 'status' => 'open', 'payment_status' => 'unpaid',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_gift', 'expires_at' => (int) $d['expires_at'], 'amount_total' => $charged, 'currency' => 'aud', 'metadata' => $d['metadata']]);
        });

        $res = $this->postJson('/checkout/place', $this->body(3, $this->gift(3, 'For you'), 'card'))->assertCreated();
        $o = $this->order($res->json('orderNumber'));

        $this->assertSame(['card', 'pending', 'pending'], [$o->payment_method, $o->payment_status, $o->order_status]);
        $this->assertSame('167.99', $o->subtotal);            // 165.00 goods + 2.99 wrapping line
        $this->assertSame(3533, $charged, 'Stripe is asked for the 20% deposit, in cents');
        $this->assertSame('3.36', $o->discount_total);        // the online discount, on goods + wrapping
        $this->assertSame('176.63', $o->grand_total);
        $this->assertEquals($o->grand_total, $o->subtotal - $o->discount_total + $o->shipping_total + $o->tax_total);
        $pay = DB::table('payments')->where('order_id', $o->id)->first();
        $this->assertSame('35.33', $pay->amount);             // 20% deposit of the fee-inclusive total
        $plan = json_decode($pay->response_data, true)['plan'];
        $this->assertEquals([167.99, 35.33, 141.30], [$plan['merchandise'], $plan['deposit'], $plan['balance']]);
        $this->assertStringContainsString('For you', $o->customer_note);
        $this->assertSame(2.99, CustomerOrders::find($this->customerId, $o->order_number)['gift']['fee']);
    }
}
