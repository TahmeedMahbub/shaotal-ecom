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
            'app.url' => 'https://shop.example.test',
        ]);
        \App\Support\StoreConfig::fake(['payment_methods' => ['card', 'cod']]);
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
        $res = $this->postJson('/checkout/place', $this->body(3, $this->gift(2, "Happy birthday!\nLove, A")))->assertCreated();
        $o = $this->order($res->json('orderNumber'));

        $this->assertSame(['cod', 'pending'], [$o->payment_method, $o->payment_status]);
        $this->assertSame('165.00', $o->subtotal);                 // goods only: the fee is not inside subtotal
        $this->assertSame('0.00', $o->discount_total);
        $this->assertSame('12.00', $o->shipping_total);            // 165 < 299
        $this->assertSame('178.99', $o->grand_total);              // 165 + 12 + 1.99 fee, exactly once
        // The Admin contract: subtotal - discount + shipping + tax + gift_wrap_fee = grand total.
        $this->assertEquals($o->grand_total, $o->subtotal - $o->discount_total + $o->shipping_total + $o->tax_total + $o->gift_wrap_fee);
        $this->assertSame([1, 2, '1.99', "Happy birthday!\nLove, A"], [$o->gift_wrap_requested, $o->gift_wrap_items_count, $o->gift_wrap_fee, $o->gift_message]);
        $this->assertNull($o->customer_note, 'the gift no longer lives in customer_note');
        $items = DB::table('order_items')->where('order_id', $o->id)->orderBy('id')->get();
        $this->assertCount(1, $items, 'no GIFT-WRAP order line');
        $this->assertSame([3, 2], [(int) $items[0]->quantity, (int) $items[0]->gift_wrap_qty]);
        $this->assertEquals($o->subtotal, $items->sum('line_total'));
        $this->assertSame($o->gift_wrap_items_count, (int) $items->sum('gift_wrap_qty'));
        $this->assertSame('178.99', DB::table('payments')->where('order_id', $o->id)->value('amount'));
    }

    public function test_order_page_shows_gift_separately_from_the_note_and_items(): void
    {
        $res = $this->postJson('/checkout/place', $this->body(3, $this->gift(3, 'With love')))->assertCreated();
        $number = $res->json('orderNumber');

        $view = CustomerOrders::find($this->customerId, $number);
        $this->assertNull($view['note']);
        $this->assertCount(1, $view['items']);
        $this->assertSame(['3 x '.$view['items'][0]['name']], $view['gift']['items']);
        $this->assertSame(3, array_sum(array_column($view['items'], 'quantity')));
        $this->assertSame(2.99, $view['gift']['fee']);
        $this->assertSame('With love', $view['gift']['message']);
        $this->assertSame([165.0, 2.99, 12.0, 179.99], [$view['totals']['subtotal'], $view['totals']['giftFee'], $view['totals']['shipping'], $view['totals']['total']]);

        $this->get("/account/orders/$number")->assertOk()->assertSee('Gift wrapping')->assertSee('With love');
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
        $plainOrder = $this->order($plain->json('orderNumber'));
        $this->assertSame([0, 0, '0.00', null], [(int) $plainOrder->gift_wrap_requested, $plainOrder->gift_wrap_items_count, $plainOrder->gift_wrap_fee, $plainOrder->gift_message]);
        $this->assertSame([1, 0], [DB::table('order_items')->where('order_id', $plainOrder->id)->count(), (int) DB::table('order_items')->where('order_id', $plainOrder->id)->sum('gift_wrap_qty')]);
        $this->assertEquals($plainOrder->grand_total, $plainOrder->subtotal + $plainOrder->shipping_total);
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
        // 3 x 55 = 165 + 2.99 wrapping = 167.99 eligible; 2% = 3.36 (half-up); + 12 shipping => 176.63; 30% deposit = 52.99.
        $q = $this->postJson('/checkout/quote', $this->body(3, $this->gift(3)))->assertOk();
        $online = $q->json('online');
        $this->assertEquals([167.99, 3.36, 12, 176.63, 52.99, 123.64], [$online['merchandise'], $online['discount'], $online['shipping'], $online['total'], $online['deposit'], $online['balance']]);
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
        $this->assertSame('165.00', $o->subtotal);            // goods only
        $this->assertSame(['1', '3', '2.99', 'For you'], [(string) $o->gift_wrap_requested, (string) $o->gift_wrap_items_count, $o->gift_wrap_fee, $o->gift_message]);
        $this->assertSame(5299, $charged, 'Stripe is asked for the 30% deposit, in cents');
        $this->assertSame('3.36', $o->discount_total);        // the online discount, on goods + wrapping
        $this->assertSame('176.63', $o->grand_total);
        $this->assertEquals($o->grand_total, $o->subtotal - $o->discount_total + $o->shipping_total + $o->tax_total + $o->gift_wrap_fee);
        $pay = DB::table('payments')->where('order_id', $o->id)->first();
        $this->assertSame('52.99', $pay->amount);             // 30% deposit of the fee-inclusive total
        $plan = json_decode($pay->response_data, true)['plan'];
        $this->assertEquals([167.99, 52.99, 123.64], [$plan['merchandise'], $plan['deposit'], $plan['balance']]);
        $this->assertNull($o->customer_note);
        $this->assertSame(2.99, CustomerOrders::find($this->customerId, $o->order_number)['gift']['fee']);
    }

    /** A historical order (fee as a GIFT-WRAP line inside subtotal, gift block in customer_note, gift columns at defaults). */
    public function test_historical_gift_order_still_displays_and_totals_correctly(): void
    {
        $id = DB::table('orders')->insertGetId([
            'order_number' => 'INV-legacy1', 'customer_id' => $this->customerId, 'shipping_recipient_name' => 'B', 'shipping_phone' => '1', 'shipping_street_address' => '1 St',
            'subtotal' => 166.99, 'shipping_total' => 12, 'grand_total' => 178.99, 'payment_method' => 'cod', 'placed_at' => now(),
            'customer_note' => "Leave at gate\n\n--- GIFT WRAPPING ---\nWrapping fee: A$1.99 for 2 items\n- 2 x Jute Basket\nGift message: Old message",
        ]);
        DB::table('order_items')->insert([
            ['order_id' => $id, 'product_id' => 2, 'product_name' => 'Jute Basket', 'sku' => 'JB', 'unit_price' => 55, 'unit_selling_price' => 55, 'quantity' => 3, 'line_total' => 165],
            ['order_id' => $id, 'product_id' => null, 'product_name' => 'Gift wrapping (2 items)', 'sku' => 'GIFT-WRAP', 'unit_price' => 1.99, 'unit_selling_price' => 1.99, 'quantity' => 1, 'line_total' => 1.99],
        ]);
        $v = CustomerOrders::find($this->customerId, 'INV-legacy1');
        $this->assertCount(1, $v['items']);
        $this->assertSame('Leave at gate', $v['note']);
        $this->assertSame(['2 x Jute Basket'], $v['gift']['items']);
        $this->assertSame('Old message', $v['gift']['message']);
        $this->assertSame([165.0, 1.99, 12.0, 178.99], [$v['totals']['subtotal'], $v['totals']['giftFee'], $v['totals']['shipping'], $v['totals']['total']]);
        $this->assertSame(3, CustomerOrders::page($this->customerId, 1)['orders'][0]['itemCount']);
    }
}
