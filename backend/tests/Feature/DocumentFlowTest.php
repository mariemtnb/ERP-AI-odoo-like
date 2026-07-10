<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStock;
use App\Exceptions\InvalidTransition;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    private Customer $customer;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['email' => 't@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->product = Product::create([
            'sku' => 'P-1', 'name' => 'P', 'sale_price' => 10, 'cost_price' => 6,
        ]);
        $this->customer = Customer::create(['name' => 'C']);
        $this->supplier = Supplier::create(['name' => 'S']);
    }

    private function stock(): float
    {
        return (float) $this->product->refresh()->quantity_in_stock;
    }

    private function makePo(string $qty = '10'): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'number' => DocumentService::nextNumber('PO', PurchaseOrder::class),
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);
        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id, 'product_id' => $this->product->id,
            'quantity' => $qty, 'unit_price' => 6,
        ]);
        $po->load('lines')->recomputeTotal();

        return $po->refresh();
    }

    private function makeSale(string $qty = '4'): Sale
    {
        $sale = Sale::create([
            'number' => DocumentService::nextNumber('SO', Sale::class),
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);
        SaleLine::create([
            'sale_id' => $sale->id, 'product_id' => $this->product->id,
            'quantity' => $qty, 'unit_price' => 10,
        ]);
        $sale->load('lines')->recomputeTotal();

        return $sale->refresh();
    }

    public function test_receive_purchase_creates_stock_in(): void
    {
        $po = $this->makePo('10');
        DocumentService::confirmPurchase($po, $this->user);
        DocumentService::receivePurchase($po, $this->user);

        $this->assertSame(10.0, $this->stock());
        $movement = StockMovement::where('reference_type', 'purchase')
            ->where('reference_id', $po->id)->firstOrFail();
        $this->assertSame('in', $movement->movement_type);
        $this->assertSame(60.0, (float) $po->total_amount);
    }

    public function test_receive_requires_confirmed(): void
    {
        $this->expectException(InvalidTransition::class);
        DocumentService::receivePurchase($this->makePo(), $this->user);
    }

    public function test_confirm_sale_decrements_stock(): void
    {
        StockService::recordMovement(
            productId: $this->product->id, movementType: 'in',
            quantity: '10', user: $this->user,
        );
        $sale = $this->makeSale('4');
        DocumentService::confirmSale($sale, $this->user);

        $this->assertSame(6.0, $this->stock());
        $this->assertSame(40.0, (float) $sale->total_amount);
    }

    public function test_confirm_sale_insufficient_stock_rolls_back(): void
    {
        StockService::recordMovement(
            productId: $this->product->id, movementType: 'in',
            quantity: '2', user: $this->user,
        );
        $sale = $this->makeSale('4');
        try {
            DocumentService::confirmSale($sale, $this->user);
            $this->fail('Expected InsufficientStock');
        } catch (InsufficientStock) {
        }
        $this->assertSame('draft', $sale->refresh()->status);
        $this->assertSame(2.0, $this->stock());
        $this->assertFalse(
            StockMovement::where('reference_type', 'sale')->where('reference_id', $sale->id)->exists()
        );
    }

    public function test_cancel_confirmed_sale_restores_stock(): void
    {
        StockService::recordMovement(
            productId: $this->product->id, movementType: 'in',
            quantity: '10', user: $this->user,
        );
        $sale = $this->makeSale('4');
        DocumentService::confirmSale($sale, $this->user);
        DocumentService::cancelSale($sale, $this->user);

        $this->assertSame(10.0, $this->stock());
        $this->assertSame('cancelled', $sale->refresh()->status);
    }

    public function test_document_numbers_sequence(): void
    {
        $po1 = $this->makePo();
        $po2 = $this->makePo();
        $this->assertNotSame($po1->number, $po2->number);
        $this->assertStringStartsWith('PO-', $po1->number);
        $this->assertStringStartsWith('SO-', $this->makeSale()->number);
    }
}
