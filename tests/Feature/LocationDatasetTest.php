<?php

namespace Tests\Feature;

use App\Models\Locations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/** The shipped dataset (database/data/locations/au-localities.csv) imports completely and covers every state. */
class LocationDatasetTest extends TestCase
{
    use DatabaseTransactions, StorefrontFixtures;

    public function test_shipped_dataset_imports_and_covers_all_regions(): void
    {
        $this->requireMysql();
        $this->assertSame(0, Artisan::call('locations:import-australia'));

        $this->assertSame(9, DB::table('au_states')->count());
        $this->assertGreaterThan(15000, DB::table('au_localities')->count());
        $this->assertGreaterThanOrEqual(DB::table('au_localities')->count(), DB::table('au_locality_postcodes')->count());
        $perState = DB::table('au_localities as l')->join('au_states as s', 's.id', '=', 'l.state_id')->groupBy('s.code')->selectRaw('s.code, count(*) n')->pluck('n', 'code');
        foreach (['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA', 'OT'] as $code) {
            $this->assertGreaterThan(0, $perState[$code] ?? 0, "no localities for {$code}");
        }
        // metropolitan, regional, remote, leading-zero postcode and Other Territories
        foreach ([['NSW', 'Sydney', '2000'], ['VIC', 'Melbourne', '3000'], ['QLD', 'Brisbane City', '4000'], ['SA', 'Adelaide', '5000'], ['WA', 'Perth', '6000'],
            ['TAS', 'Hobart', '7000'], ['ACT', 'Braddon', '2612'], ['NT', 'Darwin City', '0800'], ['QLD', 'Mount Isa', '4825'], ['WA', 'Kalgoorlie', '6430'],
            ['NT', 'Alice Springs', '0870'], ['OT', 'Christmas Island', '6798']] as [$state, $suburb, $pc]) {
            $this->assertTrue(Locations::matches($state, $suburb, $pc), "{$suburb} {$state} {$pc}");
        }
        $this->assertFalse(Locations::matches('NSW', 'Melbourne', '3000'));
        $this->assertSame(0, DB::table('au_locality_postcodes')->whereRaw('postcode NOT REGEXP "^[0-9]{4}$"')->count());
        $this->assertGreaterThan(0, DB::table('au_locality_postcodes')->where('postcode', 'like', '0%')->count());
        $this->assertGreaterThan(0, DB::table('au_locality_postcodes')->groupBy('locality_id')->havingRaw('count(*) > 1')->select('locality_id')->get()->count(), 'some suburbs have several postcodes');

        $first = DB::table('au_localities')->count();
        $this->assertSame(0, Artisan::call('locations:import-australia'));
        $this->assertSame($first, DB::table('au_localities')->count(), 're-import adds no duplicates');
        $this->assertSame(0, (int) DB::table('au_location_imports')->orderByDesc('id')->value('inserted'));
    }
}
