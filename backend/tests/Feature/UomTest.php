<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTransition;
use App\Models\FeatureFlag;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\UomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Units of measure and conversion. */
class UomTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureFlag::flush();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
    }

    public function test_conversion_within_a_category(): void
    {
        $this->assertEqualsWithDelta(2000, UomService::convert(2, 'kg', 'g'), 0.0001);
        $this->assertEqualsWithDelta(0.5, UomService::convert(500, 'g', 'kg'), 0.0001);
        $this->assertEqualsWithDelta(36, UomService::convert(3, 'dozen', 'unit'), 0.0001);
        $this->assertEqualsWithDelta(2, UomService::convert(48, 'unit', 'box'), 0.0001); // box of 24
    }

    public function test_conversion_across_categories_is_rejected(): void
    {
        $this->expectException(InvalidTransition::class);
        UomService::convert(1, 'kg', 'L');
    }

    public function test_the_convert_endpoint_works(): void
    {
        $this->actingAs($this->manager, 'api')->getJson('/api/v1/units/convert?qty=1.5&from=kg&to=g')
            ->assertOk()->assertJsonPath('result', 1500);
    }

    public function test_a_new_unit_can_be_defined_and_used(): void
    {
        $this->actingAs($this->manager, 'api')->postJson('/api/v1/units', [
            'code' => 'crate', 'name' => 'Crate (6)', 'category' => 'unit', 'factor' => 6,
        ])->assertStatus(201);

        $this->assertEqualsWithDelta(18, UomService::convert(3, 'crate', 'unit'), 0.0001);
    }

    public function test_a_product_can_carry_a_unit_of_measure(): void
    {
        $kg = UnitOfMeasure::where('code', 'kg')->first();
        $res = $this->actingAs($this->manager, 'api')->postJson('/api/v1/products', [
            'sku' => 'FLOUR', 'name' => 'Flour', 'cost_price' => 2, 'sale_price' => 3, 'uom_id' => $kg->id,
        ])->assertStatus(201)->assertJsonPath('uom_code', 'kg');

        $this->assertSame($kg->id, Product::find($res->json('id'))->uom_id);
    }

    public function test_the_module_is_absent_when_the_flag_is_off(): void
    {
        FeatureFlag::updateOrCreate(['key' => 'uom'], ['enabled' => false]);
        FeatureFlag::flush();
        $this->actingAs($this->manager, 'api')->getJson('/api/v1/units')->assertStatus(404);
    }
}
