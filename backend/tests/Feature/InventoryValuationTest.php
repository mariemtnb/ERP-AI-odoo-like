<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DocumentService;
use App\Services\InventoryValuationService;
use App\Support\AccountMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Moving-average inventory cost and its posting to the ledger. */
class InventoryValuationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        AccountMap::flush();
        $this->user = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        Warehouse::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main']);
    }

    private function product(float $cost = 2): Product
    {
        return Product::create(['sku' => 'P'.uniqid(), 'name' => 'Widget', 'sale_price' => 10, 'cost_price' => $cost, 'unit' => 'pcs']);
    }

    /** Net movement on an account (by code), debit positive. */
    private function bal(string $code): float
    {
        return (float) DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('a.code', $code)
            ->sum(DB::raw('l.debit - l.credit'));
    }

    public function test_receipts_blend_into_a_weighted_average(): void
    {
        $p = $this->product(2);
        // 10 @ 2 → avg 2
        InventoryValuationService::registerReceipt($p, 10, 2);
        $p->quantity_in_stock = 10; $p->save();
        $this->assertEqualsWithDelta(2.0, InventoryValuationService::unitCost($p->refresh()), 0.0001);

        // + 10 @ 4 → (10×2 + 10×4)/20 = 3
        InventoryValuationService::registerReceipt($p, 10, 4);
        $this->assertEqualsWithDelta(3.0, InventoryValuationService::unitCost($p->refresh()), 0.0001);
    }

    public function test_receiving_a_po_updates_the_average(): void
    {
        $p = $this->product(2);
        $supplier = Supplier::create(['name' => 'S']);
        $po = PurchaseOrder::create(['number' => 'PO-9', 'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_CONFIRMED, 'order_date' => '2026-01-01', 'created_by' => $this->user->id]);
        PurchaseOrderLine::create(['purchase_order_id' => $po->id, 'product_id' => $p->id, 'quantity' => 5, 'unit_price' => 6]);
        $po->load('lines')->recomputeTotal();

        DocumentService::receivePurchase($po, $this->user);

        // First receipt at 6 → average becomes 6.
        $this->assertEqualsWithDelta(6.0, (float) $p->refresh()->avg_cost, 0.0001);
    }

    public function test_cogs_uses_the_average_cost(): void
    {
        $p = $this->product(2);
        $p->avg_cost = 3; $p->quantity_in_stock = 100; $p->save();
        $customer = Customer::create(['name' => 'C']);

        $sale = Sale::create(['number' => 'SO-1', 'customer_id' => $customer->id,
            'status' => 'confirmed', 'sale_date' => '2026-01-10', 'created_by' => $this->user->id]);
        SaleLine::create(['sale_id' => $sale->id, 'product_id' => $p->id, 'quantity' => 4, 'unit_price' => 10]);
        $sale->load('lines')->recomputeTotal();

        \App\Services\AccountingService::postSaleConfirmed($sale->load('lines.product'), $this->user);

        // COGS = 4 × avg 3 = 12; inventory relieved by the same.
        $this->assertEqualsWithDelta(12.0, $this->bal('5000'), 0.001);
        $this->assertEqualsWithDelta(-12.0, $this->bal('1200'), 0.001);
    }

    public function test_a_pos_sale_posts_revenue_and_cogs(): void
    {
        $p = $this->product(2);
        // Put real stock in the warehouse, then fix the average at 3.
        \App\Services\StockService::recordMovement(
            productId: $p->id, movementType: \App\Models\StockMovement::TYPE_IN,
            quantity: 100, user: $this->user, reason: 'seed',
        );
        $p->avg_cost = 3; $p->save();

        // Ring up 2 units at 10 through the till.
        $session = \App\Services\PosService::openSession($this->user, 0);
        \App\Services\PosService::checkout(
            $session,
            [['product' => $p->id, 'quantity' => 2, 'unit_price' => 10]],
            [['method' => 'cash', 'amount' => 20]],
            null,
            $this->user,
        );

        // Revenue 20 (Cr), cash 20 (Dr), COGS 6 (Dr), inventory −6.
        $this->assertEqualsWithDelta(-20.0, $this->bal('4000'), 0.001);
        $this->assertEqualsWithDelta(6.0, $this->bal('5000'), 0.001);
        $this->assertEqualsWithDelta(-6.0, $this->bal('1200'), 0.001);

        foreach (JournalEntry::with('lines')->get() as $e) {
            $this->assertEqualsWithDelta($e->lines->sum('debit'), $e->lines->sum('credit'), 0.001);
        }
    }
}
