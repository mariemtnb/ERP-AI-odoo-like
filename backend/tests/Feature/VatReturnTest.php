<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The VAT declaration, summing line-level VAT across mixed rates. */
class VatReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->product = Product::create(['sku' => 'P1', 'name' => 'Item', 'sale_price' => 10, 'cost_price' => 0, 'unit' => 'pcs']);
    }

    /** A confirmed sale of one line at `$gross` (VAT-inclusive) at `$rate` %. */
    private function sale(float $gross, float $rate, string $date): void
    {
        $c = Customer::firstOrCreate(['name' => 'C']);
        $s = Sale::create(['number' => 'SO-'.uniqid(), 'customer_id' => $c->id,
            'status' => Sale::STATUS_CONFIRMED, 'sale_date' => $date, 'created_by' => $this->manager->id]);
        SaleLine::create(['sale_id' => $s->id, 'product_id' => $this->product->id,
            'quantity' => 1, 'unit_price' => $gross, 'tax_rate' => $rate]);
        $s->load('lines')->recomputeTotal();
    }

    private function purchase(float $gross, float $rate, string $date): void
    {
        $sup = Supplier::firstOrCreate(['name' => 'S']);
        $p = PurchaseOrder::create(['number' => 'PO-'.uniqid(), 'supplier_id' => $sup->id,
            'status' => PurchaseOrder::STATUS_RECEIVED, 'order_date' => $date, 'received_date' => $date,
            'created_by' => $this->manager->id]);
        PurchaseOrderLine::create(['purchase_order_id' => $p->id, 'product_id' => $this->product->id,
            'quantity' => 1, 'unit_price' => $gross, 'tax_rate' => $rate]);
        $p->load('lines')->recomputeTotal();
    }

    public function test_output_minus_input_across_mixed_rates(): void
    {
        // 1190 incl. 19% → VAT 190; 107 incl. 7% → VAT 7. Output = 197.
        $this->sale(1190, 19, '2026-03-10');
        $this->sale(107, 7, '2026-03-11');
        // 595 incl. 19% → input VAT 95.
        $this->purchase(595, 19, '2026-03-12');
        // Out of period, excluded.
        $this->sale(1190, 19, '2026-04-01');

        $res = $this->actingAs($this->manager, 'api')
            ->getJson('/api/v1/reports/vat?from=2026-03-01&to=2026-03-31')
            ->assertOk()
            ->assertJsonPath('output_vat', 197)
            ->assertJsonPath('input_vat', 95)
            ->assertJsonPath('net_vat_due', 102)
            ->assertJsonPath('vat_credit', 0);

        // The breakdown carries both rates on the output side.
        $rates = collect($res->json('output_by_rate'))->pluck('vat', 'rate');
        $this->assertEqualsWithDelta(190, $rates[19] ?? $rates['19'], 0.01);
        $this->assertEqualsWithDelta(7, $rates[7] ?? $rates['7'], 0.01);
    }

    public function test_more_input_than_output_yields_a_credit(): void
    {
        $this->sale(119, 19, '2026-05-05');     // output 19
        $this->purchase(1190, 19, '2026-05-06'); // input 190

        $this->actingAs($this->manager, 'api')
            ->getJson('/api/v1/reports/vat?from=2026-05-01&to=2026-05-31')
            ->assertOk()
            ->assertJsonPath('net_vat_due', 0)
            ->assertJsonPath('vat_credit', 171);
    }

    public function test_it_needs_a_manager(): void
    {
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')->getJson('/api/v1/reports/vat')->assertStatus(403);
    }
}
