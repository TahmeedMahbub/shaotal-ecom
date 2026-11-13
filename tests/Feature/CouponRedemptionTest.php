<?php

namespace Tests\Feature;

use App\Models\Checkout;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;
use Throwable;

/** Task 5: coupon limits enforced at order time (sequential cases; true concurrency: CouponConcurrencyTest). */
class CouponRedemptionTest extends TestCase
{
    use DatabaseTransactions, StorefrontFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
    }

    private function placeAs(int $customerId, string $code, int $qty = 1)
    {
        $this->flushSession();

        return $this->withSession($this->signedInSession($customerId))->postJson('/checkout/place', [
            'items' => [['productId' => 2, 'quantity' => $qty]], 'paymentMethod' => 'cod', 'coupon' => $code,
            'addressId' => DB::table('customer_addresses')->where('customer_id', $customerId)->value('id') ?? $this->makeAddress($customerId),
        ]);
    }

    private function uses(string $code): int
    {
        return DB::table('orders')->where('coupon_code', $code)->count();
    }

    public function test_valid_redemption_applies_the_discount(): void
    {
        $this->makeCoupon('SAVE10');
        $c = $this->makeCustomer('c1@example.test');

        $this->placeAs($c, 'SAVE10')->assertCreated();

        $order = DB::table('orders')->where('customer_id', $c)->first();
        $this->assertSame('SAVE10', $order->coupon_code);
        $this->assertSame('5.50', $order->discount_total);   // 10% of A$55
        $this->assertSame('61.50', $order->grand_total);     // 55 + 12 shipping − 5.50
    }

    public function test_usage_limit_reached_is_rejected(): void
    {
        $this->makeCoupon('ONCE', ['usage_limit' => 1]);
        $a = $this->makeCustomer('a@example.test');
        $b = $this->makeCustomer('b@example.test');

        $this->placeAs($a, 'ONCE')->assertCreated();
        $this->placeAs($b, 'ONCE')->assertStatus(422);

        $this->assertSame(1, $this->uses('ONCE'));
        $this->assertSame(0, DB::table('orders')->where('customer_id', $b)->count());
    }

    public function test_cancelled_orders_do_not_count_towards_the_limit(): void
    {
        $this->makeCoupon('BACK', ['usage_limit' => 1]);
        $a = $this->makeCustomer('a2@example.test');
        $this->placeAs($a, 'BACK')->assertCreated();
        DB::table('orders')->where('coupon_code', 'BACK')->update(['order_status' => 'cancelled']);

        $this->placeAs($this->makeCustomer('b2@example.test'), 'BACK')->assertCreated();
    }

    public function test_per_customer_limit(): void
    {
        $this->makeCoupon('PERCUST', ['usage_limit_per_customer' => 1]);
        $a = $this->makeCustomer('p1@example.test');

        $this->placeAs($a, 'PERCUST', 1)->assertCreated();
        $this->placeAs($a, 'PERCUST', 2)->assertStatus(422);   // different cart, so not a duplicate replay
        $this->placeAs($this->makeCustomer('p2@example.test'), 'PERCUST')->assertCreated();

        $this->assertSame(2, $this->uses('PERCUST'));
    }

    public function test_duplicate_retry_uses_the_coupon_once_and_still_succeeds(): void
    {
        $this->makeCoupon('RETRY', ['usage_limit' => 1]);
        $a = $this->makeCustomer('r@example.test');

        $first = $this->placeAs($a, 'RETRY')->assertCreated();
        $again = $this->placeAs($a, 'RETRY')->assertOk();   // replay, not "limit reached"

        $this->assertSame($first->json('orderNumber'), $again->json('orderNumber'));
        $this->assertSame(1, $this->uses('RETRY'));
    }

    public function test_failed_order_creation_does_not_use_up_the_coupon(): void
    {
        $this->makeCoupon('FAIL', ['usage_limit' => 1]);
        $a = $this->makeCustomer('f@example.test');
        $priced = Checkout::priceLines([['productId' => 2, 'quantity' => 1]]);
        $totals = Checkout::totals($priced['lines'], 'FAIL', $a);
        $address = ['fullName' => 'F', 'phone' => '0400000000', 'street' => 'S', 'suburb' => 'X', 'state' => 'VIC', 'postcode' => '3000'];

        // Force a failure after the coupon is claimed and the order row is inserted (order_items.product_name is NOT NULL).
        $broken = $priced['lines'];
        $broken[0]['name'] = null;
        try {
            Checkout::place($broken, $totals, $address, 'cod', $a, null);
            $this->fail('Expected the order insert to fail.');
        } catch (Throwable) {
        }
        $this->assertSame(0, $this->uses('FAIL'));

        $this->placeAs($a, 'FAIL')->assertCreated();
        $this->assertSame(1, $this->uses('FAIL'));
    }

    public function test_coupon_disabled_between_quote_and_order_is_rejected_at_order_time(): void
    {
        $this->makeCoupon('GONE');
        $a = $this->makeCustomer('g@example.test');
        $priced = Checkout::priceLines([['productId' => 2, 'quantity' => 1]]);
        $totals = Checkout::totals($priced['lines'], 'GONE', $a);   // valid at quote time
        DB::table('coupons')->where('code', 'GONE')->update(['status' => 'inactive']);

        try {
            Checkout::place($priced['lines'], $totals, ['fullName' => 'G', 'phone' => '0400000000', 'street' => 'S', 'suburb' => 'X', 'state' => 'VIC', 'postcode' => '3000'], 'cod', $a, null);
            $this->fail('Expected the coupon to be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame(0, $this->uses('GONE'));
    }
}
