<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmationMail;
use App\Models\CardPayments;
use App\Models\CustomerOrders;
use App\Payments\Stripe;
use App\Support\OrderEmails;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Carbon;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Payment-start failures, gift validation/fees, and the order confirmation email (content, queueing, duplicates,
 * failure + retry). Against a fake Stripe that, like the real one, answers a reused Idempotency-Key with DIFFERENT
 * parameters with HTTP 400 idempotency_error. Product 2 is a simple A$55 item (sql/local_test_seed.sql).
 */
class OrderFlowReliabilityTest extends TestCase
{
    use DatabaseTransactions;

    private const WHSEC = 'whsec_test_secret';

    private int $customerId;

    private int $addressId;

    private array $sessions = [];

    /** @var array<string, string> idempotency key => request hash (the real Stripe rejects a key reused with other params) */
    private array $keys = [];

    private array $createKeys = [];

    /** 'none' | 'connection' (timeout / DNS) | 'server' (HTTP 500), applied to session creation only */
    private string $createFails = 'none';

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
            'storefront.order_mail_queue' => 'sync',
            'app.url' => 'https://shop.example.test',
        ]);
        Cache::store('file')->flush();
        $this->customerId = DB::table('customers')->insertGetId(['first_name' => 'Payer', 'email' => 'payer@example.test', 'status' => 'active', 'email_verified_at' => now()]);
        $this->addressId = DB::table('customer_addresses')->insertGetId([
            'customer_id' => $this->customerId, 'label' => 'Home', 'recipient_name' => 'Payer', 'phone' => '0400000000',
            'street_address' => '1 Test St', 'suburb' => 'Carlton', 'state' => 'VIC', 'postcode' => '3053', 'is_default' => 1,
        ]);
        $this->withSession(['customer_id' => $this->customerId]);
        $this->fakeStripe();
    }

    private function fakeStripe(): void
    {
        Http::fake(function (HttpRequest $req) {
            $path = parse_url($req->url(), PHP_URL_PATH);
            if ($req->method() === 'POST' && $path === '/v1/checkout/sessions') {
                if ($this->createFails === 'connection') {
                    throw new ConnectionException('cURL error 28: timed out');
                }
                if ($this->createFails === 'server') {
                    return Http::response(['error' => ['type' => 'api_error']], 500);
                }
                $key = $req->header('Idempotency-Key')[0] ?? '';
                $this->createKeys[] = $key;
                $hash = md5(json_encode($req->data()));
                if (isset($this->keys[$key]) && $this->keys[$key] !== $hash) {
                    return Http::response(['error' => ['type' => 'idempotency_error', 'code' => 'idempotency_key_in_use']], 400);
                }
                $this->keys[$key] = $hash;
                $data = $req->data();
                $id = 'cs_test_'.(count($this->sessions) + 1).bin2hex(random_bytes(4));
                $this->sessions[$id] = [
                    'id' => $id, 'status' => 'open', 'payment_status' => 'unpaid', 'url' => 'https://checkout.stripe.com/c/pay/'.$id,
                    'expires_at' => (int) $data['expires_at'], 'amount_total' => (int) $data['line_items'][0]['price_data']['unit_amount'],
                    'currency' => 'aud', 'metadata' => $data['metadata'],
                ];

                return Http::response($this->sessions[$id]);
            }
            if (preg_match('#^/v1/checkout/sessions/([^/]+)(/expire)?$#', $path, $m) && isset($this->sessions[$m[1]])) {
                if (! empty($m[2])) {
                    $this->sessions[$m[1]]['status'] = 'expired';
                }

                return Http::response($this->sessions[$m[1]]);
            }

            return Http::response(['error' => ['type' => 'invalid_request_error']], 404);
        });
    }

    private function body(int $qty = 2, ?array $gift = null, string $method = 'cod'): array
    {
        return ['items' => [['productId' => 2, 'quantity' => $qty]], 'paymentMethod' => $method, 'addressId' => $this->addressId, 'gift' => $gift];
    }

    private function gift(int $qty, string $message = ''): array
    {
        return ['enabled' => true, 'message' => $message, 'items' => [['productId' => 2, 'quantity' => $qty]]];
    }

    private function orderRow(string $number): object
    {
        return DB::table('orders')->where('order_number', $number)->first();
    }

    private function fillBag(): int
    {
        $cart = DB::table('carts')->insertGetId(['customer_id' => $this->customerId, 'status' => 'active']);
        DB::table('cart_items')->insert(['cart_id' => $cart, 'product_id' => 2, 'quantity' => 2]);

        return $cart;
    }

    private function bagCount(): int
    {
        return DB::table('cart_items as ci')->join('carts as c', 'c.id', '=', 'ci.cart_id')->where('c.customer_id', $this->customerId)->where('c.status', 'active')->count();
    }

    private function webhookPaid(string $sessionId, string $number, ?string $eventId = null)
    {
        $s = $this->sessions[$sessionId];
        $payload = json_encode(['id' => $eventId ?? 'evt_'.bin2hex(random_bytes(6)), 'type' => 'checkout.session.completed', 'livemode' => false, 'data' => ['object' => [
            'id' => $sessionId, 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => $s['amount_total'], 'currency' => 'aud',
            'metadata' => $s['metadata'], 'payment_intent' => 'pi_x',
        ]]]);

        return $this->call('POST', '/webhooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => Stripe::signatureHeader($payload, self::WHSEC), 'CONTENT_TYPE' => 'application/json'], $payload);
    }

    // ── Payment start failures ────────────────────────────────────────────────────────────────────────────

    public function test_a_stripe_timeout_after_the_order_is_saved_is_handled_and_the_retry_gets_a_fresh_idempotency_key(): void
    {
        $this->fillBag();
        $this->createFails = 'connection';   // before the fix: ConnectionException → HTTP 500 after the order was committed
        $res = $this->postJson('/checkout/place', $this->body(2, null, 'card'))->assertCreated();
        $number = $res->json('orderNumber');
        $this->assertNotEmpty($res->json('paymentError'));
        $this->assertNull($res->json('redirectUrl'));
        $this->assertSame(1, DB::table('orders')->where('customer_id', $this->customerId)->count(), 'one order, not a duplicate');
        $this->assertSame(1, $this->bagCount(), 'the bag is kept while payment is not started');

        // The placement payment row has no session yet, so "Pay now" reuses it. Its first attempt may have created a session
        // at Stripe whose response was lost: seed the key the failed attempt would have used, with different parameters.
        $pid = DB::table('payments')->where('order_id', $this->orderRow($number)->id)->value('id');
        $this->keys['shaotal-payment-'.$pid] = 'hash-of-the-first-attempt';
        $this->createFails = 'none';
        $this->postJson("/account/orders/$number/pay")->assertOk()->assertJsonStructure(['redirectUrl']);
        $this->assertNotContains('shaotal-payment-'.$pid, $this->createKeys, 'the old row-id-only key must never be reused');
        $this->assertCount(1, $this->createKeys);
    }

    public function test_the_same_payment_row_never_sends_the_same_idempotency_key_with_different_parameters(): void
    {
        $this->fillBag();
        $number = $this->postJson('/checkout/place', $this->body(2, null, 'card'))->assertCreated()->json('orderNumber');
        // Force a second create on the same row (its session id is cleared, as if the first response was lost).
        DB::table('payments')->where('order_id', $this->orderRow($number)->id)->update(['provider_transaction_id' => null]);
        config(['services.stripe.session_minutes' => 61]);   // expires_at differs → the request parameters differ (as they do with time)
        $this->postJson("/account/orders/$number/pay")->assertOk();
        $this->assertCount(2, $this->createKeys);
        $this->assertCount(2, array_unique($this->createKeys), 'a changed request must not reuse the key (Stripe: idempotency_error)');
    }

    public function test_a_stripe_server_error_keeps_one_order_and_the_bag(): void
    {
        $this->fillBag();
        $this->createFails = 'server';
        $res = $this->postJson('/checkout/place', $this->body(2, null, 'card'))->assertCreated();
        $this->assertNotEmpty($res->json('paymentError'));
        $this->assertSame(1, $this->bagCount());
        // Same request again (double click / retry): the saved order is returned, never a second order.
        $this->postJson('/checkout/place', $this->body(2, null, 'card'))->assertOk()->assertJson(['duplicate' => true]);
        $this->assertSame(1, DB::table('orders')->where('customer_id', $this->customerId)->count());
    }

    public function test_reconcile_applies_a_verified_payment_when_the_webhook_never_arrived(): void
    {
        Mail::fake();
        $cart = $this->fillBag();
        $number = $this->postJson('/checkout/place', $this->body(2, null, 'card'))->assertCreated()->json('orderNumber');
        $sid = array_key_last($this->sessions);
        $this->assertSame(1, $this->bagCount());

        Carbon::setTestNow(now()->addMinutes(5));
        $this->assertSame(['still_open' => 1], CardPayments::reconcilePending(), 'an unpaid session changes nothing');
        $this->assertSame(1, $this->bagCount());

        $this->sessions[$sid]['status'] = 'complete';
        $this->sessions[$sid]['payment_status'] = 'paid';
        $this->assertSame(['paid' => 1], CardPayments::reconcilePending());
        $this->assertSame(0, $this->bagCount(), 'cleared only after the verified payment');
        $this->assertSame('converted', DB::table('carts')->where('id', $cart)->value('status'));
        $this->assertSame('pending', $this->orderRow($number)->payment_status, 'a 20% deposit never marks the order fully paid');
        $this->assertSame([], CardPayments::reconcilePending(), 'a paid payment is not asked about again');
        Mail::assertSent(OrderConfirmationMail::class, 1);
        Carbon::setTestNow();
    }

    // ── Gift selection ────────────────────────────────────────────────────────────────────────────────────

    public function test_gift_fee_follows_the_agreed_rule_for_one_to_four_selected_items(): void
    {
        foreach ([[1, 1.99], [2, 1.99], [3, 2.99], [4, 3.99]] as [$n, $fee]) {
            $q = $this->postJson('/checkout/quote', $this->body(4, $this->gift($n)))->assertOk();
            $this->assertEquals($fee, $q->json('giftFee'), "$n wrapped");
            $this->assertEquals($n, $q->json('gift.quantity'));
            $this->assertEquals(220 + $fee + 0, $q->json('total') - $q->json('shipping'), 'fee is a separate charge on top of the goods');
        }
    }

    public function test_a_gift_selection_that_is_not_in_the_cart_is_refused_when_placing(): void
    {
        $tooMany = $this->body(2, $this->gift(3));                                     // 3 wrapped, 2 in the cart
        $this->postJson('/checkout/place', $tooMany)->assertStatus(422)->assertJsonPath('errors.gift.0', fn ($m) => str_contains($m, 'no longer matches'));
        $notInCart = $this->body(2, ['enabled' => true, 'message' => '', 'items' => [['productId' => 1, 'color' => '#5C6B4F', 'size' => 'M', 'quantity' => 1]]]);
        $this->postJson('/checkout/place', $notInCart)->assertStatus(422);
        $this->assertSame(0, DB::table('orders')->where('customer_id', $this->customerId)->count());
        $this->assertNotNull($this->postJson('/checkout/quote', $tooMany)->json('giftError'));
    }

    public function test_non_gift_order_is_unchanged(): void
    {
        $n = $this->postJson('/checkout/place', $this->body(2))->assertCreated()->json('orderNumber');
        $this->assertNull($this->orderRow($n)->customer_note);
        $this->assertSame(0, DB::table('order_items')->where('order_id', $this->orderRow($n)->id)->where('sku', 'GIFT-WRAP')->count());
    }

    // ── Order confirmation email ──────────────────────────────────────────────────────────────────────────

    public function test_cod_order_sends_one_confirmation_with_gift_details_and_no_internal_data(): void
    {
        Mail::fake();
        $n = $this->postJson('/checkout/place', $this->body(3, $this->gift(3, "Happy birthday!\nLove, A")))->assertCreated()->json('orderNumber');
        Mail::assertSent(OrderConfirmationMail::class, 1);
        Mail::assertSent(OrderConfirmationMail::class, fn ($m) => $m->hasTo('payer@example.test') && str_contains($m->envelope()->subject, $n));

        $mail = new OrderConfirmationMail(CustomerOrders::find($this->customerId, $n), 'Payer');
        $html = $mail->render();
        $text = view('emails.order-confirmation-text', $mail->viewData())->render();
        foreach ([$html, $text] as $body) {
            $this->assertStringContainsString($n, $body);
            $this->assertStringContainsString('Nakshi Kantha Cushion', $body);
            $this->assertStringContainsString('A$2.99', $body);                  // 3 wrapped = A$2.99, itemised
            $this->assertStringContainsString('Happy birthday!', $body);
            $this->assertStringContainsString('A$165.00', $body);                  // goods subtotal, without the wrapping fee
            $this->assertStringContainsString('A$179.99', $body);                  // 165.00 + 2.99 gift wrap + 12.00 shipping
            $this->assertStringContainsString('Pay on delivery', $body);
            $this->assertStringNotContainsString('cs_', $body);
            $this->assertStringNotContainsString('pi_', $body);
            $this->assertStringNotContainsString('stripe', strtolower($body));
        }
        $this->assertStringContainsString('This order is a gift', $html);
        $this->assertStringContainsString('https://shop.example.test/assets/logos/logo.png', $html);
        $this->assertStringContainsString('https://shop.example.test/account/orders/'.$n, $html);
        $this->assertStringContainsString('Total', $html);
    }

    public function test_a_repeated_request_or_job_never_sends_a_second_confirmation(): void
    {
        Mail::fake();
        $body = $this->body(2);
        $n = $this->postJson('/checkout/place', $body)->assertCreated()->json('orderNumber');
        $this->postJson('/checkout/place', $body)->assertOk()->assertJson(['duplicate' => true]);
        $this->assertSame('already_sent', OrderEmails::deliver($n));
        OrderEmails::queue($n);
        Mail::assertSent(OrderConfirmationMail::class, 1);
        $this->assertSame('sent', OrderEmails::state($this->orderRow($n)->id));
    }

    public function test_card_order_is_confirmed_only_after_the_verified_payment_and_only_once(): void
    {
        Mail::fake();
        $n = $this->postJson('/checkout/place', $this->body(2, null, 'card'))->assertCreated()->json('orderNumber');
        Mail::assertNothingSent();                                               // placed, unpaid: no "confirmation", no payment claim
        $this->assertSame('not_ready', OrderEmails::deliver($n));

        $sid = array_key_last($this->sessions);
        $this->webhookPaid($sid, $n, 'evt_one')->assertOk();
        $this->webhookPaid($sid, $n, 'evt_one')->assertOk()->assertJson(['outcome' => 'duplicate']);
        $this->webhookPaid($sid, $n, 'evt_two')->assertOk();                      // a different event for the same payment
        Mail::assertSent(OrderConfirmationMail::class, 1);

        $mail = new OrderConfirmationMail(CustomerOrders::find($this->customerId, $n), 'Payer');
        $html = $mail->render();
        $this->assertStringContainsString('Deposit received', $html);
        $this->assertStringContainsString('still outstanding', $html);
        $this->assertStringNotContainsString('Paid in full', $html);
        $this->assertStringNotContainsString($sid, $html);
        $this->assertStringContainsString('Online payment discount', $html);
        $this->assertSame('pending', $this->orderRow($n)->payment_status);
    }

    public function test_failed_send_is_logged_recorded_and_retried_without_a_duplicate(): void
    {
        $fail = true;
        Event::listen(MessageSending::class, function () use (&$fail) {
            if ($fail) {
                throw new RuntimeException('smtp down');
            }
        });
        $sent = [];
        Event::listen(MessageSending::class, function (MessageSending $e) use (&$fail, &$sent) {
            if (! $fail) {
                $sent[] = $e->message->getSubject();
            }
        });
        $n = null;
        try {
            $n = $this->postJson('/checkout/place', $this->body(2))->assertCreated()->json('orderNumber');
        } catch (\Throwable) {
            // sync queue rethrows inside the request; the order itself is already committed
        }
        $n ??= DB::table('orders')->where('customer_id', $this->customerId)->value('order_number');
        $id = $this->orderRow($n)->id;
        $this->assertSame('failed', OrderEmails::state($id));
        $this->assertSame([], $sent);

        $this->assertSame(0, OrderEmails::retryStuck(), 'too soon: waits 5 minutes between tries');
        Carbon::setTestNow(now()->addMinutes(6));
        $fail = false;
        $this->assertSame(1, OrderEmails::retryStuck());
        $this->assertSame('sent', OrderEmails::state($id));
        $this->assertCount(1, $sent);
        $this->assertSame(0, OrderEmails::retryStuck(), 'sent ones are never re-queued');
        Carbon::setTestNow();
    }

    public function test_queue_outage_does_not_lose_the_email(): void
    {
        Mail::fake();
        config(['storefront.order_mail_queue' => 'no-such-connection']);        // pushing to the queue throws
        $n = $this->postJson('/checkout/place', $this->body(2))->assertCreated()->json('orderNumber');
        // The fallback runs after the response (defer); in a test the deferred callbacks run on demand.
        app(DeferredCallbackCollection::class)->invokeWhen(fn () => true);
        Mail::assertSent(OrderConfirmationMail::class, 1);
        $this->assertSame('sent', OrderEmails::state($this->orderRow($n)->id));
    }
}
