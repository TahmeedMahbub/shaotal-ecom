<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/**
 * Part 3: order history, order details/tracking, admin status sync through the shared tables, customer cancellation,
 * COD/payment integrity and the "stock never blocks or changes" policy. Local MySQL *_test database only.
 * Seed (sql/local_test_seed.sql): product 1 variant tee (Olive Green/S has 0 stock), 2 simple cushion A$55 (stock 20),
 * 5 admin-flagged out_of_stock.
 */
class CustomerOrdersTest extends TestCase
{
    use DatabaseTransactions;
    use StorefrontFixtures;

    private int $customer;

    private int $other;

    private int $address;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
        $this->customer = $this->makeCustomer('orders-a@example.test');
        $this->other = $this->makeCustomer('orders-b@example.test');
        $this->address = $this->makeAddress($this->customer);
    }

    private function asCustomer(?int $id = null): static
    {
        return $this->withSession($this->signedInSession($id ?? $this->customer));
    }

    private function place(array $items = [['productId' => 2, 'quantity' => 1]], array $extra = []): TestResponse
    {
        return $this->asCustomer()->postJson('/checkout/place', $extra + ['items' => $items, 'paymentMethod' => 'cod', 'addressId' => $this->address]);
    }

    private function orderRow(string $number): object
    {
        return DB::table('orders')->where('order_number', $number)->first();
    }

    /** What the admin app's status update does: order_status + an order_timeline row with the acting admin. */
    private function adminSetStatus(string $number, string $status, string $at, ?int $adminId = null): void
    {
        $o = $this->orderRow($number);
        DB::table('orders')->where('id', $o->id)->update(['order_status' => $status, 'updated_by' => $adminId]);
        DB::table('order_timeline')->insert(['order_id' => $o->id, 'old_status' => $o->order_status, 'new_status' => $status, 'changed_by_admin_id' => $adminId, 'created_at' => $at]);
    }

    private function admin(): int
    {
        return DB::table('admin_users')->insertGetId(['name' => 'Ops', 'email' => 'ops-'.uniqid().'@example.test', 'password_hash' => 'x']);
    }

    /* ── History ── */

    public function test_history_lists_only_own_orders_newest_first_with_payment_and_status(): void
    {
        $mine = $this->place()->assertCreated()->json('orderNumber');
        DB::table('orders')->insert([
            'order_number' => 'INV-OTHER1', 'customer_id' => $this->other, 'shipping_recipient_name' => 'B', 'shipping_phone' => '0400000000',
            'shipping_street_address' => '2 St', 'grand_total' => 5, 'payment_method' => 'cod',
        ]);

        $res = $this->asCustomer()->getJson('/account/orders')->assertOk();
        $res->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame([$mine], array_column($res->json('orders'), 'id'));
        $o = $res->json('orders.0');
        $this->assertSame('Order Placed', $o['status']);
        $this->assertSame('Cash on Delivery', $o['paymentMethod']);
        $this->assertSame('Pay on delivery', $o['paymentStatus']);
        $this->assertSame('/account/orders/'.$mine, $o['url']);
        $this->assertEquals(65.0, $o['total']);   // A$55 + A$10 delivery (under the A$100 threshold)
        $this->assertFalse($res->json('hasMore'));
    }

    public function test_history_is_paginated(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            DB::table('orders')->insert([
                'order_number' => sprintf('INV-P%02d', $i), 'customer_id' => $this->customer, 'shipping_recipient_name' => 'A', 'shipping_phone' => '0400000000',
                'shipping_street_address' => '1 St', 'grand_total' => $i, 'payment_method' => 'cod', 'created_at' => now()->subMinutes(20 - $i),
            ]);
        }

        $p1 = $this->asCustomer()->getJson('/account/orders?page=1')->assertOk();
        $this->assertCount(10, $p1->json('orders'));
        $this->assertTrue($p1->json('hasMore'));
        $this->assertSame('INV-P11', $p1->json('orders.0.id'));

        $p2 = $this->asCustomer()->getJson('/account/orders?page=2')->assertOk();
        $this->assertSame(['INV-P01'], array_column($p2->json('orders'), 'id'));
        $this->assertFalse($p2->json('hasMore'));
    }

    /* ── Access control ── */

    public function test_guests_cannot_read_history_details_or_cancel(): void
    {
        $number = $this->place()->json('orderNumber');
        $this->flushSession();

        $this->getJson('/account/orders')->assertUnauthorized();
        $this->get('/account/orders/'.$number)->assertRedirect('/?signin=/account/orders/'.$number);
        $this->postJson('/account/orders/'.$number.'/cancel')->assertUnauthorized();
        $this->assertSame('pending', $this->orderRow($number)->order_status);
    }

    public function test_another_customer_gets_404_for_details_and_cancel(): void
    {
        $number = $this->place()->json('orderNumber');

        $this->asCustomer($this->other)->get('/account/orders/'.$number)->assertNotFound();
        $this->asCustomer($this->other)->postJson('/account/orders/'.$number.'/cancel')->assertNotFound();
        $this->assertSame('pending', $this->orderRow($number)->order_status);
    }

    public function test_unknown_or_malformed_order_numbers_are_404(): void
    {
        $this->asCustomer()->get('/account/orders/INV-000000')->assertNotFound();
        $this->asCustomer()->get('/account/orders/'.rawurlencode("x' OR 1=1"))->assertNotFound();
    }

    /* ── Details ── */

    public function test_details_page_shows_items_totals_address_payment_and_is_private(): void
    {
        $this->makeCoupon('ORD10');
        $number = $this->place([['productId' => 2, 'quantity' => 2], ['productId' => 1, 'color' => '#1A1A1A', 'size' => 'L', 'quantity' => 1]], ['coupon' => 'ORD10'])
            ->assertCreated()->json('orderNumber');

        $res = $this->asCustomer()->get('/account/orders/'.$number)->assertOk();
        $res->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $res->assertHeader('Cache-Control', 'no-store, private');
        $html = $res->getContent();
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
        $this->assertStringContainsString('Order #'.e($number), $html);
        $this->assertStringContainsString('Nakshi Kantha Cushion', $html);
        $this->assertStringContainsString('Essential Heavyweight 240GSM Tee', $html);
        $this->assertStringContainsString('Colour: Midnight Black', $html);
        $this->assertStringContainsString('Size: L', $html);
        // 2 × 55 + 26 (variant sells at the product selling price; A$32 is its list price) = 136; 10% = 13.60; free delivery
        $this->assertStringContainsString('A$136.00', $html);
        $this->assertStringContainsString('A$26.00 each <span class="order-item-was">A$32.00</span>', $html);
        $this->assertStringContainsString('Discount (ORD10)', $html);
        $this->assertStringContainsString('&minus;A$13.60', $html);
        $this->assertStringContainsString('A$122.40 AUD', $html);
        $this->assertStringContainsString('Free', $html);
        $this->assertStringContainsString('1 Test St', $html);
        $this->assertStringContainsString('Carlton VIC 3053', $html);
        $this->assertStringContainsString('Cash on Delivery', $html);
        $this->assertStringContainsString('Pay on delivery', $html);
        $this->assertStringContainsString('href="/account#orders"', $html);
        $this->assertStringContainsString('id="order-cancel-btn"', $html);
    }

    public function test_details_escape_snapshot_text(): void
    {
        $number = $this->place()->json('orderNumber');
        DB::table('order_items')->where('order_id', $this->orderRow($number)->id)->update(['product_name' => '<script>alert(1)</script>']);

        $html = $this->asCustomer()->get('/account/orders/'.$number)->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_price_snapshot_is_shown_even_after_the_product_price_changes(): void
    {
        $number = $this->place()->json('orderNumber');
        DB::table('products')->where('id', 2)->update(['price' => 99, 'selling_price' => 99]);

        $html = $this->asCustomer()->get('/account/orders/'.$number)->getContent();
        $this->assertStringContainsString('A$55.00 each', $html);
        $this->assertStringNotContainsString('A$99.00', $html);
    }

    /* ── Tracking: admin updates through the shared tables ── */

    public function test_admin_status_updates_show_on_the_storefront_with_recorded_times_only(): void
    {
        $number = $this->place()->json('orderNumber');
        $admin = $this->admin();
        $this->adminSetStatus($number, 'confirmed', '2026-11-02 09:15:00', $admin);
        $this->adminSetStatus($number, 'shipped', '2026-11-03 14:00:00', $admin);   // processing skipped by staff

        $html = $this->asCustomer()->get('/account/orders/'.$number)->getContent();
        $this->assertStringContainsString('>Shipped<', $html);                                  // badge
        // Stored UTC, shown in Australia/Sydney (AEDT, +11 in November)
        $this->assertStringContainsString('2 Nov 2026, 8:15 pm AEDT', $html);
        $this->assertStringContainsString('4 Nov 2026, 1:00 am AEDT', $html);
        $this->assertMatchesRegularExpression('/is-done"[^>]*>\s*<span class="order-track-dot"[^>]*>&#10003;<\/span>\s*<span class="order-track-label">Processing/', $html);
        $this->assertMatchesRegularExpression('/is-upcoming"[^>]*>\s*<span class="order-track-dot"[^>]*>5<\/span>\s*<span class="order-track-label">Delivered/', $html);
        $this->assertStringNotContainsString('id="order-cancel-btn"', $html);              // no longer cancellable

        $list = $this->asCustomer()->getJson('/account/orders')->json('orders.0');
        $this->assertSame('Shipped', $list['status']);
        $this->assertSame('in-transit', $list['statusClass']);
    }

    public function test_paid_status_recorded_by_admin_is_shown_separately_from_delivery(): void
    {
        $number = $this->place()->json('orderNumber');
        $this->adminSetStatus($number, 'delivered', '2026-11-05 10:00:00');
        DB::table('orders')->where('order_number', $number)->update(['payment_status' => 'paid']);

        $html = $this->asCustomer()->get('/account/orders/'.$number)->getContent();
        $this->assertStringContainsString('<strong id="order-payment-status">Paid</strong>', $html);
        $this->assertStringContainsString('>Delivered<', $html);
    }

    /* ── Cancellation ── */

    public function test_customer_can_cancel_a_pending_order_once_without_touching_stock_or_payment(): void
    {
        $number = $this->place([['productId' => 2, 'quantity' => 3]])->json('orderNumber');
        $stock = DB::table('products')->where('id', 2)->value('stock_qty');

        $this->asCustomer()->postJson('/account/orders/'.$number.'/cancel')->assertOk()->assertJsonPath('order.status', 'cancelled');

        $o = $this->orderRow($number);
        $this->assertSame('cancelled', $o->order_status);
        $this->assertNotNull($o->cancelled_at);
        $this->assertSame('pending', $o->payment_status);   // COD: nothing collected, nothing refunded
        $this->assertSame(1, DB::table('order_timeline')->where('order_id', $o->id)->where('new_status', 'cancelled')->where('old_status', 'pending')->count());
        $this->assertSame('pending', DB::table('payments')->where('order_id', $o->id)->value('status'));
        $this->assertSame($stock, DB::table('products')->where('id', 2)->value('stock_qty'));

        // Second attempt: refused, no duplicate history row.
        $this->asCustomer()->postJson('/account/orders/'.$number.'/cancel')->assertStatus(422);
        $this->assertSame(1, DB::table('order_timeline')->where('order_id', $o->id)->where('new_status', 'cancelled')->count());

        $html = $this->asCustomer()->get('/account/orders/'.$number)->getContent();
        $this->assertStringContainsString('No payment due', $html);
        $this->assertStringContainsString('this order will not be delivered', $html);
    }

    public function test_confirmed_or_later_orders_cannot_be_cancelled_by_the_customer(): void
    {
        foreach (['confirmed', 'processing', 'shipped', 'delivered', 'returned'] as $i => $status) {
            // a different quantity per order: an identical repeat within 60 s is answered with the same order (C1 guard)
            $number = $this->place([['productId' => 2, 'quantity' => $i + 1]])->assertCreated()->json('orderNumber');
            $this->adminSetStatus($number, $status, '2026-11-02 09:00:00');

            $this->asCustomer()->postJson('/account/orders/'.$number.'/cancel')->assertStatus(422);
            $this->assertSame($status, $this->orderRow($number)->order_status, $status);
        }
    }

    public function test_cancellation_can_be_switched_off(): void
    {
        config(['storefront.customer_cancellable_statuses' => []]);
        $number = $this->place()->json('orderNumber');

        $this->assertStringNotContainsString('id="order-cancel-btn"', $this->asCustomer()->get('/account/orders/'.$number)->getContent());
        $this->asCustomer()->postJson('/account/orders/'.$number.'/cancel')->assertStatus(422);
        $this->assertSame('pending', $this->orderRow($number)->order_status);
    }

    public function test_cancelled_order_releases_its_coupon_use(): void
    {
        $this->makeCoupon('ONCE', ['usage_limit_per_customer' => 1]);
        $number = $this->place(extra: ['coupon' => 'ONCE'])->assertCreated()->json('orderNumber');
        $this->place([['productId' => 2, 'quantity' => 2]], ['coupon' => 'ONCE'])->assertStatus(422);

        $this->asCustomer()->postJson('/account/orders/'.$number.'/cancel')->assertOk();
        $this->place([['productId' => 2, 'quantity' => 2]], ['coupon' => 'ONCE'])->assertCreated();
    }

    /* ── Payment & order integrity ── */

    public function test_cod_order_is_never_marked_paid_and_client_payment_fields_are_ignored(): void
    {
        $number = $this->place(extra: ['paymentStatus' => 'paid', 'payment_status' => 'paid', 'total' => 0.01, 'discount' => 50])
            ->assertCreated()->json('orderNumber');

        $o = $this->orderRow($number);
        $this->assertSame('pending', $o->payment_status);
        $this->assertSame('cod', $o->payment_method);
        $this->assertSame('65.00', $o->grand_total);
        $this->assertSame('0.00', $o->discount_total);
        $p = DB::table('payments')->where('order_id', $o->id)->first();
        $this->assertSame('pending', $p->status);
        $this->assertNull($p->provider);
        $this->assertSame('65.00', $p->amount);
        $this->assertNull($p->paid_at);
    }

    public function test_unimplemented_payment_methods_are_rejected(): void
    {
        config(['storefront.payment_methods' => ['cod', 'card']]);   // configured but no gateway implementation
        $this->place(extra: ['paymentMethod' => 'card'])->assertStatus(422);
        $this->assertSame(0, DB::table('orders')->where('customer_id', $this->customer)->count());
    }

    public function test_order_items_snapshot_name_sku_variant_and_prices(): void
    {
        $number = $this->place([['productId' => 1, 'color' => '#1A1A1A', 'size' => 'L', 'quantity' => 2]])->json('orderNumber');
        $item = DB::table('order_items')->where('order_id', $this->orderRow($number)->id)->first();

        $this->assertSame('Essential Heavyweight 240GSM Tee', $item->product_name);
        $this->assertSame('TEE-001-BK-L', $item->sku);
        $this->assertSame('Midnight Black', $item->variant_color);
        $this->assertSame('L', $item->variant_size);
        $this->assertSame('32.00', $item->unit_price);           // variant price_override
        $this->assertSame('26.00', $item->unit_selling_price);   // product selling price
        $this->assertSame('52.00', $item->line_total);
    }

    /* ── Inventory policy: stock never blocks, reserves or changes ── */

    public function test_zero_stock_simple_and_variant_items_can_be_ordered_and_stock_is_unchanged(): void
    {
        DB::table('products')->where('id', 2)->update(['stock_qty' => 0]);
        $variantStock = DB::table('product_variants')->where('sku', 'TEE-001-OG-S')->value('stock_qty');
        $this->assertSame(0, (int) $variantStock);

        $this->place([['productId' => 2, 'quantity' => 5], ['productId' => 1, 'color' => '#5C6B4F', 'size' => 'S', 'quantity' => 3]])->assertCreated();

        $this->assertSame(0, (int) DB::table('products')->where('id', 2)->value('stock_qty'));
        $this->assertSame(0, (int) DB::table('product_variants')->where('sku', 'TEE-001-OG-S')->value('stock_qty'));
    }

    public function test_quantity_above_stock_is_accepted(): void
    {
        $this->place([['productId' => 2, 'quantity' => 50]])->assertCreated();   // stock 20
        $this->assertSame(20, (int) DB::table('products')->where('id', 2)->value('stock_qty'));
    }

    public function test_draft_hidden_inactive_archived_and_flagged_products_cannot_be_ordered(): void
    {
        foreach ([['status' => 'draft'], ['status' => 'inactive'], ['status' => 'archived'], ['visibility' => 'hidden']] as $change) {
            DB::table('products')->where('id', 2)->update($change + ['status' => 'active', 'visibility' => 'visible']);
            Cache::store('file')->flush();
            $this->place()->assertStatus(422);
        }
        DB::table('products')->where('id', 2)->update(['status' => 'active', 'visibility' => 'visible']);
        Cache::store('file')->flush();
        $this->place([['productId' => 5, 'quantity' => 1]])->assertStatus(422);   // admin-flagged out_of_stock
        $this->assertSame(0, DB::table('orders')->where('customer_id', $this->customer)->count());
    }
}
