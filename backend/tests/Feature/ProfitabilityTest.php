<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\ProfitabilityService;
use App\Services\StockService;
use App\Support\AccountMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The owner's profit view — computed from the ledger and confirmed sales. */
class ProfitabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        AccountMap::flush();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
    }

    /** Confirm one sale of $qty units of a product bought at $cost, sold at $price. */
    private function sell(string $sku, float $cost, float $price, float $qty): void
    {
        $product = Product::create(['sku' => $sku, 'name' => $sku, 'cost_price' => $cost, 'sale_price' => $price]);
        StockService::recordMovement(
            productId: $product->id, movementType: 'in', quantity: 100,
            user: $this->manager, reason: 'stock',
        );
        $customer = Customer::create(['name' => 'Client']);
        $sale = Sale::create([
            'number' => DocumentService::nextNumber('SO', Sale::class),
            'customer_id' => $customer->id, 'sale_date' => now()->toDateString(),
            'created_by' => $this->manager->id,
        ]);
        SaleLine::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => $qty, 'unit_price' => $price]);
        $sale->load('lines')->recomputeTotal();
        DocumentService::confirmSale($sale, $this->manager);
    }

    public function test_profit_summary_matches_the_sales(): void
    {
        // Sell 10 units, cost 6, price 10 → revenue 100, COGS 60, gross 40.
        $this->sell('A', 6, 10, 10);

        $summary = ProfitabilityService::summary();

        $this->assertEqualsWithDelta(100, $summary['revenue'], 0.001);
        $this->assertEqualsWithDelta(60, $summary['cost_of_goods_sold'], 0.001);
        $this->assertEqualsWithDelta(40, $summary['gross_profit'], 0.001);
        $this->assertEqualsWithDelta(40, $summary['gross_margin_pct'], 0.1);
    }

    public function test_best_products_are_ranked_by_margin(): void
    {
        // A: 10 units × (10−6) = 40 margin.
        $this->sell('A', 6, 10, 10);
        // B: 5 units × (20−5) = 75 margin — should rank first.
        $this->sell('B', 5, 20, 5);

        $best = ProfitabilityService::bestProducts();

        $this->assertSame('B', $best[0]['sku']);
        $this->assertEqualsWithDelta(75, $best[0]['margin'], 0.001);
        $this->assertSame('A', $best[1]['sku']);
        $this->assertEqualsWithDelta(40, $best[1]['margin'], 0.001);
    }

    public function test_owner_endpoint_requires_a_manager(): void
    {
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')->getJson('/api/v1/owner/profit')->assertStatus(403);
        $this->actingAs($this->manager, 'api')->getJson('/api/v1/owner/profit')->assertOk()
            ->assertJsonStructure(['summary' => ['revenue', 'net_profit'], 'best_products']);
    }
}
