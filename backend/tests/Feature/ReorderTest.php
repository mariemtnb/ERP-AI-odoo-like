<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\ReorderRule;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReorderService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReorderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->supplier = Supplier::create(['name' => 'ACME']);
    }

    private function product(string $sku, float $stock, float $cost = 5): Product
    {
        $p = Product::create(['sku' => $sku, 'name' => $sku, 'sale_price' => 10, 'cost_price' => $cost]);
        if ($stock > 0) {
            StockService::recordMovement(productId: $p->id, movementType: StockMovement::TYPE_IN, quantity: $stock, user: $this->user, reason: 'seed');
        }

        return $p->refresh();
    }

    private function rule(Product $p, float $min, float $qty, ?int $supplier, bool $active = true): ReorderRule
    {
        return ReorderRule::create([
            'product_id' => $p->id, 'supplier_id' => $supplier, 'min_qty' => $min,
            'reorder_qty' => $qty, 'is_active' => $active, 'created_by' => $this->user->id,
        ]);
    }

    public function test_product_below_reorder_point_is_suggested(): void
    {
        $low = $this->product('LOW', 5);
        $this->rule($low, 10, 40, $this->supplier->id);

        $s = ReorderService::suggestions();
        $this->assertCount(1, $s);
        $this->assertEquals($low->id, $s[0]['product']);
        $this->assertEquals(40.0, $s[0]['reorder_qty']);
    }

    public function test_product_above_reorder_point_is_not_suggested(): void
    {
        $ok = $this->product('OK', 100);
        $this->rule($ok, 10, 40, $this->supplier->id);

        $this->assertCount(0, ReorderService::suggestions());
    }

    public function test_inactive_rule_is_ignored(): void
    {
        $low = $this->product('LOW', 1);
        $this->rule($low, 10, 40, $this->supplier->id, active: false);

        $this->assertCount(0, ReorderService::suggestions());
    }

    public function test_run_creates_one_draft_po_per_supplier_with_reorder_qty(): void
    {
        $a = $this->product('A', 2, cost: 4);
        $b = $this->product('B', 0, cost: 7);
        $this->rule($a, 10, 30, $this->supplier->id);
        $this->rule($b, 5, 50, $this->supplier->id);

        $result = ReorderService::generateDraftPurchaseOrders($this->user);

        $this->assertCount(1, $result['orders']); // both from same supplier -> one PO
        $po = $result['orders'][0];
        $this->assertEquals(PurchaseOrder::STATUS_DRAFT, $po->status);
        $this->assertEquals(2, $po->lines->count());
        // total = 30*4 + 50*7 = 120 + 350 = 470
        $this->assertEquals('470.00', $po->total_amount);
    }

    public function test_rules_without_supplier_are_reported_unassigned(): void
    {
        $low = $this->product('NOSUP', 1);
        $this->rule($low, 10, 20, null);

        $result = ReorderService::generateDraftPurchaseOrders($this->user);
        $this->assertCount(0, $result['orders']);
        $this->assertCount(1, $result['unassigned']);
        $this->assertEquals($low->id, $result['unassigned'][0]['product']);
    }

    public function test_run_endpoint_and_rbac(): void
    {
        $low = $this->product('HTTP', 1);
        $this->rule($low, 10, 25, $this->supplier->id);

        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/reorder-run')
            ->assertOk()
            ->assertJsonPath('created.0.lines', 1);

        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')->getJson('/api/v1/reorder-suggestions')->assertForbidden();
    }
}
