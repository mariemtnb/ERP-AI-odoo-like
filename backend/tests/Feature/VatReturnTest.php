<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The periodic VAT declaration: output VAT − input VAT over a period. */
class VatReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        // Company default VAT rate is 19% (seeded); assert it stays so here.
        \App\Models\CompanyProfile::current()->update(['default_vat_rate' => 19]);
    }

    private function sale(float $total, string $date): void
    {
        $c = Customer::firstOrCreate(['name' => 'C']);
        Sale::create([
            'number' => 'SO-'.uniqid(), 'customer_id' => $c->id, 'status' => Sale::STATUS_CONFIRMED,
            'sale_date' => $date, 'total_amount' => $total, 'created_by' => $this->manager->id,
        ]);
    }

    private function purchase(float $total, string $date): void
    {
        $s = Supplier::firstOrCreate(['name' => 'S']);
        PurchaseOrder::create([
            'number' => 'PO-'.uniqid(), 'supplier_id' => $s->id, 'status' => PurchaseOrder::STATUS_RECEIVED,
            'order_date' => $date, 'received_date' => $date, 'total_amount' => $total, 'created_by' => $this->manager->id,
        ]);
    }

    public function test_output_minus_input_vat_over_the_period(): void
    {
        // In-period, VAT-inclusive: sales 1190 → net 1000, output VAT 190.
        $this->sale(1190, '2026-03-10');
        // purchases 595 → net 500, input VAT 95.
        $this->purchase(595, '2026-03-12');
        // Out of period — must be excluded.
        $this->sale(1190, '2026-04-01');

        $this->actingAs($this->manager, 'api')
            ->getJson('/api/v1/reports/vat?from=2026-03-01&to=2026-03-31')
            ->assertOk()
            ->assertJsonPath('rate', 19)
            ->assertJsonPath('output_vat', 190)
            ->assertJsonPath('input_vat', 95)
            ->assertJsonPath('net_vat_due', 95)
            ->assertJsonPath('vat_credit', 0);
    }

    public function test_more_input_than_output_yields_a_credit(): void
    {
        $this->sale(119, '2026-05-05');    // output VAT 19
        $this->purchase(1190, '2026-05-06'); // input VAT 190

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
