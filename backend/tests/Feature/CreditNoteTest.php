<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTransition;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CreditNoteService;
use App\Services\DocumentService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->product = Product::create(['sku' => 'P-1', 'name' => 'P', 'sale_price' => 10, 'cost_price' => 6]);
        $this->customer = Customer::create(['name' => 'C']);
        StockService::recordMovement(
            productId: $this->product->id, movementType: StockMovement::TYPE_IN,
            quantity: 100, user: $this->user, reason: 'seed',
        );
    }

    private function stock(): float
    {
        return (float) $this->product->refresh()->quantity_in_stock;
    }

    private function confirmedSale(string $qty = '10'): Sale
    {
        $sale = Sale::create([
            'number' => DocumentService::nextNumber('SO', Sale::class),
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);
        SaleLine::create(['sale_id' => $sale->id, 'product_id' => $this->product->id, 'quantity' => $qty, 'unit_price' => 10]);
        $sale->load('lines')->recomputeTotal();
        DocumentService::confirmSale($sale, $this->user); // stock 100 -> 90

        return $sale->refresh();
    }

    public function test_full_return_restocks_and_posts_reversing_entry(): void
    {
        $sale = $this->confirmedSale('10');
        $this->assertEquals(90.0, $this->stock());

        $note = CreditNoteService::createFromSale(
            $sale,
            [['product' => $this->product->id, 'quantity' => 10, 'unit_price' => 10]],
            true,
            'Damaged',
            $this->user,
        );

        $this->assertEquals('100.00', $note->total_amount);
        $this->assertEquals(100.0, $this->stock()); // restocked
        // a credit_note journal entry exists and balances
        $entry = JournalEntry::where('reference_type', 'credit_note')->where('reference_id', $note->id)->with('lines')->firstOrFail();
        $debit = $entry->lines->sum('debit');
        $credit = $entry->lines->sum('credit');
        $this->assertEqualsWithDelta($debit, $credit, 0.001);
    }

    public function test_partial_return(): void
    {
        $sale = $this->confirmedSale('10');
        $note = CreditNoteService::createFromSale(
            $sale, [['product' => $this->product->id, 'quantity' => 3, 'unit_price' => 10]], true, '', $this->user,
        );
        $this->assertEquals('30.00', $note->total_amount);
        $this->assertEquals(93.0, $this->stock()); // 90 + 3
    }

    public function test_cannot_return_more_than_sold_across_notes(): void
    {
        $sale = $this->confirmedSale('10');
        CreditNoteService::createFromSale($sale, [['product' => $this->product->id, 'quantity' => 7, 'unit_price' => 10]], true, '', $this->user);

        $this->expectException(InvalidTransition::class);
        // only 3 left; asking for 4 must fail
        CreditNoteService::createFromSale($sale, [['product' => $this->product->id, 'quantity' => 4, 'unit_price' => 10]], true, '', $this->user);
    }

    public function test_cannot_credit_an_unconfirmed_sale(): void
    {
        $sale = Sale::create([
            'number' => DocumentService::nextNumber('SO', Sale::class),
            'customer_id' => $this->customer->id, 'sale_date' => now()->toDateString(), 'created_by' => $this->user->id,
        ]);
        SaleLine::create(['sale_id' => $sale->id, 'product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 10]);

        $this->expectException(InvalidTransition::class);
        CreditNoteService::createFromSale($sale->load('lines'), [['product' => $this->product->id, 'quantity' => 1, 'unit_price' => 10]], true, '', $this->user);
    }

    public function test_no_restock_leaves_stock_untouched(): void
    {
        $sale = $this->confirmedSale('10');
        CreditNoteService::createFromSale($sale, [['product' => $this->product->id, 'quantity' => 5, 'unit_price' => 10]], false, 'kept', $this->user);
        $this->assertEquals(90.0, $this->stock()); // unchanged
    }

    public function test_store_endpoint_creates_credit_note(): void
    {
        $sale = $this->confirmedSale('10');

        $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/sales/{$sale->id}/credit-notes", [
                'reason' => 'wrong item',
                'restock' => true,
                'lines' => [['product' => $this->product->id, 'quantity' => 2, 'unit_price' => 10]],
            ])
            ->assertCreated()
            ->assertJsonPath('total_amount', '20.00')
            ->assertJsonPath('restocked', true);

        $this->assertEquals(92.0, $this->stock());
        $this->assertDatabaseCount('credit_notes', 1);
    }

    public function test_employees_cannot_issue_credit_notes(): void
    {
        $sale = $this->confirmedSale('10');
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);

        $this->actingAs($employee, 'api')
            ->postJson("/api/v1/sales/{$sale->id}/credit-notes", [
                'lines' => [['product' => $this->product->id, 'quantity' => 1, 'unit_price' => 10]],
            ])
            ->assertForbidden();
    }
}
