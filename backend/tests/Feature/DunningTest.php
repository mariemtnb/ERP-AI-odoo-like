<?php

namespace Tests\Feature;

use App\Mail\DunningMail;
use App\Models\Customer;
use App\Models\DunningLog;
use App\Models\FeatureFlag;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RecordMessage;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** AR dunning: escalating follow-ups on overdue invoices. */
class DunningTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureFlag::flush();
        Mail::fake();
        $this->manager = User::create(['id' => 1, 'email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
    }

    /** A confirmed, invoiced sale of $total, invoice dated 2026-01-01 (due 2026-01-31 at 30d terms). */
    private function overdueSale(float $total = 1000, ?string $email = 'buyer@example.com'): Sale
    {
        $customer = Customer::create(['name' => 'Ahmed', 'email' => $email ?? '']);
        $product = Product::create(['sku' => 'P1', 'name' => 'Chair', 'sale_price' => $total, 'cost_price' => 0, 'unit' => 'pcs']);
        $sale = Sale::create([
            'number' => 'SO-'.$customer->id, 'customer_id' => $customer->id, 'sale_date' => '2026-01-01',
            'status' => Sale::STATUS_CONFIRMED, 'created_by' => 1,
        ]);
        SaleLine::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => $total]);
        $sale->load('lines')->recomputeTotal();
        $invoice = DocumentService::generateInvoice($sale->refresh());
        $invoice->update(['issued_at' => '2026-01-01 09:00:00']);

        return $sale->refresh();
    }

    private function runDunning(string $asOf)
    {
        return $this->actingAs($this->manager, 'api')->postJson('/api/v1/dunning/run', ['as_of' => $asOf]);
    }

    public function test_a_first_run_sends_the_friendly_reminder_and_logs_it(): void
    {
        $sale = $this->overdueSale();

        $this->runDunning('2026-02-10') // 10 days overdue → level 1 (≥7)
            ->assertOk()->assertJsonPath('sent', 1)->assertJsonPath('emailed', 1);

        Mail::assertSent(DunningMail::class, fn ($m) => $m->hasTo('buyer@example.com') && $m->dunningLevel->level === 1);
        $log = DunningLog::where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame(1, $log->level);
        $this->assertTrue($log->emailed);
        $this->assertSame(1, RecordMessage::where('subject_type', 'sales')->where('subject_id', $sale->id)->count());
    }

    public function test_running_again_at_the_same_level_sends_nothing(): void
    {
        $this->overdueSale();
        $this->runDunning('2026-02-10')->assertJsonPath('sent', 1);

        $this->runDunning('2026-02-12')->assertOk()->assertJsonPath('sent', 0); // level 1 already sent, not yet at level 2
        Mail::assertSentCount(1);
    }

    public function test_it_escalates_to_the_next_level_as_time_passes(): void
    {
        $sale = $this->overdueSale();
        $this->runDunning('2026-02-10')->assertJsonPath('sent', 1);           // level 1 at 10 days
        $this->runDunning('2026-03-05')->assertJsonPath('sent', 1);           // level 2 at 33 days
        $this->runDunning('2026-04-05')->assertJsonPath('sent', 1);           // level 3 at 64 days

        $this->assertEqualsCanonicalizing([1, 2, 3], DunningLog::where('sale_id', $sale->id)->pluck('level')->all());
    }

    public function test_a_settled_invoice_is_never_dunned(): void
    {
        $sale = $this->overdueSale(1000);
        Payment::create([
            'number' => 'PAY-1', 'direction' => Payment::DIRECTION_IN, 'method' => Payment::METHOD_CASH,
            'amount' => 1000, 'payment_date' => '2026-02-01', 'reference_type' => 'sale',
            'reference_id' => $sale->id, 'created_by' => 1,
        ]);

        $this->actingAs($this->manager, 'api')->getJson('/api/v1/dunning/candidates?as_of=2026-02-10')
            ->assertOk()->assertJsonPath('count', 0);
        $this->runDunning('2026-02-10')->assertJsonPath('sent', 0);
    }

    public function test_a_customer_without_an_email_is_still_logged(): void
    {
        $sale = $this->overdueSale(email: null);

        $this->runDunning('2026-02-10')->assertOk()->assertJsonPath('sent', 1)->assertJsonPath('emailed', 0);
        Mail::assertNothingSent();
        $this->assertFalse(DunningLog::where('sale_id', $sale->id)->first()->emailed);
    }

    public function test_the_module_is_absent_when_the_flag_is_off(): void
    {
        FeatureFlag::updateOrCreate(['key' => 'dunning'], ['enabled' => false]);
        FeatureFlag::flush();
        $this->actingAs($this->manager, 'api')->getJson('/api/v1/dunning/candidates')->assertStatus(404);
    }

    public function test_an_ordinary_employee_cannot_run_dunning(): void
    {
        $employee = User::create(['id' => 2, 'email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')->postJson('/api/v1/dunning/run')->assertStatus(403);
    }
}
