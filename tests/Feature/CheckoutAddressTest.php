<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/** Task 4: delivery addresses are complete and well-formed before an order is created; Profile no longer corrupts them. */
class CheckoutAddressTest extends TestCase
{
    use DatabaseTransactions, StorefrontFixtures;

    private int $customerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
        $this->customerId = $this->makeCustomer('addr@example.test');
        $this->withSession($this->signedInSession($this->customerId));
    }

    private function place(array $extra)
    {
        return $this->postJson('/checkout/place', ['items' => [['productId' => 2, 'quantity' => 1]], 'paymentMethod' => 'cod'] + $extra);
    }

    private function orderCount(): int
    {
        return DB::table('orders')->where('customer_id', $this->customerId)->count();
    }

    public function test_complete_saved_address_is_accepted(): void
    {
        $this->place(['addressId' => $this->makeAddress($this->customerId)])->assertCreated();
        $this->assertSame(1, $this->orderCount());
    }

    public static function incompleteSavedAddresses(): array
    {
        return [
            'profile-style, no suburb/state' => [['suburb' => null, 'state' => null], 'suburb'],
            'no postcode' => [['postcode' => null], 'postcode'],
            'blank suburb' => [['suburb' => '  '], 'suburb'],
            'unknown state' => [['state' => 'Victoria'], 'state'],
            '3-digit postcode' => [['postcode' => '305'], '4-digit postcode'],
            'invalid phone' => [['phone' => '12345'], 'phone number'],
        ];
    }

    #[DataProvider('incompleteSavedAddresses')]
    public function test_incomplete_saved_address_is_rejected_and_no_order_is_written(array $overrides, string $mentions): void
    {
        $id = $this->makeAddress($this->customerId, $overrides);

        $res = $this->place(['addressId' => $id])->assertStatus(422)->assertJsonValidationErrors('addressId');

        $this->assertStringContainsString($mentions, $res->json('message'));
        $this->assertSame(0, $this->orderCount());
        // The saved address itself is left untouched.
        $this->assertSame(1, DB::table('customer_addresses')->where('id', $id)->count());
    }

    public function test_inline_address_requires_valid_fields_and_rejects_oversized_input(): void
    {
        $valid = ['fullName' => 'A B', 'phone' => '+61 412 345 678', 'street' => '1 St', 'suburb' => 'Carlton', 'state' => 'VIC', 'postcode' => '3053'];

        $this->place(['address' => ['phone' => '123'] + $valid])->assertStatus(422)->assertJsonValidationErrors('address.phone');
        $this->place(['address' => ['street' => str_repeat('x', 256)] + $valid])->assertStatus(422)->assertJsonValidationErrors('address.street');
        $this->place(['address' => ['state' => 'XX'] + $valid])->assertStatus(422)->assertJsonValidationErrors('address.state');
        $this->place(['address' => array_diff_key($valid, ['suburb' => 1])])->assertStatus(422)->assertJsonValidationErrors('address.suburb');
        $this->assertSame(0, $this->orderCount());

        $this->place(['address' => $valid])->assertCreated();
    }

    public function test_saved_address_endpoints_validate_phone(): void
    {
        $body = ['fullName' => 'A', 'phone' => 'call me', 'street' => 'S', 'suburb' => 'X', 'state' => 'NSW', 'postcode' => '2000', 'addressType' => 'Home'];

        $this->postJson('/account/addresses', $body)->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->postJson('/account/addresses', ['phone' => '02 9123 4567'] + $body)->assertOk();
    }

    public function test_profile_save_without_address_change_keeps_the_saved_address_intact(): void
    {
        $addrId = $this->makeAddress($this->customerId);
        // The form shows (and sends back) the address as "street, suburb, state".
        $this->putJson('/account/profile', ['name' => 'New Name', 'phone' => '0400 111 222', 'postcode' => '3053', 'address' => '1 Test St, Carlton, VIC'])->assertOk();
        $this->putJson('/account/profile', ['name' => 'New Name', 'phone' => '0400 111 222', 'postcode' => '3053', 'address' => '1 Test St, Carlton, VIC'])->assertOk();

        $row = DB::table('customer_addresses')->where('id', $addrId)->first();
        $this->assertSame('1 Test St', $row->street_address);
        $this->assertSame('Carlton', $row->suburb);
        $this->assertSame('VIC', $row->state);
    }

    public function test_profile_address_change_clears_suburb_state_so_checkout_asks_to_complete_it(): void
    {
        $addrId = $this->makeAddress($this->customerId);

        $this->putJson('/account/profile', ['name' => 'Test', 'phone' => '0400 111 222', 'postcode' => '2000', 'address' => '9 New Rd, Sydney'])->assertOk();

        $row = DB::table('customer_addresses')->where('id', $addrId)->first();
        $this->assertSame('9 New Rd, Sydney', $row->street_address);
        $this->assertNull($row->suburb);
        $this->assertNull($row->state);
        $this->place(['addressId' => $addrId])->assertStatus(422);
    }
}
