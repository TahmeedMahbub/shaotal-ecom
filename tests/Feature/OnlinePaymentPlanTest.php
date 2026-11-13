<?php

namespace Tests\Feature;

use App\Models\CardPayments;
use App\Payments\OnlinePaymentPlan;
use App\Payments\Stripe;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Online payment = pre-booking: 2% off the merchandise, 20% deposit through Stripe, 80% outstanding. COD untouched.
 * Product 2 (A$55) from sql/local_test_seed.sql; shipping A$12 below A$299.
 */
class OnlinePaymentPlanTest extends TestCase
{
    use DatabaseTransactions;

    private const WHSEC = 'whsec_test_secret';

    private int $customerId;

    private int $addressId;

    private array $sessions = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires the MySQL test database.');
        }
        config([
            'services.stripe' => [
                'enabled' => true, 'mode' => 'test', 'key' => 'pk_test_abc', 'secret' => 'sk_test_abc', 'webhook_secret' => self::WHSEC,
                'currency' => 'aud', 'api_version' => '2024-06-20', 'session_minutes' => 60, 'webhook_tolerance' => 300,
            ],
            'storefront.payment_methods' => ['card', 'cod'],
            'app.url' => 'https://shop.example.test',
        ]);
        Cache::store('file')->flush();
        $this->customerId = DB::table('customers')->insertGetId(['first_name' => 'Payer', 'email' => 'plan@example.test', 'status' => 'active', 'email_verified_at' => now()]);
        $this->addressId = DB::table('customer_addresses')->insertGetId([
            'customer_id' => $this->customerId, 'label' => 'Home', 'recipient_name' => 'Payer', 'phone' => '0400000000',
            'street_address' => '1 Test St', 'suburb' => 'Carlton', 'state' => 'VIC', 'postcode' => '3053', 'is_default' => 1,
        ]);
        $this->seedBag();
        $this->withSession(['customer_id' => $this->customerId]);
        Http::fake(function (HttpRequest $req) {
            $path = parse_url($req->url(), PHP_URL_PATH);
            if ($req->method() === 'POST' && $path === '/v1/checkout/sessions') {
                $d = $req->data();
                $id = 'cs_test_'.count($this->sessions).bin2hex(random_bytes(4));

                return Http::response($this->sessions[$id] = [
                    'id' => $id, 'status' => 'open', 'payment_status' => 'unpaid', 'url' => 'https://checkout.stripe.com/c/pay/'.$id,
                    'expires_at' => (int) $d['expires_at'], 'amount_total' => (int) $d['line_items'][0]['price_data']['unit_amount'],
                    'currency' => 'aud', 'metadata' => $d['metadata'],
                ]);
            }

            return Http::response(['error' => []], 404);
        });
    }

    private function seedBag(): void
    {
        $cart = DB::table('carts')->insertGetId(['customer_id' => $this->customerId, 'status' => 'active']);
        DB::table('cart_items')->insert(['cart_id' => $cart, 'product_id' => 2, 'quantity' => 2]);
    }

    private function bag(): array
    {
        $carts = DB::table('carts')->where('customer_id', $this->customerId)->where('status', 'active')->pluck('id');

        return [$carts->count(), DB::table('cart_items')->whereIn('cart_id', $carts)->count()];
    }

    private function place(string $method, array $extra = [])
    {
        return $this->postJson('/checkout/place', $extra + [
            'items' => [['productId' => 2, 'quantity' => 2]], 'paymentMethod' => $method, 'addressId' => $this->addressId,
        ]);
    }

    private function webhook(string $type, array $object, ?string $id = null)
    {
        $payload = json_encode(['id' => $id ?? 'evt_'.bin2hex(random_bytes(6)), 'type' => $type, 'livemode' => false, 'data' => ['object' => $object]]);

        return $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => Stripe::signatureHeader($payload, self::WHSEC),
        ], $payload);
    }

    private function paidSession(string $sid): array
    {
        return array_merge($this->sessions[$sid], ['status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_1']);
    }

    /* ── Calculation ── */

    public function test_rates_live_in_one_place_and_balance_is_the_remainder(): void
    {
        $this->assertSame(['discountPercent' => 2.0, 'depositPercent' => 20.0, 'balancePercent' => 80.0], OnlinePaymentPlan::settings());
        config(['storefront.online_payment' => ['discount_percent' => 5, 'deposit_percent' => 30]]);
        $this->assertSame(70.0, OnlinePaymentPlan::settings()['balancePercent']);
    }

    #[DataProvider('totals')]
    public function test_discount_and_deposit_calculation(float $merch, float $ship, float $discount, float $total, float $deposit, float $balance): void
    {
        $p = OnlinePaymentPlan::calculate($merch, $ship);
        $this->assertEqualsWithDelta($discount, $p['discount'], 0.0001);
        $this->assertEqualsWithDelta($total, $p['total'], 0.0001);
        $this->assertEqualsWithDelta($deposit, $p['deposit'], 0.0001);
        $this->assertEqualsWithDelta($balance, $p['balance'], 0.0001);
        $this->assertSame(OnlinePaymentPlan::cents($p['total']), OnlinePaymentPlan::cents($p['deposit']) + OnlinePaymentPlan::cents($p['balance']), 'deposit + balance == total, to the cent');
        $this->assertEqualsWithDelta($ship, $p['shipping'], 0.0001, 'shipping is never discounted');
    }

    public static function totals(): array
    {
        return [
            'seed order' => [110.00, 12.00, 2.20, 119.80, 23.96, 95.84],
            'free shipping' => [299.00, 0.00, 5.98, 293.02, 58.60, 234.42],     // 58.604 -> 58.60
            'half-cent rounds up' => [10.25, 12.00, 0.21, 22.04, 4.41, 17.63],  // 0.205 -> 0.21; 4.408 -> 4.41
            'tiny' => [0.50, 12.00, 0.01, 12.49, 2.50, 9.99],                   // 0.01; 2.498 -> 2.50
            'large' => [12345.67, 0.00, 246.91, 12098.76, 2419.75, 9679.01],
            'fully discounted by coupon' => [0.00, 12.00, 0.00, 12.00, 2.40, 9.60],
        ];
    }

    /* ── Quote / placement ── */

    public function test_quote_returns_cod_total_and_the_online_breakdown(): void
    {
        $q = $this->postJson('/checkout/quote', ['items' => [['productId' => 2, 'quantity' => 2]]])->assertOk()->json();
        $this->assertEquals(122.00, $q['total']);
        $this->assertEquals(110.00, $q['subtotal']);
        $this->assertEquals([2.20, 119.80, 23.96, 95.84, 12.00], [$q['online']['discount'], $q['online']['total'], $q['online']['deposit'], $q['online']['balance'], $q['online']['shipping']]);
    }

    public function test_coupon_is_applied_before_the_online_discount(): void
    {
        $this->makeCoupon10();
        $q = $this->postJson('/checkout/quote', ['items' => [['productId' => 2, 'quantity' => 2]], 'coupon' => 'TEN'])->assertOk()->json();
        $this->assertEquals(11.00, $q['discount']);
        $this->assertEquals(122.00 - 11.00, $q['total']);
        // eligible = 110 - 11 = 99 -> 2% = 1.98 -> 99 - 1.98 + 12 = 109.02 -> deposit 21.80
        $this->assertEquals([1.98, 109.02, 21.80, 87.22], [$q['online']['discount'], $q['online']['total'], $q['online']['deposit'], $q['online']['balance']]);

        $n = $this->place('card', ['coupon' => 'TEN'])->assertCreated()->json('orderNumber');
        $o = DB::table('orders')->where('order_number', $n)->first();
        $this->assertSame(['109.02', '12.98'], [$o->grand_total, $o->discount_total]);   // discount_total = coupon 11.00 + online 1.98
        $this->assertSame($o->subtotal - $o->discount_total + $o->shipping_total, (float) $o->grand_total);
    }

    private function makeCoupon10(): void
    {
        DB::table('coupons')->insert(['code' => 'TEN', 'discount_type' => 'percentage', 'discount_value' => 10, 'status' => 'active']);
    }

    public function test_cod_has_no_online_discount_no_advance_and_clears_the_bag_at_once(): void
    {
        $res = $this->place('cod')->assertCreated();
        $this->assertArrayNotHasKey('redirectUrl', $res->json());
        $this->assertFalse($res->json('prebooked'));
        $o = DB::table('orders')->where('order_number', $res->json('orderNumber'))->first();
        $this->assertSame(['122.00', '0.00', 'pending', 'cod'], [$o->grand_total, $o->discount_total, $o->payment_status, $o->payment_method]);
        $pay = DB::table('payments')->where('order_id', $o->id)->first();
        $this->assertSame(['122.00', 'pending', null], [$pay->amount, $pay->status, $pay->provider]);
        $this->assertSame([0, 0], $this->bag());
        $this->get('/account/orders/'.$o->order_number)->assertOk()->assertSee('Pay on delivery')->assertDontSee('Online payment discount')->assertDontSee('Remaining balance');
    }

    public function test_card_order_records_discount_plan_and_keeps_the_bag(): void
    {
        $res = $this->place('card')->assertCreated();
        $this->assertTrue($res->json('prebooked'));
        $this->assertEquals([119.80, 23.96, 95.84], [$res->json('total'), $res->json('payNow'), $res->json('balance')]);
        $o = DB::table('orders')->where('order_number', $res->json('orderNumber'))->first();
        $plan = OnlinePaymentPlan::forOrder($o->id);
        $this->assertEquals(['discountPercent' => 2, 'depositPercent' => 20, 'discount' => 2.2, 'deposit' => 23.96, 'balance' => 95.84], array_intersect_key($plan, array_flip(['discountPercent', 'depositPercent', 'discount', 'deposit', 'balance'])));
        $this->assertSame([1, 1], $this->bag());
        $this->assertSame(2396, array_values($this->sessions)[0]['amount_total']);

        // Changing the rates later never alters an order that was already placed.
        config(['storefront.online_payment' => ['discount_percent' => 9, 'deposit_percent' => 50]]);
        $this->assertSame(23.96, OnlinePaymentPlan::forOrder($o->id)['deposit']);
        $this->get('/account/orders/'.$o->order_number)->assertOk()->assertSee('Online payment discount (2%)', false)->assertSee('Pay now (20%)', false)->assertSee('Remaining balance (80%)', false);
    }

    /* ── Stripe outcomes ── */

    public function test_deposit_success_is_pre_booked_never_paid_and_clears_the_bag_once(): void
    {
        $n = $this->place('card')->assertCreated()->json('orderNumber');
        $sid = array_key_first($this->sessions);
        $this->webhook('checkout.session.completed', $this->paidSession($sid), 'evt_a')->assertJson(['outcome' => 'paid']);
        $this->webhook('checkout.session.completed', $this->paidSession($sid), 'evt_a')->assertJson(['outcome' => 'duplicate']);
        $this->webhook('checkout.session.completed', $this->paidSession($sid), 'evt_b')->assertJson(['outcome' => 'already_paid']);

        $o = DB::table('orders')->where('order_number', $n)->first();
        $this->assertSame('pending', $o->payment_status);
        $this->assertSame(23.96, CardPayments::paidTotal($o->id));
        $this->assertSame([0, 0], $this->bag());
        $this->assertSame(1, DB::table('order_timeline')->where('order_id', $o->id)->where('note', 'like', 'Pre-booking deposit%')->count());

        $page = $this->get('/account/orders/'.$n)->assertOk();
        $page->assertSee('Pre-booked (deposit paid)')->assertSee('Deposit paid')->assertSee('A$95.84');
        $page->assertDontSee('id="order-pay-btn"', false);
        $this->assertStringNotContainsString('order-payment-status">Paid<', $page->getContent());
        $this->getJson("/account/orders/{$n}/payment-status")->assertJson(['paymentStatus' => 'pending', 'received' => true]);
    }

    public function test_deposit_cannot_be_collected_twice_or_cancelled_online(): void
    {
        $n = $this->place('card')->assertCreated()->json('orderNumber');
        $sid = array_key_first($this->sessions);
        $this->webhook('checkout.session.completed', $this->paidSession($sid))->assertJson(['outcome' => 'paid']);
        $this->postJson("/account/orders/{$n}/pay")->assertStatus(409);
        $this->postJson("/account/orders/{$n}/cancel")->assertStatus(422);
        $this->assertCount(1, $this->sessions);
        $this->assertSame('pending', DB::table('orders')->where('order_number', $n)->value('order_status'));
    }

    public function test_declined_or_cancelled_payment_does_not_pre_book_and_keeps_the_bag(): void
    {
        $n = $this->place('card')->assertCreated()->json('orderNumber');
        $o = DB::table('orders')->where('order_number', $n)->first();
        $pay = DB::table('payments')->where('order_id', $o->id)->first();
        $pi = ['id' => 'pi_1', 'metadata' => ['order_number' => $n, 'payment_id' => (string) $pay->id], 'last_payment_error' => ['code' => 'card_declined']];
        $this->webhook('payment_intent.payment_failed', $pi)->assertJson(['outcome' => 'declined']);
        $this->assertSame(0.0, CardPayments::paidTotal($o->id));
        $this->assertSame('failed', DB::table('orders')->where('id', $o->id)->value('payment_status'));
        $this->assertSame([1, 1], $this->bag());
        $this->get('/checkout/payment/cancel?order='.$n)->assertOk()->assertSee('Payment cancelled')->assertSee('A$23.96 now', false)->assertSee('data-clear-cart="0"', false);
        $this->assertSame([1, 1], $this->bag());
        // Retry still asks for the deposit only.
        $this->postJson("/account/orders/{$n}/pay")->assertOk();
    }

    public function test_paying_the_balance_later_completes_the_order_only_when_the_total_is_reached(): void
    {
        $n = $this->place('card')->assertCreated()->json('orderNumber');
        $o = DB::table('orders')->where('order_number', $n)->first();
        $this->webhook('checkout.session.completed', $this->paidSession(array_key_first($this->sessions)))->assertJson(['outcome' => 'paid']);

        // A later payment for the balance (A$95.84), recorded by the future balance flow / staff as a Stripe payment row.
        $pid = DB::table('payments')->insertGetId(['order_id' => $o->id, 'provider' => 'stripe', 'provider_transaction_id' => 'cs_test_balance', 'payment_method' => 'card', 'amount' => 95.84, 'status' => 'pending']);
        $session = ['id' => 'cs_test_balance', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 9584, 'currency' => 'aud', 'metadata' => ['order_number' => $n, 'payment_id' => (string) $pid]];
        $this->webhook('checkout.session.completed', $session)->assertJson(['outcome' => 'paid']);
        $this->assertSame('paid', DB::table('orders')->where('id', $o->id)->value('payment_status'));
        $this->assertSame(119.80, CardPayments::paidTotal($o->id));
    }

    public function test_card_order_placed_before_prebooking_still_pays_in_full(): void
    {
        $n = $this->place('card')->assertCreated()->json('orderNumber');
        $o = DB::table('orders')->where('order_number', $n)->first();
        DB::table('payments')->where('order_id', $o->id)->update(['response_data' => null, 'amount' => $o->grand_total, 'provider_transaction_id' => 'cs_test_legacy']);
        $this->assertNull(OnlinePaymentPlan::forOrder($o->id));
        $session = ['id' => 'cs_test_legacy', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 11980, 'currency' => 'aud',
            'metadata' => ['order_number' => $n, 'payment_id' => (string) DB::table('payments')->where('order_id', $o->id)->value('id')]];
        $this->webhook('checkout.session.completed', $session)->assertJson(['outcome' => 'paid']);
        $this->assertSame('paid', DB::table('orders')->where('id', $o->id)->value('payment_status'));
    }

    public function test_announcement_bar_renders_above_the_header_and_not_inside_content(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $bar = strpos($html, 'id="announcement-bar"');
        $nav = strpos($html, 'id="navbar"');
        $this->assertNotFalse($bar);
        $this->assertLessThan($nav, $bar);
        $this->assertStringContainsString('Delivery across Australia', $html);
        $this->assertSame(1, substr_count($html, 'id="announcement-bar"'));
        $this->get('/checkout')->assertOk()->assertDontSee('id="announcement-bar"', false);   // minimal checkout layout
    }

    public function test_checkout_page_shows_the_two_payment_cards_with_copy_from_settings(): void
    {
        $page = $this->get('/checkout')->assertOk();
        $page->assertSee('Online Payment')->assertSee('EXTRA 2% OFF')->assertSee('Pay just 20% now to pre-book your order.')
            ->assertSee('Secure payment via Credit / Debit Card. Remaining 80% payable later.')
            ->assertSee('Cash on Delivery')->assertSee('PAY ON DELIVERY')->assertSee('Pay the full amount when your order arrives.')->assertSee('No advance payment required.');
        config(['storefront.online_payment' => ['discount_percent' => 3, 'deposit_percent' => 25]]);
        $this->get('/checkout')->assertSee('EXTRA 3% OFF')->assertSee('Pay just 25% now')->assertSee('Remaining 75% payable later');
    }
}
