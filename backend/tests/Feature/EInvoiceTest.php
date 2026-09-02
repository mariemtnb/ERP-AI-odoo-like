<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\EInvoice;
use App\Models\FeatureFlag;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tunisian e-invoicing: generating a TEIF document and submitting it to TTN. */
class EInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureFlag::flush();   // the flag map is a static memo; don't inherit another test's
    }

    private function manager(): User
    {
        return User::firstOrCreate(['id' => 1], ['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
    }

    /** A confirmed, invoiced sale with two VAT rates and both parties identified. */
    private function invoicedSale(string $sellerTaxId = '1234567A', ?string $buyerTaxId = '7654321B'): Sale
    {
        CompanyProfile::current()->update(['legal_name' => 'Vendor SARL', 'tax_id' => $sellerTaxId]);

        $customer = Customer::create(['name' => 'Ahmed', 'email' => 'a@b.c', 'tax_id' => $buyerTaxId]);
        $p1 = Product::create(['sku' => 'A', 'name' => 'Chair', 'sale_price' => 119, 'cost_price' => 0, 'unit' => 'pcs']);
        $p2 = Product::create(['sku' => 'B', 'name' => 'Book', 'sale_price' => 107, 'cost_price' => 0, 'unit' => 'pcs']);
        $sale = Sale::create([
            'number' => 'SO-9', 'customer_id' => $customer->id, 'sale_date' => '2026-03-15',
            'status' => Sale::STATUS_CONFIRMED, 'created_by' => 1,
        ]);
        SaleLine::create(['sale_id' => $sale->id, 'product_id' => $p1->id, 'quantity' => 1, 'unit_price' => 119, 'tax_rate' => 19]);
        SaleLine::create(['sale_id' => $sale->id, 'product_id' => $p2->id, 'quantity' => 1, 'unit_price' => 107, 'tax_rate' => 7]);
        $sale->load('lines')->recomputeTotal();
        DocumentService::generateInvoice($sale);

        return $sale->refresh();
    }

    public function test_generating_builds_a_teif_document_with_both_matricules_and_totals(): void
    {
        $sale = $this->invoicedSale();

        $res = $this->actingAs($this->manager(), 'api')
            ->postJson("/api/v1/sales/{$sale->id}/e-invoice")
            ->assertStatus(201)
            ->assertJsonPath('status', EInvoice::STATUS_GENERATED);

        $xml = EInvoice::find($res->json('id'))->xml;

        // Well-formed and carries the fiscal identity of both parties.
        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc);
        $this->assertStringContainsString('1234567A', $xml);
        $this->assertStringContainsString('7654321B', $xml);
        $this->assertStringContainsString($sale->invoice->number, $xml);

        // Totals: net 200, VAT 26 (19 + 7), TTC 226.
        $this->assertStringContainsString('>200.00<', $xml);
        $this->assertStringContainsString('>26.00<', $xml);
        $this->assertStringContainsString('>226.00<', $xml);
    }

    public function test_submitting_through_the_sandbox_is_accepted_with_a_reference(): void
    {
        $sale = $this->invoicedSale();
        $gen = $this->actingAs($this->manager(), 'api')->postJson("/api/v1/sales/{$sale->id}/e-invoice");

        $this->actingAs($this->manager(), 'api')
            ->postJson("/api/v1/e-invoices/{$gen->json('id')}/submit")
            ->assertOk()
            ->assertJsonPath('status', EInvoice::STATUS_ACCEPTED);

        $e = EInvoice::first();
        $this->assertStringStartsWith('SANDBOX-', $e->ttn_ref);
        $this->assertNotNull($e->accepted_at);
    }

    public function test_an_accepted_e_invoice_cannot_be_regenerated(): void
    {
        $sale = $this->invoicedSale();
        $gen = $this->actingAs($this->manager(), 'api')->postJson("/api/v1/sales/{$sale->id}/e-invoice");
        $this->actingAs($this->manager(), 'api')->postJson("/api/v1/e-invoices/{$gen->json('id')}/submit")->assertOk();

        $this->actingAs($this->manager(), 'api')
            ->postJson("/api/v1/sales/{$sale->id}/e-invoice")
            ->assertStatus(409);
    }

    public function test_submission_is_rejected_when_the_seller_matricule_is_missing(): void
    {
        $sale = $this->invoicedSale(sellerTaxId: '');
        $gen = $this->actingAs($this->manager(), 'api')->postJson("/api/v1/sales/{$sale->id}/e-invoice");

        $this->actingAs($this->manager(), 'api')
            ->postJson("/api/v1/e-invoices/{$gen->json('id')}/submit")
            ->assertStatus(422)
            ->assertJsonPath('status', EInvoice::STATUS_REJECTED);

        // A rejected document can be regenerated and resubmitted after a fix.
        CompanyProfile::current()->update(['tax_id' => '9999999Z']);
        $this->actingAs($this->manager(), 'api')->postJson("/api/v1/sales/{$sale->id}/e-invoice")->assertStatus(201);
        $this->actingAs($this->manager(), 'api')
            ->postJson("/api/v1/e-invoices/".EInvoice::first()->id."/submit")
            ->assertOk()->assertJsonPath('status', EInvoice::STATUS_ACCEPTED);
    }

    public function test_generating_before_the_invoice_exists_is_rejected(): void
    {
        $customer = Customer::create(['name' => 'Ahmed', 'email' => 'a@b.c']);
        $sale = Sale::create(['number' => 'SO-1', 'customer_id' => $customer->id, 'sale_date' => '2026-03-15', 'created_by' => 1]);

        $this->actingAs($this->manager(), 'api')
            ->postJson("/api/v1/sales/{$sale->id}/e-invoice")
            ->assertStatus(409);
    }

    public function test_the_module_is_absent_when_the_flag_is_off(): void
    {
        $sale = $this->invoicedSale();
        FeatureFlag::updateOrCreate(['key' => 'einvoicing'], ['enabled' => false]);
        FeatureFlag::flush();

        $this->actingAs($this->manager(), 'api')
            ->postJson("/api/v1/sales/{$sale->id}/e-invoice")
            ->assertStatus(404);
    }

    public function test_an_ordinary_employee_cannot_generate(): void
    {
        $sale = $this->invoicedSale();
        $employee = User::create(['id' => 2, 'email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);

        $this->actingAs($employee, 'api')
            ->postJson("/api/v1/sales/{$sale->id}/e-invoice")
            ->assertStatus(403);
    }
}
