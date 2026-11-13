<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Server-side authorization of customer-only routes (the "customer" middleware + per-controller ownership checks).
 * Needs the local MySQL test database (see STOREFRONT_PRODUCTION_READINESS_PLAN.md → Testing).
 */
class CustomerAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires the MySQL test database.');
        }
    }

    private function customer(string $email, string $status = 'active'): int
    {
        return DB::table('customers')->insertGetId(['first_name' => 'Test', 'email' => $email, 'password_hash' => 'x', 'status' => $status, 'email_verified_at' => now()]);
    }

    private function address(int $customerId): int
    {
        return DB::table('customer_addresses')->insertGetId([
            'customer_id' => $customerId, 'label' => 'Home', 'recipient_name' => 'Owner', 'phone' => '0400000000',
            'street_address' => '1 Private St', 'suburb' => 'Carlton', 'state' => 'VIC', 'postcode' => '3053', 'is_default' => 1,
        ]);
    }

    public static function protectedEndpoints(): array
    {
        return [
            ['GET', '/account/orders'], ['GET', '/account/requests'], ['GET', '/account/addresses'],
            ['POST', '/account/addresses'], ['PUT', '/account/addresses/1'], ['DELETE', '/account/addresses/1'],
            ['PUT', '/account/profile'], ['PUT', '/account/cart'], ['PUT', '/account/wishlist'], ['POST', '/account/merge'],
            ['POST', '/checkout/quote'], ['POST', '/checkout/place'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_guest_gets_401_on_every_customer_endpoint(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertStatus(401);
    }

    public function test_guest_checkout_page_redirects_to_sign_in(): void
    {
        $this->get('/checkout')->assertRedirect('/?signin=/checkout');
    }

    public function test_active_customer_can_use_account_endpoints_and_checkout_page(): void
    {
        $id = $this->customer('active@example.test');
        $this->withSession(['customer_id' => $id]);

        $this->getJson('/account/orders')->assertOk();
        $this->getJson('/account/addresses')->assertOk();
        $this->get('/checkout')->assertOk();
    }

    public function test_blocked_customer_session_is_ended_and_rejected(): void
    {
        $id = $this->customer('blocked@example.test', 'blocked');
        $this->withSession(['customer_id' => $id]);

        $this->getJson('/account/orders')->assertStatus(401)->assertSessionMissing('customer_id');
    }

    public function test_customer_deactivated_mid_session_loses_checkout_access(): void
    {
        $id = $this->customer('later@example.test');
        $this->withSession(['customer_id' => $id]);
        $this->getJson('/account/orders')->assertOk();

        DB::table('customers')->where('id', $id)->update(['status' => 'inactive']);

        $this->postJson('/checkout/place', [])->assertStatus(401);
        $this->get('/checkout')->assertRedirect('/?signin=/checkout');
    }

    public function test_session_for_deleted_customer_is_rejected(): void
    {
        $this->withSession(['customer_id' => 999999999]);

        $this->getJson('/account/orders')->assertStatus(401)->assertSessionMissing('customer_id');
    }

    public function test_customer_only_sees_own_orders(): void
    {
        $a = $this->customer('a@example.test');
        $b = $this->customer('b@example.test');
        foreach ([[$a, 'INV-TESTA1'], [$b, 'INV-TESTB1']] as [$cid, $num]) {
            DB::table('orders')->insert([
                'order_number' => $num, 'customer_id' => $cid, 'shipping_recipient_name' => 'X', 'shipping_phone' => '1',
                'shipping_street_address' => 'S', 'grand_total' => 10,
            ]);
        }

        $ids = $this->withSession(['customer_id' => $a])->getJson('/account/orders')->assertOk()->json('orders.*.id');   // paginated shape since Part 3

        $this->assertSame(['INV-TESTA1'], $ids);
    }

    public function test_customer_cannot_edit_or_delete_another_customers_address(): void
    {
        $owner = $this->customer('owner@example.test');
        $other = $this->customer('other@example.test');
        $addr = $this->address($owner);
        $this->withSession(['customer_id' => $other]);

        $this->putJson("/account/addresses/{$addr}", [
            'fullName' => 'Hijack', 'phone' => '0400 000 000', 'street' => 'x', 'suburb' => 'x', 'state' => 'NSW', 'postcode' => '2000', 'addressType' => 'Home',
        ])->assertNotFound();
        $this->deleteJson("/account/addresses/{$addr}")->assertNotFound();

        $this->assertSame('Owner', DB::table('customer_addresses')->where('id', $addr)->value('recipient_name'));
    }

    public function test_customer_cannot_ship_an_order_to_another_customers_saved_address(): void
    {
        $owner = $this->customer('owner2@example.test');
        $other = $this->customer('other2@example.test');
        $addr = $this->address($owner);

        $this->withSession(['customer_id' => $other])->postJson('/checkout/place', [
            'items' => [['productId' => 2, 'quantity' => 1]], 'paymentMethod' => 'cod', 'addressId' => $addr,
        ])->assertStatus(422);

        $this->assertSame(0, DB::table('orders')->where('customer_id', $other)->count());
    }
}
