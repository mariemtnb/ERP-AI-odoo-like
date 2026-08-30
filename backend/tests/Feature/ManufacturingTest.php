<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStock;
use App\Exceptions\InvalidTransition;
use App\Models\BillOfMaterials;
use App\Models\BomComponent;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\ManufacturingService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManufacturingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $finished;

    private Product $flour;

    private Product $sugar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->finished = Product::create(['sku' => 'CAKE', 'name' => 'Cake', 'sale_price' => 20, 'cost_price' => 8]);
        $this->flour = Product::create(['sku' => 'FLOUR', 'name' => 'Flour', 'sale_price' => 2, 'cost_price' => 1]);
        $this->sugar = Product::create(['sku' => 'SUGAR', 'name' => 'Sugar', 'sale_price' => 3, 'cost_price' => 1]);
    }

    private function stock(Product $p): float
    {
        return (float) $p->refresh()->quantity_in_stock;
    }

    private function seedStock(Product $p, float $q): void
    {
        StockService::recordMovement(productId: $p->id, movementType: StockMovement::TYPE_IN, quantity: $q, user: $this->user, reason: 'seed');
    }

    /** BOM: 1 batch = 2 cakes, using 3 flour + 1 sugar. */
    private function bom(): BillOfMaterials
    {
        $bom = BillOfMaterials::create(['product_id' => $this->finished->id, 'output_quantity' => 2, 'created_by' => $this->user->id]);
        BomComponent::create(['bom_id' => $bom->id, 'component_product_id' => $this->flour->id, 'quantity' => 3]);
        BomComponent::create(['bom_id' => $bom->id, 'component_product_id' => $this->sugar->id, 'quantity' => 1]);

        return $bom->load('components.component');
    }

    public function test_requirements_scale_with_quantity(): void
    {
        $bom = $this->bom();
        // to make 6 cakes (3 batches): 9 flour, 3 sugar
        $req = collect(ManufacturingService::requirements($bom, 6))->keyBy('component');
        $this->assertEquals(9.0, $req[$this->flour->id]['required']);
        $this->assertEquals(3.0, $req[$this->sugar->id]['required']);
    }

    public function test_completing_consumes_components_and_produces_output(): void
    {
        $this->seedStock($this->flour, 20);
        $this->seedStock($this->sugar, 10);
        $bom = $this->bom();

        $wo = ManufacturingService::createWorkOrder($bom, 6, $this->user); // 6 cakes
        ManufacturingService::complete($wo, $this->user);

        $this->assertEquals(WorkOrder::STATUS_DONE, $wo->refresh()->status);
        $this->assertEquals(11.0, $this->stock($this->flour)); // 20 - 9
        $this->assertEquals(7.0, $this->stock($this->sugar));  // 10 - 3
        $this->assertEquals(6.0, $this->stock($this->finished)); // produced 6
    }

    public function test_completing_rolls_back_when_a_component_is_short(): void
    {
        $this->seedStock($this->flour, 5); // not enough (needs 9 for 6 cakes)
        $this->seedStock($this->sugar, 10);
        $bom = $this->bom();
        $wo = ManufacturingService::createWorkOrder($bom, 6, $this->user);

        try {
            ManufacturingService::complete($wo, $this->user);
            $this->fail('Expected InsufficientStock.');
        } catch (InsufficientStock) {
            $this->assertEquals(5.0, $this->stock($this->flour));   // untouched
            $this->assertEquals(10.0, $this->stock($this->sugar));  // untouched
            $this->assertEquals(0.0, $this->stock($this->finished)); // nothing produced
            $this->assertNotEquals(WorkOrder::STATUS_DONE, $wo->refresh()->status);
        }
    }

    public function test_cannot_complete_twice(): void
    {
        $this->seedStock($this->flour, 20);
        $this->seedStock($this->sugar, 10);
        $wo = ManufacturingService::createWorkOrder($this->bom(), 2, $this->user);
        ManufacturingService::complete($wo, $this->user);

        $this->expectException(InvalidTransition::class);
        ManufacturingService::complete($wo->refresh(), $this->user);
    }

    public function test_cancel_and_cannot_cancel_completed(): void
    {
        $this->seedStock($this->flour, 20);
        $this->seedStock($this->sugar, 10);
        $wo = ManufacturingService::createWorkOrder($this->bom(), 2, $this->user);
        ManufacturingService::cancel($wo);
        $this->assertEquals(WorkOrder::STATUS_CANCELLED, $wo->refresh()->status);

        $wo2 = ManufacturingService::createWorkOrder(BillOfMaterials::first(), 2, $this->user);
        ManufacturingService::complete($wo2, $this->user);
        $this->expectException(InvalidTransition::class);
        ManufacturingService::cancel($wo2->refresh());
    }

    public function test_http_flow_and_rbac(): void
    {
        $this->seedStock($this->flour, 20);
        $this->seedStock($this->sugar, 10);

        $this->actingAs($this->user, 'api')->postJson('/api/v1/boms', [
            'product' => $this->finished->id, 'output_quantity' => 2,
            'components' => [
                ['component' => $this->flour->id, 'quantity' => 3],
                ['component' => $this->sugar->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        $bom = BillOfMaterials::first();
        $wo = $this->actingAs($this->user, 'api')->postJson('/api/v1/work-orders', ['bom' => $bom->id, 'quantity' => 4])
            ->assertCreated()->json();

        $this->actingAs($this->user, 'api')->postJson("/api/v1/work-orders/{$wo['id']}/complete")
            ->assertOk()->assertJsonPath('status', 'done');
        $this->assertEquals(4.0, $this->stock($this->finished));

        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')->postJson('/api/v1/work-orders', ['bom' => $bom->id, 'quantity' => 1])->assertForbidden();
    }
}
