<?php

namespace Tests\Feature;

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

/** Receiving a purchase order in instalments. */
class PartialReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        AccountMap::flush();
        $this->user = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        Warehouse::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main']);
        $this->product = Product::create(['sku' => 'P1', 'name' => 'Bolt', 'sale_price' => 5, 'cost_price' => 2, 'unit' => 'pcs']);
    }

    private function order(float $qty = 10, float $price = 2): PurchaseOrder
    {
        $s = Supplier::firstOrCreate(['name' => 'S']);
        $po = PurchaseOrder::create(['number' => 'PO-'.uniqid(), 'supplier_id' => $s->id,
            'status' => PurchaseOrder::STATUS_CONFIRMED, 'order_date' => '2026-01-01', 'created_by' => $this->user->id]);
        PurchaseOrderLine::create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id,
            'quantity' => $qty, 'unit_price' => $price, 'tax_rate' => 0]);
        $po->load('lines')->recomputeTotal();

        return $po->refresh();
    }

    private function inventoryBalance(): float
    {
        return (float) DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('a.code', '1200')->sum(DB::raw('l.debit - l.credit'));
    }

    public function test_receiving_part_leaves_the_order_partial(): void
    {
        $po = $this->order(10, 2);
        $lineId = $po->lines->first()->id;

        // Receive 4 of 10.
        $po = DocumentService::receivePurchase($po, $this->user, [$lineId => 4]);

        $this->assertSame(PurchaseOrder::STATUS_PARTIAL, $po->status);
        $this->assertEqualsWithDelta(4, (float) $po->lines->first()->received_qty, 0.001);
        $this->assertEqualsWithDelta(4, (float) $this->product->refresh()->quantity_in_stock, 0.001);
        // Only the received value posts: 4 × 2 = 8.
        $this->assertEqualsWithDelta(8, $this->inventoryBalance(), 0.001);
    }

    public function test_receiving_the_rest_completes_the_order(): void
    {
        $po = $this->order(10, 2);
        $lineId = $po->lines->first()->id;

        DocumentService::receivePurchase($po, $this->user, [$lineId => 4]);
        // No per-line map → receive everything still outstanding (6).
        $po = DocumentService::receivePurchase($po->refresh(), $this->user);

        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->status);
        $this->assertEqualsWithDelta(10, (float) $po->lines->first()->received_qty, 0.001);
        $this->assertEqualsWithDelta(20, $this->inventoryBalance(), 0.001); // 8 + 12
    }

    public function test_you_cannot_receive_more_than_was_ordered(): void
    {
        $po = $this->order(10, 2);
        $lineId = $po->lines->first()->id;

        // Ask for 99; only 10 are outstanding, so exactly 10 arrive.
        $po = DocumentService::receivePurchase($po, $this->user, [$lineId => 99]);

        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->status);
        $this->assertEqualsWithDelta(10, (float) $po->lines->first()->received_qty, 0.001);
    }

    public function test_a_fully_received_order_cannot_be_received_again(): void
    {
        $po = $this->order(5, 2);
        DocumentService::receivePurchase($po, $this->user);

        $this->expectException(\App\Exceptions\InvalidTransition::class);
        DocumentService::receivePurchase($po->refresh(), $this->user);
    }
}
