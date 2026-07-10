<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStock;
use App\Exceptions\InvalidTransition;
use App\Models\Lead;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\DocumentService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create([
            'email' => 'a@t.t', 'password' => 'x', 'role' => 'admin',
        ]);
        $this->manager = User::create([
            'email' => 'm@t.t', 'password' => 'x', 'role' => 'manager',
        ]);
        $this->product = Product::create(['sku' => 'P-1', 'name' => 'P']);
    }

    // ---------- multi-warehouse ----------

    public function test_movement_tracks_warehouse_and_global_stock(): void
    {
        $main = Warehouse::defaultWarehouse();
        StockService::recordMovement(
            productId: $this->product->id, movementType: 'in',
            quantity: 10, user: $this->manager,
        );

        $this->assertEquals(10, (float) $this->product->refresh()->quantity_in_stock);
        $this->assertEquals(10, (float) WarehouseStock::where('warehouse_id', $main->id)
            ->where('product_id', $this->product->id)->value('quantity'));
    }

    public function test_transfer_moves_stock_between_warehouses(): void
    {
        $main = Warehouse::defaultWarehouse();
        $annex = Warehouse::create(['name' => 'Annex']);
        StockService::recordMovement(
            productId: $this->product->id, movementType: 'in',
            quantity: 10, user: $this->manager,
        );

        StockService::transfer(
            productId: $this->product->id,
            fromWarehouseId: $main->id, toWarehouseId: $annex->id,
            quantity: 4, user: $this->manager,
        );

        $qty = fn ($w) => (float) WarehouseStock::where('warehouse_id', $w->id)
            ->where('product_id', $this->product->id)->value('quantity');
        $this->assertEquals(6, $qty($main));
        $this->assertEquals(4, $qty($annex));
        // Global stock unchanged by a transfer.
        $this->assertEquals(10, (float) $this->product->refresh()->quantity_in_stock);
    }

    public function test_warehouse_oversell_rejected_even_if_other_warehouse_has_stock(): void
    {
        $annex = Warehouse::create(['name' => 'Annex']);
        StockService::recordMovement(
            productId: $this->product->id, movementType: 'in',
            quantity: 10, user: $this->manager, warehouseId: $annex->id,
        );

        $this->expectException(InsufficientStock::class);
        // Default warehouse holds 0 even though the annex holds 10.
        StockService::recordMovement(
            productId: $this->product->id, movementType: 'out',
            quantity: 1, user: $this->manager,
        );
    }

    // ---------- approval workflow ----------

    private function makePo(float $unitPrice): PurchaseOrder
    {
        $supplier = Supplier::create(['name' => 'S']);
        $po = PurchaseOrder::create([
            'number' => DocumentService::nextNumber('PO', PurchaseOrder::class),
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'created_by' => $this->manager->id,
        ]);
        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id, 'product_id' => $this->product->id,
            'quantity' => 10, 'unit_price' => $unitPrice,
        ]);
        $po->load('lines')->recomputeTotal();

        return $po->refresh();
    }

    public function test_large_po_by_manager_needs_admin_approval(): void
    {
        $po = $this->makePo(200); // total 2000 >= threshold 1000
        DocumentService::confirmPurchase($po, $this->manager);
        $this->assertEquals(PurchaseOrder::STATUS_PENDING_APPROVAL, $po->status);

        DocumentService::approvePurchase($po, $this->admin);
        $this->assertEquals(PurchaseOrder::STATUS_CONFIRMED, $po->status);
        $this->assertEquals($this->admin->id, $po->approved_by);
    }

    public function test_small_po_confirms_directly(): void
    {
        $po = $this->makePo(1); // total 10 < threshold
        DocumentService::confirmPurchase($po, $this->manager);
        $this->assertEquals(PurchaseOrder::STATUS_CONFIRMED, $po->status);
    }

    public function test_large_po_by_admin_autoapproves(): void
    {
        $po = $this->makePo(200);
        DocumentService::confirmPurchase($po, $this->admin);
        $this->assertEquals(PurchaseOrder::STATUS_CONFIRMED, $po->status);
        $this->assertEquals($this->admin->id, $po->approved_by);
    }

    public function test_reject_returns_po_to_draft(): void
    {
        $po = $this->makePo(200);
        DocumentService::confirmPurchase($po, $this->manager);
        DocumentService::rejectPurchase($po);
        $this->assertEquals(PurchaseOrder::STATUS_DRAFT, $po->status);
    }

    public function test_pending_po_cannot_be_received(): void
    {
        $po = $this->makePo(200);
        DocumentService::confirmPurchase($po, $this->manager);
        $this->expectException(InvalidTransition::class);
        DocumentService::receivePurchase($po, $this->manager);
    }

    // ---------- CRM ----------

    public function test_lead_lifecycle_and_conversion(): void
    {
        $response = $this->actingAs($this->manager, 'api')->postJson('/api/v1/leads', [
            'name' => 'Sami Gharbi', 'company' => 'Gharbi & Co',
            'phone' => '+216 20 111 222', 'source' => 'referral',
        ]);
        $response->assertCreated();
        $leadId = $response->json('id');

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/leads/{$leadId}/activities", [
                'type' => 'call', 'summary' => 'Intro call, interested in bulk pricing',
            ])->assertCreated();

        $convert = $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/leads/{$leadId}/convert");
        $convert->assertCreated();

        $lead = Lead::find($leadId);
        $this->assertEquals('won', $lead->status);
        $this->assertNotNull($lead->customer_id);
        $this->assertEquals('Sami Gharbi (Gharbi & Co)', $lead->customer->name);

        // Second conversion is rejected.
        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/leads/{$leadId}/convert")->assertStatus(409);
    }

    public function test_employee_cannot_delete_lead(): void
    {
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $lead = Lead::create(['name' => 'L', 'created_by' => $employee->id]);

        $this->actingAs($employee, 'api')
            ->deleteJson("/api/v1/leads/{$lead->id}")->assertForbidden();
        $this->actingAs($this->manager, 'api')
            ->deleteJson("/api/v1/leads/{$lead->id}")->assertNoContent();
    }
}
