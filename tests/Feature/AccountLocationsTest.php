<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/** Account → My Locations: saved delivery addresses (customer_addresses) via /account/addresses. */
class AccountLocationsTest extends TestCase
{
    use DatabaseTransactions, StorefrontFixtures;

    private int $customerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
        $this->customerId = $this->makeCustomer('locations@example.test');
        $this->withSession($this->signedInSession($this->customerId));
    }

    private function body(array $overrides = []): array
    {
        return $overrides + [
            'addressType' => 'Home', 'fullName' => 'Jane Citizen', 'phone' => '0412 345 678', 'line1' => '12 Smith St',
            'line2' => 'Unit 5', 'suburb' => 'Fitzroy', 'state' => 'VIC', 'postcode' => '3065', 'country' => 'Australia',
        ];
    }

    private function defaults(?int $customerId = null): array
    {
        return DB::table('customer_addresses')->where('customer_id', $customerId ?? $this->customerId)->where('is_default', 1)->pluck('id')->all();
    }

    public function test_empty_list(): void
    {
        $this->getJson('/account/addresses')->assertOk()->assertExactJson([]);
    }

    public function test_guest_cannot_use_locations(): void
    {
        $this->flushSession();
        $this->getJson('/account/addresses')->assertUnauthorized();
        $this->postJson('/account/addresses', $this->body())->assertUnauthorized();
    }

    public function test_first_location_becomes_default_and_lines_round_trip(): void
    {
        $res = $this->postJson('/account/addresses', $this->body())->assertOk();
        $addr = $res->json('addresses.0');

        $this->assertTrue($addr['isDefault']);
        $this->assertSame('12 Smith St', $addr['line1']);
        $this->assertSame('Unit 5', $addr['line2']);
        $this->assertSame('12 Smith St, Unit 5', $addr['street']); // single line for checkout
        $this->assertSame('Australia', $addr['country']);
        $this->assertSame("12 Smith St\nUnit 5", DB::table('customer_addresses')->where('id', $addr['id'])->value('street_address'));
    }

    public function test_only_one_default_and_new_default_replaces_old(): void
    {
        $first = $this->postJson('/account/addresses', $this->body())->json('id');
        $second = $this->postJson('/account/addresses', $this->body(['line1' => '1 Other Rd']))->json('id');
        $this->assertSame([$first], $this->defaults());

        $third = $this->postJson('/account/addresses', $this->body(['line1' => '9 New St', 'isDefault' => true]))->json('id');
        $this->assertSame([$third], $this->defaults());

        $this->putJson("/account/addresses/{$second}/default")->assertOk()->assertJsonPath('addresses.0.id', $second);
        $this->assertSame([$second], $this->defaults());

        $this->putJson("/account/addresses/{$first}", $this->body(['isDefault' => true]))->assertOk();
        $this->assertSame([$first], $this->defaults());
    }

    public function test_deleting_default_promotes_oldest_remaining_and_last_delete_leaves_none(): void
    {
        $a = $this->postJson('/account/addresses', $this->body())->json('id');
        $b = $this->postJson('/account/addresses', $this->body(['line1' => '2 B St']))->json('id');
        $c = $this->postJson('/account/addresses', $this->body(['line1' => '3 C St']))->json('id');

        $this->deleteJson("/account/addresses/{$a}")->assertOk();
        $this->assertSame([$b], $this->defaults());

        $this->deleteJson("/account/addresses/{$c}")->assertOk(); // non-default: default unchanged
        $this->assertSame([$b], $this->defaults());

        $this->deleteJson("/account/addresses/{$b}")->assertOk()->assertJsonPath('addresses', []);
    }

    public function test_labels_home_work_and_custom_other(): void
    {
        $work = $this->postJson('/account/addresses', $this->body(['addressType' => 'Office']))->json('id');
        $custom = $this->postJson('/account/addresses', $this->body(['addressType' => 'Other', 'customLabel' => "Mum's place"]))->json('id');
        $plainOther = $this->postJson('/account/addresses', $this->body(['addressType' => 'Other', 'customLabel' => '']))->json('id');

        $list = collect($this->getJson('/account/addresses')->json())->keyBy('id');
        $this->assertSame('Office', $list[$work]['addressType']);
        $this->assertSame(['Other', "Mum's place"], [$list[$custom]['addressType'], $list[$custom]['customLabel']]);
        $this->assertSame(['Other', ''], [$list[$plainOther]['addressType'], $list[$plainOther]['customLabel']]);

        // Checkout's form has no custom-label field: saving there keeps the customer's custom label.
        $checkoutBody = ['fullName' => 'Jane', 'phone' => '0412 345 678', 'street' => '5 Elm St', 'suburb' => 'Fitzroy', 'state' => 'VIC', 'postcode' => '3065', 'addressType' => 'Other'];
        $this->putJson("/account/addresses/{$custom}", $checkoutBody)->assertOk();
        $this->assertSame("Mum's place", DB::table('customer_addresses')->where('id', $custom)->value('label'));
        $this->assertSame('5 Elm St', DB::table('customer_addresses')->where('id', $custom)->value('street_address'));
    }

    public static function invalidBodies(): array
    {
        return [
            'missing name' => [['fullName' => ''], 'fullName'],
            'missing line 1' => [['line1' => ''], 'line1'],
            'missing suburb' => [['suburb' => ''], 'suburb'],
            'bad phone' => [['phone' => 'call me'], 'phone'],
            'bad state' => [['state' => 'XYZ'], 'state'],
            'short postcode' => [['postcode' => '300'], 'postcode'],
            'postcode not in state' => [['state' => 'VIC', 'postcode' => '2000'], 'postcode'],
            'not australia' => [['country' => 'New Zealand'], 'country'],
            'bad label' => [['addressType' => 'Castle'], 'addressType'],
            'long custom label' => [['addressType' => 'Other', 'customLabel' => str_repeat('x', 41)], 'customLabel'],
            'newline in line 1' => [['line1' => "1 A St\n2 B St"], 'line1'],
        ];
    }

    #[DataProvider('invalidBodies')]
    public function test_validation(array $overrides, string $field): void
    {
        $this->postJson('/account/addresses', $this->body($overrides))->assertStatus(422)->assertJsonValidationErrors($field);
        $this->assertSame(0, DB::table('customer_addresses')->where('customer_id', $this->customerId)->count());
    }

    public function test_valid_postcodes_for_each_state(): void
    {
        foreach (['NSW' => '2000', 'ACT' => '2600', 'VIC' => '3000', 'QLD' => '4000', 'SA' => '5000', 'WA' => '6000', 'TAS' => '7000', 'NT' => '0800'] as $state => $pc) {
            $this->postJson('/account/addresses', $this->body(['state' => $state, 'postcode' => $pc]))->assertOk();
        }
    }

    public function test_customers_cannot_see_or_change_each_others_locations(): void
    {
        $otherId = $this->makeCustomer('someone-else@example.test');
        $theirs = $this->makeAddress($otherId);
        $before = (array) DB::table('customer_addresses')->where('id', $theirs)->first();

        $this->getJson('/account/addresses')->assertOk()->assertJsonMissing(['id' => $theirs]);
        $this->putJson("/account/addresses/{$theirs}", $this->body())->assertNotFound();
        $this->putJson("/account/addresses/{$theirs}/default")->assertNotFound();
        $this->deleteJson("/account/addresses/{$theirs}")->assertNotFound();

        $this->assertEquals($before, (array) DB::table('customer_addresses')->where('id', $theirs)->first());
        // Making one of my own locations default never touches another customer's default.
        $mine = $this->postJson('/account/addresses', $this->body(['isDefault' => true]))->json('id');
        $this->assertSame([$mine], $this->defaults());
        $this->assertSame([$theirs], $this->defaults($otherId));
    }

    public function test_checkout_uses_two_line_location_as_one_street_line(): void
    {
        $id = $this->postJson('/account/addresses', $this->body(['state' => 'NSW', 'postcode' => '2000', 'suburb' => 'Sydney']))->json('id');

        $res = $this->postJson('/checkout/place', ['items' => [['productId' => 2, 'quantity' => 1]], 'paymentMethod' => 'cod', 'addressId' => $id]);
        $res->assertCreated();
        $street = DB::table('orders')->where('customer_id', $this->customerId)->latest('id')->value('shipping_street_address');
        $this->assertSame('12 Smith St, Unit 5', $street);
    }

    public function test_account_page_shows_my_locations_tab(): void
    {
        $this->get('/account')->assertOk()
            ->assertSee('data-account-tab="locations"', false)
            ->assertSee('id="tab-panel-locations"', false)
            ->assertSee('My Locations');
    }
}
