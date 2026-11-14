<?php

namespace Tests\Feature;

use App\Support\ConfigDataException;
use App\Support\Shipping;
use App\Support\StoreConfig;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/** App\Support\StoreConfig: admin-managed config_data values, never a silent fallback. */
class StoreConfigTest extends TestCase
{
    private array $rows = [];

    private bool $mocked = false;

    /** Serve these raw `data` strings as the config_data rows (key => JSON text). */
    private function rows(array $rows): void
    {
        StoreConfig::fake(null);   // also forgets values loaded so far
        $this->rows = $rows;
        if (! $this->mocked) {
            $query = Mockery::mock();
            $query->shouldReceive('whereIn')->with('key', array_keys(StoreConfig::KEYS))->andReturnSelf();
            $query->shouldReceive('pluck')->with('data', 'key')->andReturnUsing(fn () => collect($this->rows));
            DB::shouldReceive('table')->with('config_data')->andReturn($query);
            $this->mocked = true;
        }
    }

    public function test_values_are_decoded_from_json_and_typed(): void
    {
        $this->rows([
            'shipping_fee_adelaide' => '5', 'free_shipping_threshold' => 'null', 'pickup_enabled' => 'true',
            'payment_methods' => '["card", "cod"]',
            'pickup_location' => '{"label": "Free Pickup – West Croydon", "suburb": "West Croydon", "state": "SA", "postcode": "5008", "details": null}',
        ]);

        $this->assertSame(5.0, StoreConfig::get('shipping_fee_adelaide'));
        $this->assertNull(StoreConfig::get('free_shipping_threshold'));
        $this->assertFalse(Shipping::freeDelivery(10000));   // null threshold = free delivery switched off
        $this->assertTrue(StoreConfig::get('pickup_enabled'));
        $this->assertSame(['card', 'cod'], StoreConfig::get('payment_methods'));
        $this->assertSame('West Croydon', Shipping::pickup()['suburb']);
    }

    public function test_a_missing_required_key_is_an_error_not_a_default(): void
    {
        $this->rows(['shipping_fee_adelaide' => '5']);

        $this->expectException(ConfigDataException::class);
        $this->expectExceptionMessage('Required configuration "shipping_fee_australia" is missing from the config_data table.');
        Shipping::fee(Shipping::AUSTRALIA);
    }

    public function test_invalid_json_and_wrong_shapes_are_errors(): void
    {
        $cases = [
            'online_payment_balance_terms' => ['Pay later', 'not valid JSON'],
            'online_payment_deposit_percent' => ['130', 'expected a number from 0 to 100'],
            'shipping_fee_adelaide' => ['"5"', 'expected a number'],
            'gift_wrap_base_items' => ['2.5', 'expected a whole number'],
            'pickup_location' => ['{"label": "Pickup"}', 'missing the field "suburb"'],
            'return_reasons' => ['[1, 2]', 'expected a JSON list of strings'],
        ];
        foreach ($cases as $key => [$data, $message]) {
            $this->rows([$key => $data]);
            try {
                StoreConfig::get($key);
                $this->fail("{$key} = {$data} was accepted");
            } catch (ConfigDataException $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $key);
                $this->assertStringContainsString($key, $e->getMessage());
            }
        }
    }

    public function test_a_missing_table_is_a_clear_error(): void
    {
        StoreConfig::fake(null);   // the in-memory / test database has no config_data table

        try {
            StoreConfig::get('shipping_fee_adelaide');
            $this->fail('No error without the config_data table');
        } catch (ConfigDataException $e) {
            $this->assertTrue($e->tableUnreadable);
            $this->assertStringContainsString('could not be read from the config_data table', $e->getMessage());
        }
    }

    public function test_unknown_keys_are_rejected(): void
    {
        $this->expectException(ConfigDataException::class);
        StoreConfig::get('shipping_flat');
    }
}
