<?php

namespace Tests\Feature;

use App\Models\BillOfMaterials;
use App\Models\BomComponent;
use App\Models\Product;
use App\Models\User;
use App\Models\WorkCentre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Work centres, BOM routings and multi-level MRP. */
class ManufacturingPlanningTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::create(['id' => 1, 'email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
    }

    private function product(string $sku, float $stock = 0): Product
    {
        $p = Product::create(['sku' => $sku, 'name' => $sku, 'cost_price' => 1, 'sale_price' => 2, 'unit' => 'unit']);
        if ($stock) {
            $p->quantity_in_stock = $stock;
            $p->save();
        }

        return $p->refresh();
    }

    private function bom(Product $product, array $components): BillOfMaterials
    {
        $bom = BillOfMaterials::create(['product_id' => $product->id, 'output_quantity' => 1, 'created_by' => 1]);
        foreach ($components as [$comp, $qty]) {
            BomComponent::create(['bom_id' => $bom->id, 'component_product_id' => $comp->id, 'quantity' => $qty]);
        }

        return $bom;
    }

    public function test_mrp_explodes_multiple_levels_and_nets_stock(): void
    {
        $finished = $this->product('FINISHED');
        $sub = $this->product('SUB', stock: 4);   // 4 already on hand
        $plastic = $this->product('PLASTIC');
        $screw = $this->product('SCREW');
        $this->bom($finished, [[$sub, 2], [$screw, 10]]);
        $this->bom($sub, [[$plastic, 1]]);

        $res = $this->actingAs($this->manager, 'api')->getJson("/api/v1/products/{$finished->id}/mrp?qty=5")->assertOk();

        $buy = collect($res->json('to_purchase'))->keyBy('product_id');
        $make = collect($res->json('to_manufacture'))->keyBy('product_id');

        // Need 10 SUB, 4 on hand → make 6; each SUB needs 1 PLASTIC → buy 6.
        $this->assertEqualsWithDelta(6, $make[$sub->id]['net'], 0.001);
        $this->assertEqualsWithDelta(6, $buy[$plastic->id]['net'], 0.001);
        // 10 SCREW per FINISHED × 5 = 50, none in stock.
        $this->assertEqualsWithDelta(50, $buy[$screw->id]['net'], 0.001);
    }

    public function test_a_cyclic_bom_does_not_loop_forever(): void
    {
        $a = $this->product('A');
        $b = $this->product('B');
        $this->bom($a, [[$b, 1]]);
        $this->bom($b, [[$a, 1]]);   // cycle

        $res = $this->actingAs($this->manager, 'api')->getJson("/api/v1/products/{$a->id}/mrp?qty=1")->assertOk();
        // It terminates; the second time A is reached it is treated as buy, not exploded.
        $this->assertNotEmpty($res->json('lines'));
    }

    public function test_routing_cost_scales_operations_to_quantity(): void
    {
        $finished = $this->product('WIDGET');
        $bom = $this->bom($finished, []);
        $assembly = WorkCentre::where('code', 'ASSEMBLY')->first(); // 30/hr
        $packing = WorkCentre::where('code', 'PACKING')->first();   // 18/hr

        $this->actingAs($this->manager, 'api')->postJson("/api/v1/boms/{$bom->id}/operations",
            ['name' => 'Assemble', 'sequence' => 1, 'work_centre_id' => $assembly->id, 'minutes' => 12])->assertStatus(201);
        $this->actingAs($this->manager, 'api')->postJson("/api/v1/boms/{$bom->id}/operations",
            ['name' => 'Pack', 'sequence' => 2, 'work_centre_id' => $packing->id, 'minutes' => 6])->assertStatus(201);

        $res = $this->actingAs($this->manager, 'api')->getJson("/api/v1/boms/{$bom->id}/routing-cost?qty=5")->assertOk();

        // 12×5=60 min → 30 TND; 6×5=30 min → 9 TND. Total 90 min, 39 TND.
        $this->assertEqualsWithDelta(90, $res->json('total_minutes'), 0.01);
        $this->assertEqualsWithDelta(39, $res->json('labour_cost'), 0.01);
    }

    public function test_work_centres_are_seeded_and_can_be_created(): void
    {
        $this->actingAs($this->manager, 'api')->getJson('/api/v1/work-centres')->assertOk()->assertJsonCount(2);

        $this->actingAs($this->manager, 'api')->postJson('/api/v1/work-centres',
            ['code' => 'PAINT', 'name' => 'Paint booth', 'cost_per_hour' => 25])->assertStatus(201);
        $this->actingAs($this->manager, 'api')->getJson('/api/v1/work-centres')->assertJsonCount(3);
    }

    public function test_an_ordinary_employee_cannot_add_a_work_centre(): void
    {
        $employee = User::create(['id' => 2, 'email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')->postJson('/api/v1/work-centres',
            ['code' => 'X', 'name' => 'X', 'cost_per_hour' => 1])->assertStatus(403);
    }
}
