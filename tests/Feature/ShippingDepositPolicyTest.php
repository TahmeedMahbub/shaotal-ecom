<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmationMail;
use App\Models\CardPayments;
use App\Models\Checkout;
use App\Models\CustomerOrders;
use App\Models\Locations;
use App\Payments\OnlinePaymentPlan;
use App\Payments\Stripe;
use App\Support\OrderStatus;
use App\Support\Shipping;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Shipping & deposit policy (2026-11-14): Adelaide delivery A$5, rest of Australia A$12, free pickup from West Croydon;
 * every card order pays a 30% deposit now (2% online discount kept), COD unchanged. Product 2 = A$55 (sql/local_test_seed.sql).
 */
class ShippingDepositPolicyTest extends TestCase
{
    use DatabaseTransactions;

    private const WHSEC = 'whsec_test_secret';

    private int $customerId;

    private int $adelaideId;

    private int $regionalSaId;

    private int $melbourneId;

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
            'app.url' => 'https://shop.example.test',
        ]);
        \App\Support\StoreConfig::fake(['payment_methods' => ['card', 'cod']]);
        Cache::store('file')->flush();
        Shipping::flush();
        $this->customerId = DB::table('customers')->insertGetId(['first_name' => 'Ada', 'email' => 'ship@example.test', 'phone' => '0400111222', 'status' => 'active', 'email_verified_at' => now()]);
        $addr = fn (string $suburb, string $state, string $postcode, int $default = 0) => DB::table('customer_addresses')->insertGetId([
            'customer_id' => $this->customerId, 'label' => 'Home', 'recipient_name' => 'Ada', 'phone' => '0400111222',
            'street_address' => '1 Test St', 'suburb' => $suburb, 'state' => $state, 'postcode' => $postcode, 'is_default' => $default,
        ]);
        $this->adelaideId = $addr('Norwood', 'SA', '5067', 1);
        $this->regionalSaId = $addr('Mount Barker', 'SA', '5251');
        $this->melbourneId = $addr('Carlton', 'VIC', '3053');
        $this->withSession(['customer_id' => $this->customerId]);
        Http::fake(function (HttpRequest $req) {
            if ($req->method() === 'POST' && parse_url($req->url(), PHP_URL_PATH) === '/v1/checkout/sessions') {
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

    private function items(int $qty = 2): array
    {
        return [['productId' => 2, 'quantity' => $qty]];
    }

    private function place(string $method, array $extra = [])
    {
        return $this->postJson('/checkout/place', $extra + ['items' => $this->items(), 'paymentMethod' => $method, 'addressId' => $this->adelaideId]);
    }

    private function order(string $number): object
    {
        return DB::table('orders')->where('order_number', $number)->first();
    }

    private function webhook(string $type, array $object)
    {
        $payload = json_encode(['id' => 'evt_'.bin2hex(random_bytes(6)), 'type' => $type, 'livemode' => false, 'data' => ['object' => $object]]);

        return $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => Stripe::signatureHeader($payload, self::WHSEC),
        ], $payload);
    }

    /* ── Zones: one central list, never "all of SA" ── */

    public function test_adelaide_needs_sa_and_a_listed_suburb_with_its_postcode(): void
    {
        $this->assertTrue(Shipping::isAdelaide('Adelaide', '5000', 'SA'));
        $this->assertTrue(Shipping::isAdelaide('West Croydon', '5008', 'SA'));
        $this->assertTrue(Shipping::isAdelaide('  st   peters ', '5069', 'sa'));         // case/spacing-insensitive
        $this->assertTrue(Shipping::isAdelaide('Saint Peters', '5069', 'SA'));            // Saint = St
        $this->assertTrue(Shipping::isAdelaide('Mt Osmond', '5064', 'SA'));               // Mt = Mount
        $this->assertTrue(Shipping::isAdelaide('OHalloran Hill', '5158', 'SA'));          // apostrophe optional
        $this->assertTrue(Shipping::isAdelaide('Seaford', '5169', 'SA'));

        $this->assertFalse(Shipping::isAdelaide('Mount Barker', '5251', 'SA'));           // Adelaide Hills: rest of Australia
        $this->assertFalse(Shipping::isAdelaide('Gawler', '5118', 'SA'));
        $this->assertFalse(Shipping::isAdelaide('Whyalla', '5600', 'SA'));
        $this->assertFalse(Shipping::isAdelaide('Adelaide', '5999', 'SA'));               // listed suburb, wrong postcode
        $this->assertFalse(Shipping::isAdelaide('Norwood', '5000', 'SA'));                // postcode of another listed suburb
        $this->assertFalse(Shipping::isAdelaide('Adelaide', '5000', 'VIC'));              // wrong state
        $this->assertFalse(Shipping::isAdelaide('Unknown Place', '5067', 'SA'));          // Adelaide postcode, unknown suburb
        $this->assertFalse(Shipping::isAdelaide(null, null, null));
    }

    public function test_the_adelaide_list_is_configurable_in_one_place(): void
    {
        $this->assertFalse(Shipping::isAdelaide('Mount Barker', '5251', 'SA'));
        config(['adelaide_localities.5251' => ['Mount Barker']]);
        Shipping::flush();
        $this->assertTrue(Shipping::isAdelaide('Mount Barker', '5251', 'SA'));
        $this->assertSame('adelaide', Shipping::quote('delivery', ['suburb' => 'Mount Barker', 'state' => 'SA', 'postcode' => '5251'], 50)['method']);
    }

    public function test_every_adelaide_locality_is_a_real_location_when_the_dataset_is_loaded(): void
    {
        if (! Locations::available()) {
            $this->markTestSkipped('Australian locations dataset (au_localities) not loaded in this database.');
        }
        $missing = [];
        foreach (config('adelaide_localities') as $postcode => $suburbs) {
            foreach ($suburbs as $suburb) {
                if (! Locations::matches('SA', $suburb, (string) $postcode)) {
                    $missing[] = "{$suburb} {$postcode}";
                }
            }
        }
        $this->assertSame([], $missing, 'config/adelaide_localities.php entries not in the locations dataset');
    }

    public function test_zone_fees_pickup_and_free_delivery_threshold(): void
    {
        $adl = ['suburb' => 'Norwood', 'state' => 'SA', 'postcode' => '5067'];
        $sa = ['suburb' => 'Mount Barker', 'state' => 'SA', 'postcode' => '5251'];
        $vic = ['suburb' => 'Carlton', 'state' => 'VIC', 'postcode' => '3053'];

        $q = Shipping::quote('delivery', $adl, 100);
        $this->assertSame(['adelaide', 5.0, 'Adelaide delivery'], [$q['method'], $q['fee'], $q['label']]);
        $this->assertSame(['australia', 12.0], [Shipping::quote('delivery', $sa, 100)['method'], Shipping::quote('delivery', $sa, 100)['fee']]);
        $this->assertSame(['australia', 12.0], [Shipping::quote('delivery', $vic, 100)['method'], Shipping::quote('delivery', $vic, 100)['fee']]);
        $pickup = Shipping::quote('pickup', $vic, 100);
        $this->assertSame(['pickup', 'pickup', 0.0, 'Free Pickup – West Croydon'], [$pickup['fulfillment'], $pickup['method'], $pickup['fee'], $pickup['label']]);

        // No address chosen yet: shown as the rest-of-Australia rate (never undercharged), flagged as needing an address.
        $none = Shipping::quote('delivery', null, 100);
        $this->assertSame([12.0, true], [$none['fee'], $none['addressNeeded']]);

        // Delivery stays free at or above the threshold in both zones (existing owner decision); pickup is always free.
        $this->assertSame(0.0, Shipping::quote('delivery', $adl, 299)['fee']);
        $this->assertSame(0.0, Shipping::quote('delivery', $vic, 299)['fee']);
        $this->assertSame(5.0, Shipping::quote('delivery', $adl, 298.99)['fee']);
        \App\Support\StoreConfig::fake(['free_shipping_threshold' => null]);
        $this->assertSame(12.0, Shipping::quote('delivery', $vic, 5000)['fee']);

        // Pickup switched off: a pickup request falls back to delivery pricing.
        \App\Support\StoreConfig::fake(['pickup_enabled' => false]);
        $this->assertSame('delivery', Shipping::quote('pickup', $vic, 100)['fulfillment']);
    }

    /* ── Deposit calculation (the brief's example) ── */

    public function test_brief_example_100_dollars_adelaide(): void
    {
        $lines = [['productId' => 2, 'categoryId' => 2, 'price' => 100.00, 'quantity' => 1]];
        $delivery = ['fulfillment' => 'delivery', 'address' => ['suburb' => 'Adelaide', 'state' => 'SA', 'postcode' => '5000']];

        // Without the online discount: exactly the brief's numbers.
        \App\Support\StoreConfig::fake(['online_payment_discount_percent' => 0]);
        $t = Checkout::totals($lines, null, null, null, $delivery);
        $this->assertSame([100.0, 5.0, 105.0], [$t['subtotal'], $t['shipping'], $t['total']]);
        $this->assertEquals([105.00, 31.50, 73.50], [$t['online']['total'], $t['online']['deposit'], $t['online']['balance']]);

        // With the kept 2% online discount (on the items only): 100 - 2 + 5 = 103 -> 30.90 now, 72.10 later.
        \App\Support\StoreConfig::fake(['online_payment_discount_percent' => 2]);
        $t = Checkout::totals($lines, null, null, null, $delivery);
        $this->assertSame(105.0, $t['total']);   // COD: unchanged, no deposit
        $this->assertEquals([2.00, 103.00, 30.90, 72.10, 30.0, 70.0], [$t['online']['discount'], $t['online']['total'], $t['online']['deposit'], $t['online']['balance'], $t['online']['depositPercent'], $t['online']['balancePercent']]);
        $card = Checkout::forMethod($t, 'card');
        $this->assertEquals([103.00, 30.90], [$card['total'], $card['chargeNow']]);
        $this->assertSame(0.0, Checkout::forMethod($t, 'cod')['chargeNow']);
    }

    /* ── Quote endpoint: server decides; client amounts are ignored ── */

    public function test_quote_uses_the_saved_address_zone_and_ignores_client_amounts(): void
    {
        $q = $this->postJson('/checkout/quote', ['items' => $this->items(), 'fulfillment' => 'delivery', 'addressId' => $this->adelaideId,
            'shipping' => 0, 'deposit' => 0.01, 'online' => ['deposit' => 0.01]])->assertOk()->json();
        $this->assertSame(['adelaide', 'Adelaide delivery'], [$q['delivery']['method'], $q['delivery']['label']]);
        $this->assertEquals([110, 5, 115], [$q['subtotal'], $q['shipping'], $q['total']]);
        // 110 - 2.20 + 5 = 112.80 -> 30% = 33.84
        $this->assertEquals([112.80, 33.84, 78.96], [$q['online']['total'], $q['online']['deposit'], $q['online']['balance']]);

        $this->assertEquals(12, $this->postJson('/checkout/quote', ['items' => $this->items(), 'addressId' => $this->regionalSaId])->json('shipping'));
        $this->assertEquals(0, $this->postJson('/checkout/quote', ['items' => $this->items(), 'fulfillment' => 'pickup'])->json('shipping'));

        // Another customer's address is never used to pick a zone.
        $other = DB::table('customers')->insertGetId(['first_name' => 'O', 'email' => 'other-ship@example.test', 'status' => 'active']);
        $foreign = DB::table('customer_addresses')->insertGetId(['customer_id' => $other, 'recipient_name' => 'O', 'phone' => '0400000000',
            'street_address' => '1 St', 'suburb' => 'Adelaide', 'state' => 'SA', 'postcode' => '5000']);
        $q = $this->postJson('/checkout/quote', ['items' => $this->items(), 'addressId' => $foreign])->json();
        $this->assertSame([12, true], [(int) $q['shipping'], $q['delivery']['addressNeeded']]);
    }

    /* ── COD orders: shipping by zone, no deposit ── */

    public function test_cod_adelaide_order_stores_the_zone_fee_and_snapshot(): void
    {
        $n = $this->place('cod', ['shipping' => 0])->assertCreated()->json('orderNumber');
        $o = $this->order($n);
        $this->assertSame(['5.00', '115.00', 'delivery', 'pending', 'Norwood'], [$o->shipping_total, $o->grand_total, $o->fulfillment_type, $o->payment_status, $o->shipping_suburb]);
        $pay = DB::table('payments')->where('order_id', $o->id)->first();
        $this->assertSame(['115.00', 'pending'], [$pay->amount, $pay->status]);     // COD: full amount on delivery, no deposit
        $this->assertEquals(['fulfillment' => 'delivery', 'method' => 'adelaide', 'label' => 'Adelaide delivery', 'fee' => 5], json_decode($pay->response_data, true)['shipping']);
        $this->assertSame(1, DB::table('order_timeline')->where('order_id', $o->id)->where('note', 'Delivery method: Adelaide delivery (A$5.00)')->count());

        $this->get('/account/orders/'.$n)->assertOk()->assertSee('Shipping (Adelaide delivery)')->assertSee('A$5.00')
            ->assertSee('Delivery Address')->assertDontSee('Remaining balance');
    }

    public function test_cod_rest_of_australia_and_regional_sa_pay_12(): void
    {
        foreach ([$this->regionalSaId, $this->melbourneId] as $i => $addressId) {
            $n = $this->place('cod', ['addressId' => $addressId, 'items' => $this->items(2 + $i)])->assertCreated()->json('orderNumber');
            $o = $this->order($n);
            $this->assertSame('12.00', $o->shipping_total);
            $this->assertSame('Delivery within Australia', CustomerOrders::find($this->customerId, $n)['delivery']['label']);
        }
    }

    /* ── Pickup ── */

    public function test_pickup_order_needs_no_address_and_charges_no_shipping(): void
    {
        DB::table('customer_addresses')->where('customer_id', $this->customerId)->delete();   // no address at all
        $res = $this->postJson('/checkout/place', ['items' => $this->items(), 'paymentMethod' => 'cod', 'fulfillment' => 'pickup',
            'contact' => ['fullName' => 'Ada Collector', 'phone' => '0400 999 888']])->assertCreated();
        $this->assertTrue($res->json('pickup'));
        $o = $this->order($res->json('orderNumber'));
        $this->assertSame(['pickup', '0.00', '110.00', 'Ada Collector', '0400 999 888', 'West Croydon', 'SA', '5008'],
            [$o->fulfillment_type, $o->shipping_total, $o->grand_total, $o->shipping_recipient_name, $o->shipping_phone, $o->shipping_suburb, $o->shipping_state, $o->shipping_postcode]);
        $this->assertSame(1, DB::table('order_timeline')->where('order_id', $o->id)->where('note', 'Delivery method: Free Pickup – West Croydon (free)')->count());

        $page = $this->get('/account/orders/'.$o->order_number)->assertOk();
        $page->assertSee('Shipping (Free Pickup – West Croydon)')->assertSee('Collected by')->assertSee('Ada Collector')->assertDontSee('Delivery Address');

        $mail = (new OrderConfirmationMail(CustomerOrders::find($this->customerId, $o->order_number), 'Ada'))->viewData();
        $this->assertSame('Pickup', $mail['deliveryHeading']);
        $this->assertSame('Pay on pickup', $mail['payment']['status']);
        $this->assertContains(['Shipping (Free Pickup – West Croydon)', 'Free', false], $mail['rows']);
    }

    public function test_pickup_requires_contact_details_and_can_be_switched_off(): void
    {
        $body = ['items' => $this->items(), 'paymentMethod' => 'cod', 'fulfillment' => 'pickup'];
        $this->postJson('/checkout/place', $body)->assertStatus(422)->assertJsonValidationErrors(['contact']);
        $this->postJson('/checkout/place', $body + ['contact' => ['fullName' => 'A', 'phone' => 'call me']])->assertStatus(422)->assertJsonValidationErrors(['contact.phone']);

        \App\Support\StoreConfig::fake(['pickup_enabled' => false]);
        $this->postJson('/checkout/place', $body + ['contact' => ['fullName' => 'A', 'phone' => '0400000000']])->assertStatus(422)->assertJsonValidationErrors(['fulfillment']);
        $this->assertSame(0, DB::table('orders')->where('customer_id', $this->customerId)->count());
    }

    /* ── Card: 30% deposit, never "paid", failed/duplicate handling ── */

    public function test_card_order_charges_exactly_the_30_percent_deposit_and_stays_partially_paid(): void
    {
        $res = $this->place('card', ['deposit' => 0.5, 'shipping' => 0])->assertCreated();
        $this->assertEquals([112.80, 33.84, 78.96], [$res->json('total'), $res->json('payNow'), $res->json('balance')]);
        $o = $this->order($res->json('orderNumber'));
        $this->assertSame(['112.80', '5.00', 'pending'], [$o->grand_total, $o->shipping_total, $o->payment_status]);

        $session = array_values($this->sessions)[0];
        $this->assertSame(3384, $session['amount_total'], 'Stripe is asked for the 30% deposit only');
        $pay = DB::table('payments')->where('order_id', $o->id)->first();
        $this->assertSame(['33.84', 'pending', 'stripe'], [$pay->amount, $pay->status, $pay->provider]);
        $data = json_decode($pay->response_data, true);
        $this->assertSame([30, 70, 'adelaide'], [(int) $data['plan']['depositPercent'], (int) $data['plan']['balancePercent'], $data['shipping']['method']]);

        // Verified payment of the deposit: money recorded, order NOT fully paid.
        $this->webhook('checkout.session.completed', array_merge($session, ['status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_dep']))->assertJson(['outcome' => 'paid']);
        $o = $this->order($o->order_number);
        $this->assertSame('pending', $o->payment_status);
        $this->assertSame(33.84, CardPayments::paidTotal($o->id));
        $this->assertSame('Partially Paid', OrderStatus::paymentLabel($o->payment_status, 'card', $o->order_status, 33.84, 112.80));

        // A second deposit cannot be started.
        $this->postJson("/account/orders/{$o->order_number}/pay")->assertStatus(409);
        $this->assertSame(1, DB::table('payments')->where('order_id', $o->id)->count());

        $page = $this->get('/account/orders/'.$o->order_number)->assertOk();
        $page->assertSee('Deposit paid')->assertSee('A$33.84')->assertSee('A$78.96')->assertSee(OnlinePaymentPlan::balanceTerms(70), false);

        $mail = (new OrderConfirmationMail(CustomerOrders::find($this->customerId, $o->order_number), 'Ada'))->viewData();
        $this->assertSame('Deposit received', $mail['payment']['status']);
        $this->assertContains(OnlinePaymentPlan::balanceTerms(70), $mail['payment']['lines']);
        $this->assertContains(['Shipping (Adelaide delivery)', 'A$5.00', false], $mail['rows']);
    }

    public function test_failed_card_deposit_records_nothing_as_paid(): void
    {
        $n = $this->place('card')->assertCreated()->json('orderNumber');
        $o = $this->order($n);
        $pay = DB::table('payments')->where('order_id', $o->id)->first();
        $pi = ['id' => 'pi_fail', 'metadata' => ['order_number' => $n, 'payment_id' => (string) $pay->id], 'last_payment_error' => ['code' => 'card_declined']];
        $this->webhook('payment_intent.payment_failed', $pi)->assertJson(['outcome' => 'declined']);

        $this->assertSame('failed', $this->order($n)->payment_status);
        $this->assertSame(0.0, CardPayments::paidTotal($o->id));
        // Retrying asks for the same deposit again, never the full total.
        $this->postJson("/account/orders/{$n}/pay")->assertOk();
        $this->assertSame(3384, end($this->sessions)['amount_total']);
    }

    public function test_balance_is_settled_only_when_the_total_is_received(): void
    {
        $n = $this->place('card')->assertCreated()->json('orderNumber');
        $o = $this->order($n);
        $sid = array_key_first($this->sessions);
        $this->webhook('checkout.session.completed', array_merge($this->sessions[$sid], ['status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_a']));
        $this->assertSame('pending', $this->order($n)->payment_status);

        // Balance recorded later (staff / future flow) as its own payment row; the deposit row is untouched.
        $pid = DB::table('payments')->insertGetId(['order_id' => $o->id, 'provider' => 'stripe', 'provider_transaction_id' => 'cs_test_bal', 'payment_method' => 'card', 'amount' => 78.96, 'status' => 'pending']);
        $this->webhook('checkout.session.completed', ['id' => 'cs_test_bal', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 7896, 'currency' => 'aud',
            'payment_intent' => 'pi_b', 'metadata' => ['order_number' => $n, 'payment_id' => (string) $pid]]);
        $this->assertSame('paid', $this->order($n)->payment_status);
        $this->assertSame(112.80, CardPayments::paidTotal($o->id));
        $this->assertSame(['33.84', '78.96'], DB::table('payments')->where('order_id', $o->id)->orderBy('id')->pluck('amount')->all());
    }

    /* ── Pages ── */

    public function test_checkout_page_offers_delivery_and_pickup_and_explains_the_deposit(): void
    {
        $html = $this->get('/checkout')->assertOk()->getContent();
        $this->assertStringContainsString('id="fulfillment-options"', $html);
        $this->assertStringContainsString('Adelaide A$5 · Rest of Australia A$12', $html);
        $this->assertStringContainsString('Free Pickup – West Croydon', $html);
        $this->assertStringContainsString('id="pickup-panel"', $html);
        $this->assertStringContainsString('Pay just 30% now to pre-book your order.', $html);
        $this->assertStringContainsString('Remaining 70% payable later.', $html);
        $this->assertStringContainsString(e(OnlinePaymentPlan::balanceTerms()), $html);

        \App\Support\StoreConfig::fake(['pickup_enabled' => false]);
        $this->assertStringNotContainsString('id="fulfillment-options"', $this->get('/checkout')->getContent());
    }

    public function test_policy_pages_state_the_same_rules(): void
    {
        $pay = $this->get('/payments')->assertOk()->getContent();
        $this->assertStringContainsString('30% deposit', $pay);
        $this->assertStringContainsString('remaining 70%', $pay);
        $this->assertStringContainsString('order total A$103.00, deposit now A$30.90, remaining balance A$72.10', $pay);
        $this->assertStringContainsString('Cash on delivery:', $pay);
        $this->assertStringContainsString('being finalised', $pay);          // refund/cancellation rules not invented

        $terms = $this->get('/terms')->assertOk()->getContent();
        $this->assertStringContainsString('Adelaide delivery A$5, rest of Australia A$12, or free pickup from West Croydon.', $terms);
        $this->assertStringContainsString('30% deposit', $terms);
        $this->assertStringContainsString('/payments', $this->get('/')->getContent());   // footer link
    }
}
