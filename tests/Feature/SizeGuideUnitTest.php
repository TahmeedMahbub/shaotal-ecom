<?php

namespace Tests\Feature;

use App\Models\Catalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** In-memory SQLite with only the size guide tables (the real schema is SQL-first, see sql/). */
class SizeGuideUnitTest extends TestCase
{
    private function tables(bool $withUnit): void
    {
        Schema::create('size_guides', function ($t) use ($withUnit) {
            $t->id();
            $t->string('title');
            $t->string('key')->unique();
            $t->text('customer_details')->nullable();
            if ($withUnit) {
                $t->string('measurement_unit')->default('in');
            }
            $t->string('status')->default('active');
        });
        Schema::create('size_guide_attributes', function ($t) {
            $t->id();
            $t->unsignedBigInteger('size_guide_id');
            $t->string('attribute_name');
            $t->string('unit')->default('cm');
            $t->integer('sort_order')->default(0);
        });
        Schema::create('size_guide_values', function ($t) {
            $t->id();
            $t->unsignedBigInteger('size_guide_id');
            $t->string('size_label');
            $t->unsignedBigInteger('attribute_id');
            $t->decimal('measurement_value', 10, 2);
            $t->integer('sort_order')->default(0);
        });
    }

    private function seed(array $guide, string $attrUnit, bool $withValue = true): void
    {
        DB::table('size_guides')->insert($guide + ['title' => 'T', 'key' => 'k']);
        DB::table('size_guide_attributes')->insert(['size_guide_id' => 1, 'attribute_name' => 'Chest', 'unit' => $attrUnit]);
        if ($withValue) {
            DB::table('size_guide_values')->insert(['size_guide_id' => 1, 'size_label' => 'M', 'attribute_id' => 1, 'measurement_value' => '38.25']);
        }
    }

    public function test_payload_has_guide_unit_and_the_stored_value_untouched(): void
    {
        $this->tables(true);
        $this->seed(['measurement_unit' => 'in'], 'in');

        $g = $this->getJson('/size-guides/k')->assertOk()->json();

        $this->assertSame('in', $g['unit']);
        $this->assertSame('38.25', $g['rows']['M'][1]);   // text, as stored
        $this->assertSame('in', $g['attributes'][0]['unit']);
    }

    public function test_cm_guide_keeps_cm(): void
    {
        $this->tables(true);
        $this->seed(['measurement_unit' => 'cm'], 'cm');

        $this->getJson('/size-guides/k')->assertOk()->assertJsonPath('unit', 'cm');
    }

    public function test_legacy_database_without_column_uses_attribute_unit(): void
    {
        $this->tables(false);
        $this->seed([], 'Inches');

        $this->getJson('/size-guides/k')->assertOk()->assertJsonPath('unit', 'in');
    }

    public function test_unknown_guide_or_guide_without_measurements_is_404(): void
    {
        $this->tables(true);
        $this->seed(['measurement_unit' => 'in'], 'in', false);

        $this->getJson('/size-guides/k')->assertNotFound();
        $this->getJson('/size-guides/missing')->assertNotFound();
    }

    public function test_range_values_stay_text_not_floats(): void
    {
        $this->tables(true);
        $this->seed(['measurement_unit' => 'cm'], 'cm', false);
        DB::table('size_guide_values')->insert(['size_guide_id' => 1, 'size_label' => 'M', 'attribute_id' => 1, 'measurement_value' => '71.1-76.2']);

        $this->getJson('/size-guides/k')->assertOk()->assertJsonPath('rows.M.1', '71.1-76.2');
    }

    public function test_cm_attributes_beat_a_default_inch_column(): void
    {
        $this->tables(true);
        $this->seed(['measurement_unit' => 'in'], 'cm');

        $this->getJson('/size-guides/k')->assertOk()->assertJsonPath('unit', 'cm')->assertJsonPath('attributes.0.unit', 'cm');
    }

    public function test_mixed_attribute_units_are_kept_per_attribute(): void
    {
        $this->tables(true);
        $this->seed(['measurement_unit' => 'in'], 'cm');
        DB::table('size_guide_attributes')->insert(['size_guide_id' => 1, 'attribute_name' => 'Length', 'unit' => 'inches']);

        $g = $this->getJson('/size-guides/k')->assertOk()->assertJsonPath('unit', 'in')->json();
        $this->assertSame(['cm', 'in'], array_column($g['attributes'], 'unit'));
    }

    public function test_normalize_unit(): void
    {
        $this->assertSame('in', Catalog::normalizeUnit(' Inches '));
        $this->assertSame('cm', Catalog::normalizeUnit('CM'));
        $this->assertNull(Catalog::normalizeUnit('kg'));
        $this->assertNull(Catalog::normalizeUnit(null));
    }
}
