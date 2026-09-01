<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Vendor bills and their 3-way match against the PO and the receipt. */
class VendorBillTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Supplier $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\FeatureFlag::flush();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->supplier = Supplier::create(['name' => 'Acme']);
        $this->product = Product::create(['sku' => 'P1', 'name' => 'Bolt', 'sale_price' => 5, 'cost_price' => 2, 'unit' => 'pcs']);
    }

    private function receivedPo(float $qty = 10, float $price = 2): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'number' => 'PO-1', 'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrder::STATUS_RECEIVED, 'order_date' => '2026-01-01', 'created_by' => $this->manager->id,
        ]);
        PurchaseOrderLine::create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id, 'quantity' => $qty, 'unit_price' => $price]);

        return $po;
    }

    private function postBill(array $body)
    {
        return $this->actingAs($this->manager, 'api')->postJson('/api/v1/vendor-bills', $body);
    }

    public function test_a_bill_matching_the_po_and_receipt_is_matched(): void
    {
        $po = $this->receivedPo(10, 2);

        $this->postBill([
            'supplier' => $this->supplier->id, 'purchase_order' => $po->id, 'bill_date' => '2026-01-05',
            'lines' => [['product' => $this->product->id, 'quantity' => 10, 'unit_price' => 2]],
        ])->assertCreated()->assertJsonPath('status', 'matched')->assertJsonPath('total_amount', '20.00');
    }

    public function test_over_billing_more_than_received_is_an_exception(): void
    {
        $po = $this->receivedPo(10, 2);

        $this->postBill([
            'supplier' => $this->supplier->id, 'purchase_order' => $po->id, 'bill_date' => '2026-01-05',
            'lines' => [['product' => $this->product->id, 'quantity' => 12, 'unit_price' => 2]], // 12 > 10 received
        ])->assertCreated()
            ->assertJsonPath('status', 'exception')
            ->assertJsonPath('match.0.flags.0', 'over_billed');
    }

    public function test_a_price_that_drifted_from_the_po_is_an_exception(): void
    {
        $po = $this->receivedPo(10, 2);

        $this->postBill([
            'supplier' => $this->supplier->id, 'purchase_order' => $po->id, 'bill_date' => '2026-01-05',
            'lines' => [['product' => $this->product->id, 'quantity' => 10, 'unit_price' => 2.5]], // priced 2.5 vs 2
        ])->assertCreated()
            ->assertJsonPath('status', 'exception')
            ->assertJsonPath('match.0.flags.0', 'price_mismatch');
    }

    public function test_billing_before_receipt_is_an_exception(): void
    {
        $po = PurchaseOrder::create([
            'number' => 'PO-2', 'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrder::STATUS_CONFIRMED, 'order_date' => '2026-01-01', 'created_by' => $this->manager->id,
        ]);
        PurchaseOrderLine::create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 2]);

        $this->postBill([
            'supplier' => $this->supplier->id, 'purchase_order' => $po->id, 'bill_date' => '2026-01-05',
            'lines' => [['product' => $this->product->id, 'quantity' => 10, 'unit_price' => 2]],
        ])->assertCreated()->assertJsonPath('status', 'exception'); // received_qty is 0
    }

    public function test_a_bill_with_no_po_is_an_exception(): void
    {
        $this->postBill([
            'supplier' => $this->supplier->id, 'bill_date' => '2026-01-05',
            'lines' => [['product' => $this->product->id, 'quantity' => 3, 'unit_price' => 2]],
        ])->assertCreated()->assertJsonPath('status', 'exception');
    }

    public function test_a_manager_can_approve_an_exception(): void
    {
        $res = $this->postBill([
            'supplier' => $this->supplier->id, 'bill_date' => '2026-01-05',
            'lines' => [['product' => $this->product->id, 'quantity' => 3, 'unit_price' => 2]],
        ])->assertCreated();
        $id = $res->json('id');

        $this->actingAs($this->manager, 'api')->postJson("/api/v1/vendor-bills/{$id}/approve")
            ->assertOk()->assertJsonPath('status', 'approved');
    }

    public function test_a_po_from_another_supplier_is_rejected(): void
    {
        $other = Supplier::create(['name' => 'Other']);
        $po = $this->receivedPo();

        $this->postBill([
            'supplier' => $other->id, 'purchase_order' => $po->id, 'bill_date' => '2026-01-05',
            'lines' => [['product' => $this->product->id, 'quantity' => 1, 'unit_price' => 2]],
        ])->assertStatus(422);
    }
}
