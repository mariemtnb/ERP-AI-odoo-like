<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\User;
use App\Services\BiService;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Customer $c1;

    private Customer $c2;

    private Product $water;

    private Product $juice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->c1 = Customer::create(['name' => 'Alpha']);
        $this->c2 = Customer::create(['name' => 'Beta']);
        $this->water = Product::create(['sku' => 'W', 'name' => 'Water', 'sale_price' => 1, 'cost_price' => 0.5]);
        $this->juice = Product::create(['sku' => 'J', 'name' => 'Juice', 'sale_price' => 2, 'cost_price' => 1]);

        // Jan: Alpha buys 10 water @1 + 5 juice @2 = 20
        $this->sale($this->c1, '2026-01-10', [[$this->water, 10, 1], [$this->juice, 5, 2]]);
        // Feb: Beta buys 3 juice @2 = 6
        $this->sale($this->c2, '2026-02-05', [[$this->juice, 3, 2]]);
        // Feb: Alpha buys 4 water @1 = 4
        $this->sale($this->c1, '2026-02-20', [[$this->water, 4, 1]]);
    }

    private function sale(Customer $c, string $date, array $lines): void
    {
        $sale = Sale::create([
            'number' => DocumentService::nextNumber('SO', Sale::class),
            'customer_id' => $c->id, 'sale_date' => $date, 'created_by' => $this->manager->id,
        ]);
        foreach ($lines as [$p, $q, $price]) {
            SaleLine::create(['sale_id' => $sale->id, 'product_id' => $p->id, 'quantity' => $q, 'unit_price' => $price]);
        }
        $sale->load('lines')->recomputeTotal();
    }

    public function test_sales_total_by_month(): void
    {
        $rows = collect(BiService::run('sales', 'month', 'total'))->keyBy('label');
        $this->assertEquals(20.0, $rows['2026-01']['value']);
        $this->assertEquals(10.0, $rows['2026-02']['value']); // 6 + 4
    }

    public function test_sales_count_by_month(): void
    {
        $rows = collect(BiService::run('sales', 'month', 'count'))->keyBy('label');
        $this->assertEquals(1.0, $rows['2026-01']['value']);
        $this->assertEquals(2.0, $rows['2026-02']['value']);
    }

    public function test_total_by_customer(): void
    {
        $rows = collect(BiService::run('sales', 'customer', 'total'))->keyBy('label');
        $this->assertEquals(24.0, $rows['Alpha']['value']); // 20 + 4
        $this->assertEquals(6.0, $rows['Beta']['value']);
    }

    public function test_revenue_and_units_by_product(): void
    {
        $total = collect(BiService::run('sales', 'product', 'total'))->keyBy('label');
        // Water revenue: 10*1 + 4*1 = 14 ; Juice: 5*2 + 3*2 = 16
        $this->assertEquals(14.0, $total['Water']['value']);
        $this->assertEquals(16.0, $total['Juice']['value']);

        $units = collect(BiService::run('sales', 'product', 'count'))->keyBy('label');
        $this->assertEquals(14.0, $units['Water']['value']); // 10 + 4 units
        $this->assertEquals(8.0, $units['Juice']['value']);  // 5 + 3 units
    }

    public function test_run_endpoint_save_and_rbac(): void
    {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/bi/run', ['group_by' => 'month', 'measure' => 'total'])
            ->assertOk()->assertJsonPath('total', 30);

        $saved = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/bi/reports', ['name' => 'Monthly revenue', 'group_by' => 'month', 'measure' => 'total'])
            ->assertCreated()->json();

        $this->actingAs($this->manager, 'api')->getJson("/api/v1/bi/reports/{$saved['id']}/run")
            ->assertOk()->assertJsonPath('total', 30);

        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')->postJson('/api/v1/bi/run', ['group_by' => 'month', 'measure' => 'total'])->assertForbidden();
    }
}
