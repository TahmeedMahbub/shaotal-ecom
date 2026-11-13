<?php

namespace Tests\Feature;

use App\Models\CustomerOrders;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * All 11 admin order_status values stored directly in orders.order_status: customer history, details and timeline show the
 * right labels and progression, and payment status stays independent of fulfilment status. MySQL test DB with the Admin schema.
 */
class AdminContractOrderStatusTest extends TestCase
{
    use DatabaseTransactions;

    private int $customerId;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires the MySQL test database.');
        }
        $this->customerId = DB::table('customers')->insertGetId(['first_name' => 'Hist', 'email' => 'hist@example.test', 'status' => 'active', 'email_verified_at' => now()]);
    }

    private function order(string $number, string $status, string $payment = 'pending', string $method = 'cod'): int
    {
        $id = DB::table('orders')->insertGetId([
            'order_number' => $number, 'customer_id' => $this->customerId, 'shipping_recipient_name' => 'H', 'shipping_phone' => '1', 'shipping_street_address' => '1 St',
            'subtotal' => 50, 'shipping_total' => 12, 'grand_total' => 62, 'payment_method' => $method, 'payment_status' => $payment, 'order_status' => $status,
            'placed_at' => '2026-11-01 10:00:00',
        ]);
        DB::table('order_items')->insert(['order_id' => $id, 'product_id' => null, 'product_name' => 'Thing', 'unit_price' => 50, 'unit_selling_price' => 50, 'quantity' => 1, 'line_total' => 50]);
        DB::table('order_timeline')->insert(['order_id' => $id, 'old_status' => null, 'new_status' => 'pending', 'created_at' => '2026-11-01 10:00:00']);

        return $id;
    }

    public function test_every_status_shows_its_label_in_history_and_details_with_correct_progression(): void
    {
        $keys = array_keys(OrderStatus::STEPS);
        foreach (OrderStatus::ALL as $n => $status) {
            $this->order("INV-st$n", $status);
        }
        // 11 orders = two history pages (10 per page).
        $list = collect(CustomerOrders::page($this->customerId, 1)['orders'])->merge(CustomerOrders::page($this->customerId, 2)['orders'])->keyBy('id');
        $this->assertCount(11, $list);

        foreach (OrderStatus::ALL as $n => $status) {
            $this->assertSame(OrderStatus::label($status), $list["INV-st$n"]['status'], $status);
            $this->assertNotSame('', $list["INV-st$n"]['statusClass']);
            $v = CustomerOrders::find($this->customerId, "INV-st$n");
            $this->assertSame(OrderStatus::label($status), $v['statusLabel'], $status);
            if (isset(OrderStatus::STEPS[$status])) {
                $i = array_search($status, $keys, true);
                $states = array_column($v['timeline']['steps'], 'state', 'key');
                $this->assertSame($status === 'delivered' ? 'done' : 'current', $states[$status]);
                foreach (array_slice($keys, $i + 1) as $later) {
                    $this->assertSame('upcoming', $states[$later], "$status -> $later");
                }
                $this->assertNull($v['timeline']['exception']);
            } else {
                $this->assertSame($status, $v['timeline']['exception']['key']);
            }
        }
    }

    public function test_payment_status_is_independent_of_fulfilment_status(): void
    {
        $this->order('INV-pay1', 'out_for_delivery', 'pending', 'cod');
        $this->order('INV-pay2', 'pending', 'paid', 'card');
        $list = collect(CustomerOrders::page($this->customerId, 1)['orders'])->keyBy('id');
        $this->assertSame(['Out for Delivery', 'Pay on delivery'], [$list['INV-pay1']['status'], $list['INV-pay1']['paymentStatus']]);
        $this->assertSame(['Order Placed', 'Paid'], [$list['INV-pay2']['status'], $list['INV-pay2']['paymentStatus']]);
    }

    public function test_legacy_sub_stage_recorded_only_in_the_timeline_still_refines_the_parent_status(): void
    {
        $id = $this->order('INV-leg1', 'shipped');
        DB::table('order_timeline')->insert(['order_id' => $id, 'old_status' => 'shipped', 'new_status' => 'in_transit', 'created_at' => '2026-11-03 10:00:00']);
        $this->assertSame('In Transit', CustomerOrders::find($this->customerId, 'INV-leg1')['statusLabel']);
        $this->assertSame('In Transit', collect(CustomerOrders::page($this->customerId, 1)['orders'])->firstWhere('id', 'INV-leg1')['status']);
    }

    public function test_customer_can_cancel_only_while_pending_and_unpaid(): void
    {
        $this->order('INV-c1', 'pending');
        $this->order('INV-c2', 'ready_for_dispatch');
        $this->assertTrue(CustomerOrders::cancel($this->customerId, 'INV-c1'));
        $this->assertFalse(CustomerOrders::cancel($this->customerId, 'INV-c2'));
        $this->assertSame('cancelled', DB::table('orders')->where('order_number', 'INV-c1')->value('order_status'));
    }
}
