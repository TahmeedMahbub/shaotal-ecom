<?php

namespace Tests\Feature;

use App\Models\CardPayments;
use App\Payments\PaymentMethods;
use App\Payments\Stripe;
use App\Support\StoreConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Stripe Checkout card payments, against an in-memory fake of the Stripe API (Http::fake) and webhooks signed with a
 * test secret. Product 2 (A$55, simple) from sql/local_test_seed.sql: 2 × A$55 = A$110.00 + A$12 delivery.
 * Online payment (pre-booking): A$110.00 - 2% (A$2.20) + A$12.00 = A$119.80 order total; the 30% deposit
 * A$35.94 is all Stripe charges; A$83.86 stays outstanding. COD stays A$122.00.
 */
class StripePaymentTest extends TestCase
{
    use DatabaseTransactions;

    private const WHSEC = 'whsec_test_secret';

    private int $customerId;

    private int $addressId;

    /** @var array<string, array> fake Stripe sessions by id */
    private array $sessions = [];

    /** @var array<int, array> create-session requests received */
    private array $created = [];

    private bool $stripeDown = false;

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
        StoreConfig::fake(['payment_methods' => ['card', 'cod']]);
        Cache::store('file')->flush();
        $this->customerId = DB::table('customers')->insertGetId([
            'first_name' => 'Payer', 'email' => 'payer@example.test', 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $this->addressId = DB::table('customer_addresses')->insertGetId([
            'customer_id' => $this->customerId, 'label' => 'Home', 'recipient_name' => 'Payer', 'phone' => '0400000000',
            'street_address' => '1 Test St', 'suburb' => 'Carlton', 'state' => 'VIC', 'postcode' => '3053', 'is_default' => 1,
        ]);
        $cartId = DB::table('carts')->insertGetId(['customer_id' => $this->customerId, 'status' => 'active']);
        DB::table('cart_items')->insert(['cart_id' => $cartId, 'product_id' => 2, 'quantity' => 2]);
        $this->withSession(['customer_id' => $this->customerId]);
        $this->fakeStripe();
    }

    private function fakeStripe(): void
    {
        Http::fake(function (HttpRequest $req) {
            if ($this->stripeDown) {
                return Http::response(['error' => ['type' => 'api_error']], 500);
            }
            $this->assertSame('Bearer sk_test_abc', $req->header('Authorization')[0]);
            $path = parse_url($req->url(), PHP_URL_PATH);
            if ($req->method() === 'POST' && $path === '/v1/checkout/sessions') {
                $this->assertNotEmpty($req->header('Idempotency-Key')[0] ?? null);
                $data = $req->data();
                $this->created[] = $data;
                $id = 'cs_test_'.count($this->created).bin2hex(random_bytes(4));
                $this->sessions[$id] = [
                    'id' => $id, 'object' => 'checkout.session', 'status' => 'open', 'payment_status' => 'unpaid',
                    'url' => 'https://checkout.stripe.com/c/pay/'.$id, 'expires_at' => (int) $data['expires_at'],
                    'amount_total' => (int) $data['line_items'][0]['price_data']['unit_amount'],
                    'currency' => $data['line_items'][0]['price_data']['currency'], 'metadata' => $data['metadata'],
                ];

                return Http::response($this->sessions[$id]);
            }
            if (preg_match('#^/v1/checkout/sessions/([^/]+)(/expire)?$#', $path, $m) && isset($this->sessions[$m[1]])) {
                if (! empty($m[2])) {
                    if ($this->sessions[$m[1]]['status'] !== 'open') {
                        return Http::response(['error' => ['type' => 'invalid_request_error']], 400);
                    }
                    $this->sessions[$m[1]]['status'] = 'expired';
                }

                return Http::response($this->sessions[$m[1]]);
            }

            return Http::response(['error' => ['type' => 'invalid_request_error']], 404);
        });
    }

    private function placeCard(): array
    {
        $res = $this->postJson('/checkout/place', [
            'items' => [['productId' => 2, 'quantity' => 2, 'price' => 0.01]], 'paymentMethod' => 'card', 'addressId' => $this->addressId,
        ])->assertCreated();

        return $res->json();
    }

    private function order(string $number): object
    {
        return DB::table('orders')->where('order_number', $number)->first();
    }

    private function payments(string $number)
    {
        return DB::table('payments')->where('order_id', $this->order($number)->id)->orderBy('id')->get();
    }

    private function event(string $type, array $object, ?string $id = null, bool $live = false): array
    {
        return ['id' => $id ?? 'evt_'.bin2hex(random_bytes(6)), 'object' => 'event', 'type' => $type, 'livemode' => $live, 'data' => ['object' => $object]];
    }

    private function completed(string $sessionId, array $overrides = []): array
    {
        return array_merge($this->sessions[$sessionId], ['status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_test_1'], $overrides);
    }

    private function sendWebhook(array $event, ?string $secret = null, ?int $t = null)
    {
        $payload = json_encode($event);

        return $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => Stripe::signatureHeader($payload, $secret ?? self::WHSEC, $t),
        ], $payload);
    }

    private function sessionOf(string $number): string
    {
        return (string) $this->payments($number)->last()->provider_transaction_id;
    }

    public function test_card_is_offered_only_when_keys_are_complete_and_match_the_mode(): void
    {
        $this->assertSame(['card', 'cod'], PaymentMethods::enabled());
        config(['services.stripe.secret' => 'sk_live_abc']);
        $this->assertSame(['cod'], PaymentMethods::enabled(), 'a live key in test mode must not enable card');
        config(['services.stripe.secret' => 'sk_test_abc', 'services.stripe.webhook_secret' => '']);
        $this->assertSame(['cod'], PaymentMethods::enabled());
        config(['services.stripe.webhook_secret' => self::WHSEC, 'services.stripe.enabled' => false]);
        $this->assertSame(['cod'], PaymentMethods::enabled());
        $this->postJson('/checkout/place', [
            'items' => [['productId' => 2, 'quantity' => 1]], 'paymentMethod' => 'card', 'addressId' => $this->addressId,
        ])->assertStatus(422);
    }

    public function test_amounts_convert_to_cents_without_float_errors(): void
    {
        $this->assertSame(12200, Stripe::cents('122.00'));
        $this->assertSame(1999, Stripe::cents('19.99'));
        $this->assertSame(1950, Stripe::cents('19.5'));
        $this->assertSame(1, Stripe::cents('0.01'));
        $this->assertSame(29, Stripe::cents(0.29));
    }

    public function test_card_order_creates_a_pending_order_and_a_checkout_session_for_the_server_total(): void
    {
        $res = $this->placeCard();
        $order = $this->order($res['orderNumber']);

        $this->assertStringStartsWith('https://checkout.stripe.com/', $res['redirectUrl']);
        $this->assertSame('119.80', $order->grand_total);
        $this->assertSame('2.20', $order->discount_total);
        $this->assertSame(['card', 'pending', 'pending'], [$order->payment_method, $order->payment_status, $order->order_status]);
        $pay = $this->payments($res['orderNumber']);
        $this->assertCount(1, $pay);
        $this->assertSame(['stripe', 'card', 'pending', '35.94'], [$pay[0]->provider, $pay[0]->payment_method, $pay[0]->status, $pay[0]->amount]);   // deposit only
        $this->assertStringStartsWith('cs_test_', $pay[0]->provider_transaction_id);

        $sent = $this->created[0];
        $this->assertSame(3594, (int) $sent['line_items'][0]['price_data']['unit_amount']);   // the 30% deposit, not the client's 0.01 nor the full total
        $this->assertSame('aud', $sent['line_items'][0]['price_data']['currency']);
        $this->assertSame($res['orderNumber'], $sent['metadata']['order_number']);
        $this->assertSame((string) $pay[0]->id, $sent['metadata']['payment_id']);
        $this->assertSame('https://shop.example.test/checkout/payment/success?session_id={CHECKOUT_SESSION_ID}', $sent['success_url']);
        // The bag is kept until payment is confirmed.
        $this->assertSame(1, DB::table('carts')->where('customer_id', $this->customerId)->where('status', 'active')->count());
    }

    public function test_verified_completed_webhook_records_the_deposit_once_and_is_idempotent(): void
    {
        $n = $this->placeCard()['orderNumber'];
        $sid = $this->sessionOf($n);
        $event = $this->event('checkout.session.completed', $this->completed($sid), 'evt_paid_1');

        $this->sendWebhook($event)->assertOk()->assertJson(['outcome' => 'paid']);
        $this->sendWebhook($event)->assertOk()->assertJson(['outcome' => 'duplicate']);
        // Same session confirmed by a different event (e.g. Stripe retry with new id, or async succeeded).
        $this->sendWebhook($this->event('checkout.session.async_payment_succeeded', $this->completed($sid)))->assertOk()->assertJson(['outcome' => 'already_paid']);

        $order = $this->order($n);
        $this->assertSame('pending', $order->payment_status, 'a 30% deposit must never make the order fully paid');
        $this->assertSame('pending', $order->order_status);   // fulfilment stays with staff
        $pay = $this->payments($n)->last();
        $this->assertSame('paid', $pay->status);
        $this->assertSame('35.94', $pay->amount);
        $this->assertNotNull($pay->paid_at);
        $this->assertSame(35.94, CardPayments::paidTotal($order->id));
        $this->assertSame(1, DB::table('order_timeline')->where('order_id', $order->id)->where('note', 'like', 'Pre-booking deposit of A$35.94 received%balance of A$83.86%')->count());
        $this->assertSame(0, DB::table('carts')->where('customer_id', $this->customerId)->where('status', 'active')->count());
        $this->assertStringNotContainsString('payer@example.test', (string) $pay->response_data);
    }

    public function test_invalid_signatures_and_stale_timestamps_are_rejected_without_changes(): void
    {
        $n = $this->placeCard()['orderNumber'];
        $event = $this->event('checkout.session.completed', $this->completed($this->sessionOf($n)));

        $this->sendWebhook($event, 'whsec_wrong')->assertStatus(400);
        $this->sendWebhook($event, null, time() - 3600)->assertStatus(400);
        $this->call('POST', '/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($event))->assertStatus(400);
        $this->assertSame('pending', $this->order($n)->payment_status);
    }

    public function test_amount_or_mode_mismatch_never_marks_paid(): void
    {
        $n = $this->placeCard()['orderNumber'];
        $sid = $this->sessionOf($n);

        $this->sendWebhook($this->event('checkout.session.completed', $this->completed($sid, ['amount_total' => 100])))->assertJson(['outcome' => 'amount_mismatch']);
        $this->sendWebhook($this->event('checkout.session.completed', $this->completed($sid, ['currency' => 'usd'])))->assertJson(['outcome' => 'amount_mismatch']);
        $this->sendWebhook($this->event('checkout.session.completed', $this->completed($sid), null, true))->assertJson(['outcome' => 'mode_mismatch']);
        $this->sendWebhook($this->event('checkout.session.completed', $this->completed($sid, ['metadata' => ['order_number' => 'INV-000000']])))->assertJson(['outcome' => 'order_mismatch']);
        $this->sendWebhook($this->event('checkout.session.completed', $this->completed($sid, ['id' => 'cs_test_unknown_x', 'metadata' => []])))
            ->assertOk()->assertJson(['outcome' => 'unknown_payment']);

        $this->assertSame('pending', $this->order($n)->payment_status);
        $this->assertSame('pending', $this->payments($n)->last()->status);
    }

    /** Saved-bag state: [active carts, items in them]. */
    private function bag(): array
    {
        $carts = DB::table('carts')->where('customer_id', $this->customerId)->where('status', 'active')->pluck('id');

        return [$carts->count(), DB::table('cart_items')->whereIn('cart_id', $carts)->count()];
    }

    public function test_declined_card_then_success_in_the_same_session(): void
    {
        $n = $this->placeCard()['orderNumber'];
        $pay = $this->payments($n)->last();
        $pi = ['id' => 'pi_test_1', 'object' => 'payment_intent', 'metadata' => ['order_number' => $n, 'payment_id' => (string) $pay->id],
            'last_payment_error' => ['code' => 'card_declined', 'decline_code' => 'generic_decline']];

        $this->sendWebhook($this->event('payment_intent.payment_failed', $pi))->assertJson(['outcome' => 'declined']);
        $this->assertSame('failed', $this->order($n)->payment_status);
        $this->assertSame('generic_decline', json_decode($this->payments($n)->last()->response_data, true)['last_error']);
        $this->assertSame([1, 1], $this->bag(), 'a declined card must leave the bag untouched');

        $this->sendWebhook($this->event('checkout.session.completed', $this->completed($pay->provider_transaction_id)))->assertJson(['outcome' => 'paid']);
        $this->assertSame('pending', $this->order($n)->payment_status, 'deposit received: pre-booked, not paid');
        $this->assertSame(35.94, CardPayments::paidTotal($this->order($n)->id));
        $this->assertSame([0, 0], $this->bag());
    }

    public function test_card_bag_is_kept_until_the_verified_webhook_and_survives_return_pages_and_cancel(): void
    {
        $this->assertSame([1, 1], $this->bag());
        $n = $this->placeCard()['orderNumber'];
        $sid = $this->sessionOf($n);
        $this->assertSame([1, 1], $this->bag(), 'placing a card order must not clear the bag');

        // Customer returns from Stripe (even "complete") or cancels on the hosted page: display only, bag stays.
        $this->get('/checkout/payment/cancel?order='.$n)->assertOk();
        $this->get('/checkout/payment/success?session_id='.$sid)->assertOk();
        $this->sessions[$sid]['status'] = 'complete';
        $this->get('/checkout/payment/success?session_id='.$sid)->assertOk();
        $this->assertSame([1, 1], $this->bag(), 'return pages never clear the bag');

        // A forged / unsigned webhook is rejected and changes nothing.
        $this->postJson('/webhooks/stripe', $this->event('checkout.session.completed', $this->completed($sid)))->assertStatus(400);
        $this->assertSame([1, 1], $this->bag());

        $this->sendWebhook($this->event('checkout.session.completed', $this->completed($sid)))->assertJson(['outcome' => 'paid']);
        $this->assertSame([0, 0], $this->bag(), 'the verified webhook converts the bag');
    }

    public function test_cancelled_card_order_keeps_the_bag(): void
    {
        $n = $this->placeCard()['orderNumber'];
        $this->postJson("/account/orders/{$n}/cancel")->assertOk();
        $this->assertSame([1, 1], $this->bag());
    }

    public function test_failed_card_order_creation_leaves_the_bag_unchanged(): void
    {
        $res = $this->postJson('/checkout/place', [
            'items' => [['productId' => 5, 'quantity' => 1]], 'paymentMethod' => 'card', 'addressId' => $this->addressId,
        ])->assertStatus(422);
        $this->assertSame(0, DB::table('orders')->where('customer_id', $this->customerId)->count());
        $this->assertSame([1, 1], $this->bag());
    }

    public function test_async_payment_stays_pending_until_it_settles(): void
    {
        $n = $this->placeCard()['orderNumber'];
        $sid = $this->sessionOf($n);
        $this->sendWebhook($this->event('checkout.session.completed', $this->completed($sid, ['payment_status' => 'unpaid'])))->assertJson(['outcome' => 'awaiting_async']);
        $this->assertSame('pending', $this->order($n)->payment_status);
        $this->sendWebhook($this->event('checkout.session.async_payment_failed', $this->completed($sid, ['payment_status' => 'unpaid'])))->assertJson(['outcome' => 'failed']);
        $this->assertSame('failed', $this->order($n)->payment_status);
    }

    public function test_expired_session_cancels_the_unpaid_order_and_releases_nothing_else(): void
    {
        $n = $this->placeCard()['orderNumber'];
        $sid = $this->sessionOf($n);
        $this->sendWebhook($this->event('checkout.session.expired', array_merge($this->sessions[$sid], ['status' => 'expired'])))->assertJson(['outcome' => 'expired_order_cancelled']);

        $order = $this->order($n);
        $this->assertSame(['cancelled', 'pending'], [$order->order_status, $order->payment_status]);
        $this->assertSame('cancelled', $this->payments($n)->last()->status);
        // A late "completed" for that session would still be recorded (money received) and flagged for a refund.
        $this->sendWebhook($this->event('checkout.session.completed', $this->completed($sid)))->assertJson(['outcome' => 'paid']);
        $this->assertSame(1, DB::table('order_timeline')->where('order_id', $order->id)->where('note', 'like', '%after the order was cancelled%')->count());
    }

    public function test_retry_reuses_the_open_session_and_never_creates_a_parallel_one(): void
    {
        $res = $this->placeCard();
        $n = $res['orderNumber'];

        $again = $this->postJson("/account/orders/{$n}/pay")->assertOk()->json('redirectUrl');
        $this->assertSame($res['redirectUrl'], $again);
        $this->assertCount(1, $this->created);
        $this->assertCount(1, $this->payments($n));

        // Session about to expire: it is expired at Stripe first, then a new attempt is made.
        $old = $this->sessionOf($n);
        $this->sessions[$old]['expires_at'] = time() + 60;
        $new = $this->postJson("/account/orders/{$n}/pay")->assertOk()->json('redirectUrl');
        $this->assertNotSame($res['redirectUrl'], $new);
        $this->assertSame('expired', $this->sessions[$old]['status']);
        $this->assertSame(['cancelled', 'pending'], $this->payments($n)->pluck('status')->all());

        // The old session's "expired" webhook must not cancel the order: a newer attempt is open.
        $this->sendWebhook($this->event('checkout.session.expired', $this->sessions[$old]))->assertJson(['outcome' => 'no_change']);
        $this->assertSame('pending', $this->order($n)->order_status);
    }

    public function test_no_new_payment_when_one_is_complete_or_the_order_is_paid(): void
    {
        $n = $this->placeCard()['orderNumber'];
        $sid = $this->sessionOf($n);
        $this->sessions[$sid]['status'] = 'complete';   // paid at Stripe, webhook not arrived yet
        $this->postJson("/account/orders/{$n}/pay")->assertStatus(409);
        $this->assertCount(1, $this->created);

        $this->sendWebhook($this->event('checkout.session.completed', $this->completed($sid)))->assertJson(['outcome' => 'paid']);
        $this->postJson("/account/orders/{$n}/pay")->assertStatus(409);
        $this->postJson("/account/orders/{$n}/cancel")->assertStatus(422);
        $this->assertSame('pending', $this->order($n)->order_status);
    }

    public function test_customer_cancel_expires_the_open_session_first(): void
    {
        $n = $this->placeCard()['orderNumber'];
        $sid = $this->sessionOf($n);
        $this->postJson("/account/orders/{$n}/cancel")->assertOk();
        $this->assertSame('expired', $this->sessions[$sid]['status']);
        $this->assertSame('cancelled', $this->order($n)->order_status);
        $this->assertSame('cancelled', $this->payments($n)->last()->status);
        $this->postJson("/account/orders/{$n}/pay")->assertStatus(422);
    }

    public function test_stripe_outage_keeps_one_order_that_can_be_paid_later(): void
    {
        $this->stripeDown = true;
        $res = $this->placeCard();
        $this->assertArrayNotHasKey('redirectUrl', $res);
        $this->assertNotEmpty($res['paymentError']);
        $this->assertSame('/account/orders/'.$res['orderNumber'], $res['orderUrl']);
        $this->assertNull($this->payments($res['orderNumber'])->last()->provider_transaction_id);

        // Double click / network retry of the same request: same order, not a second one.
        $this->postJson('/checkout/place', [
            'items' => [['productId' => 2, 'quantity' => 2, 'price' => 0.01]], 'paymentMethod' => 'card', 'addressId' => $this->addressId,
        ])->assertOk()->assertJson(['duplicate' => true, 'orderNumber' => $res['orderNumber']]);
        $this->assertSame(1, DB::table('orders')->where('customer_id', $this->customerId)->count());

        $this->stripeDown = false;
        $this->postJson("/account/orders/{$res['orderNumber']}/pay")->assertOk()->assertJsonStructure(['redirectUrl']);
        $this->assertCount(1, $this->payments($res['orderNumber']));   // the placement row got the session
    }

    public function test_return_pages_only_display_state_and_are_customer_scoped(): void
    {
        $n = $this->placeCard()['orderNumber'];
        $sid = $this->sessionOf($n);

        $this->get('/checkout/payment/success?session_id='.$sid)->assertOk()->assertSee('Payment not completed');
        $this->sessions[$sid]['status'] = 'complete';
        $this->get('/checkout/payment/success?session_id='.$sid)->assertOk()->assertSee('Confirming your payment');
        $this->assertSame('pending', $this->order($n)->payment_status, 'the redirect must never mark the order paid');

        $this->sendWebhook($this->event('checkout.session.completed', $this->completed($sid)));
        // Payment recorded: the return goes to the checkout success modal (the one COD uses), on every visit.
        $this->get('/checkout/payment/success?session_id='.$sid)->assertRedirect('/checkout?paid='.$n);
        $this->getJson("/account/orders/{$n}/payment-status")->assertOk()->assertJson(['paymentStatus' => 'pending', 'received' => true, 'label' => 'Partially Paid']);
        $this->get("/account/orders/{$n}")->assertOk()->assertSee('Payment history')->assertDontSee($sid);

        $other = DB::table('customers')->insertGetId(['first_name' => 'Other', 'email' => 'other@example.test', 'status' => 'active', 'email_verified_at' => now()]);
        $this->withSession(['customer_id' => $other]);
        $this->get('/checkout/payment/success?session_id='.$sid)->assertNotFound();
        $this->get('/checkout/payment/cancel?order='.$n)->assertNotFound();
        $this->getJson("/account/orders/{$n}/payment-status")->assertNotFound();
        $this->postJson("/account/orders/{$n}/pay")->assertNotFound();
    }

    /* ── Success return: verified with Stripe server-side, then the COD success modal ── */

    public function test_success_return_verifies_with_stripe_and_opens_the_cod_success_modal_without_waiting_for_the_webhook(): void
    {
        $n = $this->placeCard()['orderNumber'];
        $sid = $this->sessionOf($n);
        $grand = (float) $this->order($n)->grand_total;
        $deposit = (float) $this->payments($n)->last()->amount;
        $this->sessions[$sid] = $this->completed($sid);   // the customer paid on Stripe; the webhook has not arrived

        $this->get('/checkout/payment/success?session_id='.$sid)->assertRedirect('/checkout?paid='.$n);
        $order = $this->order($n);
        $this->assertSame('pending', $order->payment_status, 'a deposit is never full payment');
        $this->assertSame($deposit, CardPayments::paidTotal($order->id));
        $this->assertSame([0, 0], $this->bag(), 'confirmed by Stripe: the saved bag became this order');

        $page = $this->get('/checkout?paid='.$n)->assertOk();
        $placed = json_decode((string) preg_replace('#.*<script type="application/json" id="checkout-placed">(.*?)</script>.*#s', '$1', $page->getContent()), true);
        $this->assertSame([
            'orderNumber' => $n, 'orderUrl' => '/account/orders/'.$n, 'pickup' => false,
            'address' => 'Payer, 1 Test St, Carlton VIC 3053', 'total' => $grand, 'paid' => $deposit,
            'balance' => round($grand - $deposit, 2), 'paymentStatus' => 'Partially Paid',
        ], $placed);
        $page->assertSee('id="success-modal-overlay"', false)->assertSee('Order Placed Successfully!')->assertDontSee('Confirming your payment');

        // Refresh / repeated visit: same modal, nothing recorded twice.
        $this->get('/checkout/payment/success?session_id='.$sid)->assertRedirect('/checkout?paid='.$n);
        // The real webhook arrives afterwards: idempotent, no second payment, timeline note or email.
        $this->sendWebhook($this->event('checkout.session.completed', $this->completed($sid)))->assertJson(['outcome' => 'already_paid']);
        $this->assertSame($deposit, CardPayments::paidTotal($order->id));
        $this->assertSame(1, DB::table('payments')->where('order_id', $order->id)->where('status', 'paid')->count());
        $this->assertSame(1, DB::table('order_timeline')->where('order_id', $order->id)->where('note', 'like', 'Pre-booking deposit%')->count());
    }

    public function test_success_return_never_records_payment_stripe_has_not_confirmed(): void
    {
        $n = $this->placeCard()['orderNumber'];
        $sid = $this->sessionOf($n);

        // Stripe unreachable: cannot verify, so nothing is recorded and the honest "confirming" page is shown.
        $this->sessions[$sid] = $this->completed($sid);
        $this->stripeDown = true;
        $this->get('/checkout/payment/success?session_id='.$sid)->assertOk()->assertSee('Confirming your payment');
        $this->assertSame(0.0, CardPayments::paidTotal($this->order($n)->id));
        $this->stripeDown = false;

        // Stripe reports a different amount than this order asked for: not recorded (staff review).
        $this->sessions[$sid] = $this->completed($sid, ['amount_total' => 1]);
        $this->get('/checkout/payment/success?session_id='.$sid)->assertOk()->assertSee('Confirming your payment');
        $this->assertSame(0.0, CardPayments::paidTotal($this->order($n)->id));

        // ?paid= on its own shows nothing: no Stripe-confirmed payment, another customer's order, or a COD order.
        $this->get('/checkout?paid='.$n)->assertOk()->assertDontSee('id="checkout-placed"', false);
        DB::table('orders')->where('order_number', $n)->update(['order_status' => 'cancelled']);   // free the bag for a new order
        $n = $this->postJson('/checkout/place', [   // a different bag than the first order (not a double submit)
            'items' => [['productId' => 2, 'quantity' => 1, 'price' => 0.01]], 'paymentMethod' => 'card', 'addressId' => $this->addressId,
        ])->assertCreated()->json('orderNumber');
        $sid = $this->sessionOf($n);
        $this->sessions[$sid] = $this->completed($sid);
        $this->get('/checkout/payment/success?session_id='.$sid)->assertRedirect('/checkout?paid='.$n);
        $this->get('/checkout?paid='.$n)->assertOk()->assertSee('id="checkout-placed"', false);
        $other = DB::table('customers')->insertGetId(['first_name' => 'Other', 'email' => 'other2@example.test', 'status' => 'active', 'email_verified_at' => now()]);
        $this->withSession(['customer_id' => $other])->get('/checkout?paid='.$n)->assertOk()->assertDontSee('id="checkout-placed"', false);
        $this->withSession(['customer_id' => $this->customerId]);
        DB::table('carts')->where('customer_id', $this->customerId)->update(['status' => 'active']);
        $cod = $this->postJson('/checkout/place', [
            'items' => [['productId' => 2, 'quantity' => 3, 'price' => 0.01]], 'paymentMethod' => 'cod', 'addressId' => $this->addressId,
        ])->assertCreated()->json();
        $this->assertArrayNotHasKey('paid', $cod, 'COD place-order response unchanged');
        $this->get('/checkout?paid='.$cod['orderNumber'])->assertOk()->assertDontSee('id="checkout-placed"', false);
    }

    public function test_cancel_page_offers_retry_for_an_unpaid_order(): void
    {
        $n = $this->placeCard()['orderNumber'];
        $this->get('/checkout/payment/cancel?order='.$n)->assertOk()->assertSee('Payment cancelled')->assertSee('id="payment-retry-btn"', false);
        $this->get("/account/orders/{$n}")->assertOk()->assertSee('id="order-pay-btn"', false);
    }

    public function test_cod_is_unchanged_with_stripe_enabled(): void
    {
        $res = $this->postJson('/checkout/place', [
            'items' => [['productId' => 2, 'quantity' => 2]], 'paymentMethod' => 'cod', 'addressId' => $this->addressId,
        ])->assertCreated();
        $this->assertArrayNotHasKey('redirectUrl', $res->json());
        $order = $this->order($res->json('orderNumber'));
        $this->assertSame(['cod', 'pending', '122.00'], [$order->payment_method, $order->payment_status, $order->grand_total]);
        $pay = $this->payments($res->json('orderNumber'))->last();
        $this->assertNull($pay->provider);
        $this->assertSame([], $this->created);
        $this->assertSame([0, 0], $this->bag(), 'COD clears the saved bag at placement');
        $this->assertFalse(CardPayments::canPay('cod', 'pending', 'pending'));
        $this->get('/checkout')->assertOk()->assertSee('id="pay-card"', false)->assertSee('id="pay-cod"', false);
    }

    public function test_webhook_route_needs_no_session_or_csrf(): void
    {
        $n = $this->placeCard()['orderNumber'];
        $this->flushSession();
        $res = $this->sendWebhook($this->event('checkout.session.completed', $this->completed($this->sessionOf($n))));
        $res->assertOk();
        $this->assertEmpty($res->headers->getCookies());
    }
}
