<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\LandedCost;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DocumentService;
use App\Support\AccountMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Landed costs capitalised onto a received purchase order. */
class LandedCostTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Product $a;
    private Product $b;

    protected function setUp(): void
    {
        parent::setUp();
        AccountMap::flush();
        FeatureFlag::flush();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        Warehouse::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main']);
        $this->a = Product::create(['sku' => 'A', 'name' => 'Cheap', 'sale_price' => 20, 'cost_price' => 10, 'unit' => 'pcs']);
        $this->b = Product::create(['sku' => 'B', 'name' => 'Dear', 'sale_price' => 80, 'cost_price' => 40, 'unit' => 'pcs']);
    }

    /** A received order: A 10 @ 10 (=100), B 10 @ 40 (=400). */
    private function receivedOrder(bool $receive = true): PurchaseOrder
    {
        $s = Supplier::firstOrCreate(['name' => 'S']);
        $po = PurchaseOrder::create(['number' => 'PO-'.uniqid(), 'supplier_id' => $s->id,
            'status' => PurchaseOrder::STATUS_CONFIRMED, 'order_date' => '2026-01-01', 'created_by' => $this->manager->id]);
        PurchaseOrderLine::create(['purchase_order_id' => $po->id, 'product_id' => $this->a->id, 'quantity' => 10, 'unit_price' => 10]);
        PurchaseOrderLine::create(['purchase_order_id' => $po->id, 'product_id' => $this->b->id, 'quantity' => 10, 'unit_price' => 40]);
        $po->load('lines')->recomputeTotal();

        if ($receive) {
            DocumentService::receivePurchase($po->refresh(), $this->manager);
        }

        return $po->refresh();
    }

    private function balance(string $code): float
    {
        return (float) DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('a.code', $code)->sum(DB::raw('l.debit - l.credit'));
    }

    public function test_a_value_weighted_landed_cost_capitalises_into_inventory(): void
    {
        $po = $this->receivedOrder();
        $this->assertEqualsWithDelta(500, $this->balance('1200'), 0.001); // inventory after receipt

        $res = $this->actingAs($this->manager, 'api')->postJson("/api/v1/purchases/{$po->id}/landed-costs", [
            'description' => 'Sea freight', 'amount' => 100, 'allocation' => 'value',
        ])->assertStatus(201);

        // 100 split by value 100:400 → 20 to A, 80 to B.
        $alloc = collect($res->json('allocations'))->keyBy('product_id');
        $this->assertEqualsWithDelta(20, (float) $alloc[$this->a->id]['amount'], 0.001);
        $this->assertEqualsWithDelta(80, (float) $alloc[$this->b->id]['amount'], 0.001);

        // AVCO rises: A 10 + 20/10 = 12; B 40 + 80/10 = 48.
        $this->assertEqualsWithDelta(12, (float) $this->a->refresh()->avg_cost, 0.001);
        $this->assertEqualsWithDelta(48, (float) $this->b->refresh()->avg_cost, 0.001);

        // Ledger: inventory +100 (→600). Payable was already −500 from the
        // receipt (Dr Inventory / Cr AP); the landed cost adds another −100.
        $this->assertEqualsWithDelta(600, $this->balance('1200'), 0.001);
        $this->assertEqualsWithDelta(-600, $this->balance('2000'), 0.001);
    }

    public function test_a_quantity_weighted_landed_cost_splits_evenly(): void
    {
        $po = $this->receivedOrder();

        $this->actingAs($this->manager, 'api')->postJson("/api/v1/purchases/{$po->id}/landed-costs", [
            'description' => 'Customs duty', 'amount' => 100, 'allocation' => 'quantity',
        ])->assertStatus(201);

        // Equal quantities (10 and 10) → 50 each → both averages rise by 5.
        $this->assertEqualsWithDelta(15, (float) $this->a->refresh()->avg_cost, 0.001);
        $this->assertEqualsWithDelta(45, (float) $this->b->refresh()->avg_cost, 0.001);
    }

    public function test_allocations_always_sum_to_the_landed_amount(): void
    {
        $po = $this->receivedOrder();

        $res = $this->actingAs($this->manager, 'api')->postJson("/api/v1/purchases/{$po->id}/landed-costs", [
            'description' => 'Odd freight', 'amount' => 10, 'allocation' => 'quantity', // 5 + 5, but proves the sum invariant
        ])->assertStatus(201);

        $sum = collect($res->json('allocations'))->sum(fn ($a) => (float) $a['amount']);
        $this->assertEqualsWithDelta(10, $sum, 0.0001);
        $this->assertEqualsWithDelta(10, (float) LandedCost::first()->amount, 0.001);
    }

    public function test_it_is_rejected_on_an_order_with_nothing_received(): void
    {
        $po = $this->receivedOrder(receive: false); // confirmed, not received

        $this->actingAs($this->manager, 'api')->postJson("/api/v1/purchases/{$po->id}/landed-costs", [
            'description' => 'Freight', 'amount' => 100,
        ])->assertStatus(422);
    }

    public function test_the_module_is_absent_when_the_flag_is_off(): void
    {
        $po = $this->receivedOrder();
        FeatureFlag::updateOrCreate(['key' => 'landed_costs'], ['enabled' => false]);
        FeatureFlag::flush();

        $this->actingAs($this->manager, 'api')->postJson("/api/v1/purchases/{$po->id}/landed-costs", [
            'description' => 'Freight', 'amount' => 100,
        ])->assertStatus(404);
    }

    public function test_an_ordinary_employee_cannot_add_landed_costs(): void
    {
        $po = $this->receivedOrder();
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);

        $this->actingAs($employee, 'api')->postJson("/api/v1/purchases/{$po->id}/landed-costs", [
            'description' => 'Freight', 'amount' => 100,
        ])->assertStatus(403);
    }
}
