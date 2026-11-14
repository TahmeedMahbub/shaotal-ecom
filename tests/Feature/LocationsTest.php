<?php

namespace Tests\Feature;

use App\Models\Locations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\ComponentAttributeBag;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/** Central location service: import command, /api/locations/*, address validation. Uses a small fixture file, not the full dataset. */
class LocationsTest extends TestCase
{
    use DatabaseTransactions, StorefrontFixtures;

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
        $this->csv = sys_get_temp_dir().'/loc_fixture_'.uniqid().'.csv';
        $this->importRows($this->fixtureRows());
    }

    protected function tearDown(): void
    {
        if (isset($this->csv)) {
            @unlink($this->csv);
        }
        parent::tearDown();
    }

    private function fixtureRows(): array
    {
        return [
            ['NSW', 'p1', 'SYDNEY', 'G', '2000'], ['NSW', 'p2', 'ALBURY', 'G', '2640'], ['NSW', 'p3', 'MCDOWALL', 'G', '2000'],
            ['VIC', 'p4', 'FITZROY', 'G', '3065'], ['VIC', 'p5', 'MELBOURNE', 'G', '3000'], ['VIC', 'p5', 'MELBOURNE', 'G', '3004'],
            ['QLD', 'p6', 'MOUNT ISA', 'G', '4825'], ['SA', 'p7', 'FITZROY', 'G', '5082'], ['WA', 'p8', 'PERTH', 'G', '6000'],
            ['TAS', 'p9', 'HOBART', 'G', '7000'], ['NT', 'p10', 'DARWIN CITY', 'G', '0800'], ['ACT', 'p11', 'CANBERRA', 'G', '2600'],
            ['OT', 'p12', 'CHRISTMAS ISLAND', 'G', '6798'],
        ];
    }

    private function importRows(array $rows): int
    {
        $h = fopen($this->csv, 'wb');
        fputcsv($h, ['state', 'locality_pid', 'locality_name', 'class_code', 'postcode'], ',', '"', '');
        foreach ($rows as $r) {
            fputcsv($h, $r, ',', '"', '');
        }
        fclose($h);
        Locations::flushCache();

        return Artisan::call('locations:import-australia', ['file' => $this->csv, '--force' => true]);
    }

    private function stateId(string $code): int
    {
        return (int) DB::table('au_states')->where('code', $code)->value('id');
    }

    private function suburbId(string $name, string $state): int
    {
        return (int) DB::table('au_localities')->where('name', $name)->where('state_id', $this->stateId($state))->value('id');
    }

    public function test_import_titlecases_names_keeps_leading_zero_and_multiple_postcodes(): void
    {
        DB::table('au_localities')->count(); // fixture from setUp
        $this->assertSame('Mount Isa', DB::table('au_localities')->where('source_pid', 'p6')->value('name'));
        $this->assertSame('McDowall', DB::table('au_localities')->where('source_pid', 'p3')->value('name'));
        $this->assertSame('0800', DB::table('au_locality_postcodes')->where('locality_id', $this->suburbId('Darwin City', 'NT'))->value('postcode'));
        $this->assertSame(['3000', '3004'], DB::table('au_locality_postcodes')->where('locality_id', $this->suburbId('Melbourne', 'VIC'))->orderBy('postcode')->pluck('postcode')->all());
        $this->assertSame(9, DB::table('au_states')->count());
    }

    public function test_import_is_idempotent_and_reports_updates(): void
    {
        $count = DB::table('au_localities')->count();
        $this->importRows($this->fixtureRows());
        $this->assertSame($count, DB::table('au_localities')->count());
        $this->assertSame(0, (int) DB::table('au_location_imports')->orderByDesc('id')->value('inserted'));

        $rows = array_values(array_filter($this->fixtureRows(), fn ($r) => $r[1] !== 'p3' && $r[4] !== '3004'));
        $rows = array_map(fn ($r) => $r[1] === 'p4' ? ['VIC', 'p4', 'FITZROY NORTH', 'G', '3065'] : $r, $rows);
        $this->importRows($rows);
        $this->assertSame($count - 1, DB::table('au_localities')->count());                                           // McDowall left the source
        $this->assertSame('Fitzroy North', DB::table('au_localities')->where('source_pid', 'p4')->value('name'));      // renamed in place
        $this->assertSame(['3000'], DB::table('au_locality_postcodes')->where('locality_id', $this->suburbId('Melbourne', 'VIC'))->pluck('postcode')->all());
        $this->assertSame(3, DB::table('au_location_imports')->count());                                              // update history kept
    }

    public function test_invalid_rows_are_counted_and_bad_files_are_rejected(): void
    {
        $rows = $this->fixtureRows();
        $rows[] = ['NSW', 'x', 'BAD', 'G', '20A0'];
        $rows[] = ['XX', 'y', 'NOWHERE', 'G', '2000'];
        $this->importRows($rows);
        $this->assertSame(2, (int) DB::table('au_location_imports')->orderByDesc('id')->value('invalid'));

        $this->assertSame(1, Artisan::call('locations:import-australia', ['file' => $this->csv])); // too few localities: looks incomplete
        file_put_contents($this->csv, "a,b\n1,2\n");
        $this->assertSame(1, Artisan::call('locations:import-australia', ['file' => $this->csv]));
        $this->assertSame(1, Artisan::call('locations:import-australia', ['file' => $this->csv.'.missing']));
    }

    public function test_states_endpoint_lists_all_with_deliverable_flag(): void
    {
        $r = $this->getJson('/api/locations/states')->assertOk();
        $this->assertCount(9, $r->json('data'));
        $this->assertSame(['id', 'code', 'name', 'deliverable'], array_keys($r->json('data.0')));
        $this->assertFalse(collect($r->json('data'))->firstWhere('code', 'OT')['deliverable']);
        $this->assertTrue(collect($r->json('data'))->firstWhere('code', 'NT')['deliverable']);
        $this->assertStringContainsString('public', $r->headers->get('Cache-Control'));
        $this->assertNull($r->headers->get('Set-Cookie'));
    }

    public function test_suburbs_are_filtered_by_state_and_searched(): void
    {
        $vic = $this->stateId('VIC');
        $r = $this->getJson("/api/locations/suburbs?state_id={$vic}")->assertOk();
        $this->assertEqualsCanonicalizing(['Fitzroy', 'Melbourne'], array_column($r->json('data'), 'name'));
        $this->assertSame(['id', 'name', 'state_id', 'state', 'postcodes'], array_keys($r->json('data.0')));

        $sa = $this->stateId('SA');
        $this->assertSame(['5082'], $this->getJson("/api/locations/suburbs?state_id={$sa}&search=fitz")->json('data.0.postcodes'));
        $this->assertSame('VIC', $this->getJson("/api/locations/suburbs?state_id={$vic}&search=FITZ")->json('data.0.state'));
        $this->assertSame([], $this->getJson("/api/locations/suburbs?state_id={$vic}&search=Perth")->json('data'), 'other-state suburbs never appear');
        $this->assertSame(['Melbourne'], array_column($this->getJson("/api/locations/suburbs?state_id={$vic}&search=3004")->json('data'), 'name'));
        $this->assertSame(['3000', '3004'], $this->getJson("/api/locations/suburbs?state_id={$vic}&search=melb")->json('data.0.postcodes'));
        $this->assertSame([], $this->getJson("/api/locations/suburbs?state_id={$vic}&search=%25")->json('data'), 'LIKE wildcards are escaped');
    }

    public function test_suburbs_paginate(): void
    {
        $nsw = $this->stateId('NSW');
        $p1 = $this->getJson("/api/locations/suburbs?state_id={$nsw}&per_page=2&page=1")->assertOk();
        $this->assertCount(2, $p1->json('data'));
        $this->assertTrue($p1->json('meta.has_more'));
        $p2 = $this->getJson("/api/locations/suburbs?state_id={$nsw}&per_page=2&page=2");
        $this->assertCount(1, $p2->json('data'));
        $this->assertFalse($p2->json('meta.has_more'));
    }

    public function test_invalid_ids_are_rejected(): void
    {
        $this->getJson('/api/locations/suburbs')->assertStatus(422);
        $this->getJson('/api/locations/suburbs?state_id=abc')->assertStatus(422);
        $this->getJson('/api/locations/suburbs?state_id=999')->assertStatus(422);
        $this->getJson('/api/locations/suburbs?state_id[]=1')->assertStatus(422);
        $this->getJson('/api/locations/postcodes')->assertStatus(422);
        $this->getJson('/api/locations/postcodes?suburb_id=0')->assertStatus(422);
        $this->getJson('/api/locations/postcodes?suburb_id=99999999')->assertNotFound();
    }

    public function test_postcodes_endpoint(): void
    {
        $id = $this->suburbId('Melbourne', 'VIC');
        $this->getJson("/api/locations/postcodes?suburb_id={$id}")->assertOk()->assertExactJson(['data' => [['postcode' => '3000'], ['postcode' => '3004']], 'meta' => ['suburb_id' => $id]]);
        $nt = $this->suburbId('Darwin City', 'NT');
        $this->assertSame('0800', $this->getJson("/api/locations/postcodes?suburb_id={$nt}")->json('data.0.postcode'));
    }

    public function test_api_is_unavailable_without_data(): void
    {
        DB::table('au_localities')->delete();
        Locations::flushCache();
        $this->getJson('/api/locations/states')->assertStatus(503);
    }

    /* ── validation + integration ── */

    private function addressBody(array $o = []): array
    {
        return $o + ['addressType' => 'Home', 'fullName' => 'Jane Citizen', 'phone' => '0412 345 678', 'line1' => '12 Smith St', 'suburb' => 'Fitzroy', 'state' => 'VIC', 'postcode' => '3065'];
    }

    public function test_account_locations_validate_against_the_dataset(): void
    {
        $this->withSession($this->signedInSession($this->makeCustomer('loc1@example.test')));
        $this->postJson('/account/addresses', $this->addressBody())->assertOk();
        $this->postJson('/account/addresses', $this->addressBody(['suburb' => 'fitzroy']))->assertOk();                 // case-insensitive
        $this->postJson('/account/addresses', $this->addressBody(['suburb' => 'Perth']))->assertStatus(422);            // another state's suburb
        $this->postJson('/account/addresses', $this->addressBody(['postcode' => '3000']))->assertStatus(422);            // postcode of a different suburb
        $this->postJson('/account/addresses', $this->addressBody(['suburb' => 'Nowhereville']))->assertStatus(422);
        $this->postJson('/account/addresses', $this->addressBody(['state' => 'OT', 'suburb' => 'Christmas Island', 'postcode' => '6798']))->assertStatus(422); // not delivered
        $this->postJson('/account/addresses', $this->addressBody(['suburb' => 'Darwin City', 'state' => 'NT', 'postcode' => '0800']))->assertOk();
    }

    public function test_edit_location_is_validated_and_existing_free_text_address_stays_usable(): void
    {
        $cid = $this->makeCustomer('loc2@example.test');
        $this->withSession($this->signedInSession($cid));
        $old = DB::table('customer_addresses')->insertGetId(['customer_id' => $cid, 'label' => 'Home', 'recipient_name' => 'Old Timer', 'phone' => '0412345678',
            'street_address' => '1 Legacy Rd', 'suburb' => 'Madeupton', 'state' => 'VIC', 'postcode' => '3999', 'is_default' => 1]);
        $this->getJson('/account/addresses')->assertOk()->assertJsonPath('0.suburb', 'Madeupton');                      // still listed untouched
        $this->putJson("/account/addresses/{$old}", $this->addressBody(['suburb' => 'Madeupton', 'postcode' => '3999']))->assertStatus(422);
        $this->putJson("/account/addresses/{$old}", $this->addressBody())->assertOk();
        $this->assertSame('Fitzroy', DB::table('customer_addresses')->where('id', $old)->value('suburb'));
    }

    public function test_guests_cannot_use_saved_locations(): void
    {
        $this->getJson('/account/addresses')->assertUnauthorized();
        $this->postJson('/account/addresses', $this->addressBody())->assertUnauthorized();
        $this->putJson('/account/addresses/1', $this->addressBody())->assertUnauthorized();
    }

    public function test_checkout_uses_saved_legacy_address_but_checks_typed_ones(): void
    {
        $cid = $this->makeCustomer('loc3@example.test');
        $this->withSession($this->signedInSession($cid));
        $saved = $this->makeAddress($cid); // fixture address, not necessarily in the fixture dataset: used as stored
        $base = ['items' => [['productId' => 2, 'quantity' => 1]], 'paymentMethod' => 'cod'];
        $this->postJson('/checkout/place', $base + ['addressId' => $saved])->assertCreated();

        $bad = ['fullName' => 'Jo Bloggs', 'phone' => '0412345678', 'street' => '5 Test St', 'suburb' => 'Perth', 'state' => 'VIC', 'postcode' => '3000'];
        $this->postJson('/checkout/place', $base + ['address' => $bad])->assertStatus(422);
        $this->postJson('/checkout/place', $base + ['address' => ['suburb' => 'Melbourne', 'postcode' => '3004'] + $bad])->assertCreated();
        $this->assertSame('3004', DB::table('orders')->where('customer_id', $cid)->orderByDesc('id')->value('shipping_postcode'));
    }

    public function test_address_fields_component_renders_central_and_legacy_modes(): void
    {
        $render = fn () => (string) view('components.address-fields', ['prefix' => 'addr', 'streetId' => 'addr-street', 'variant' => 'form', 'line2Id' => null, 'streetLabel' => 'Street Address', 'attributes' => new ComponentAttributeBag]);
        $central = $render();
        $this->assertStringContainsString('data-mode="central"', $central);
        $this->assertStringContainsString('data-cb="state"', $central);
        $this->assertStringContainsString('disabled placeholder="Choose a state first"', $central); // suburb starts disabled
        $this->assertMatchesRegularExpression('/id="addr-street-group"\s+hidden/', $central);               // street appears after a suburb is chosen
        $this->assertStringContainsString('name="addr-type"', $central);

        DB::table('au_localities')->delete();
        Locations::flushCache();
        $legacy = $render();
        $this->assertStringContainsString('data-mode="legacy"', $legacy);
        $this->assertStringContainsString('<select id="addr-state"', $legacy);
    }
}
