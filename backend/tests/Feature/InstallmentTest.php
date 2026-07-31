<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTransition;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\Payment;
use App\Models\PaymentInstrument;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\InstallmentService;
use App\Services\InstrumentService;
use App\Services\PaymentService;
use App\Services\StockService;
use App\Support\AccountMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Installment plans — "khlas bel taqsit". */
class InstallmentTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Customer $customer;
    private Sale $sale;

    protected function setUp(): void
    {
        parent::setUp();
        AccountMap::flush();

        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->customer = Customer::create(['name' => 'Ahmed Ben Ali']);

        $product = Product::create([
            'sku' => 'P-1', 'name' => 'P', 'sale_price' => 100, 'cost_price' => 60,
        ]);
        StockService::recordMovement(
            productId: $product->id, movementType: 'in', quantity: 100,
            user: $this->manager, reason: 'initial',
        );

        $this->sale = Sale::create([
            'number' => DocumentService::nextNumber('SO', Sale::class),
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'created_by' => $this->manager->id,
        ]);
        SaleLine::create([
            'sale_id' => $this->sale->id, 'product_id' => $product->id,
            'quantity' => 12, 'unit_price' => 100,
        ]);
        $this->sale->load('lines')->recomputeTotal();
        DocumentService::confirmSale($this->sale, $this->manager);
    }

    private function plan(float $total = 1200, int $count = 6, float $down = 0): InstallmentPlan
    {
        return InstallmentService::createPlan(
            referenceType: 'sale',
            referenceId: $this->sale->id,
            totalAmount: $total,
            count: $count,
            user: $this->manager,
            startDate: now()->toDateString(),
            downPayment: $down,
        );
    }

    // ---------- schedule generation ----------

    public function test_equal_monthly_schedule_sums_to_the_total(): void
    {
        $plan = $this->plan(1200, 6);

        $this->assertCount(6, $plan->installments);
        $this->assertEqualsWithDelta(
            1200,
            $plan->installments->sum('amount'),
            0.001
        );
        $this->assertEqualsWithDelta(200, (float) $plan->installments->first()->amount, 0.001);
        // Monthly steps from the start date.
        $this->assertSame(
            now()->addMonth()->toDateString(),
            $plan->installments->first()->due_date->toDateString()
        );
    }

    public function test_the_last_installment_absorbs_the_rounding(): void
    {
        // 1000 / 3 does not divide evenly at 3 decimals.
        $plan = $this->plan(1000, 3);

        $this->assertEqualsWithDelta(1000, $plan->installments->sum('amount'), 0.0001);
        $amounts = $plan->installments->pluck('amount')->map(fn ($a) => (float) $a);
        $this->assertNotEquals($amounts[0], $amounts[2]);
    }

    public function test_down_payment_becomes_the_first_installment_due_today(): void
    {
        $plan = $this->plan(1200, 6, down: 300);

        $this->assertCount(7, $plan->installments);
        $first = $plan->installments->first();
        $this->assertTrue($first->is_down_payment);
        $this->assertEqualsWithDelta(300, (float) $first->amount, 0.001);
        $this->assertSame(now()->toDateString(), $first->due_date->toDateString());
        // The rest finance 900 over 6.
        $this->assertEqualsWithDelta(150, (float) $plan->installments[1]->amount, 0.001);
        $this->assertEqualsWithDelta(1200, $plan->installments->sum('amount'), 0.001);
    }

    public function test_custom_schedule_is_used_verbatim(): void
    {
        $plan = InstallmentService::createPlan(
            referenceType: 'sale',
            referenceId: $this->sale->id,
            totalAmount: 900,
            count: 0,
            user: $this->manager,
            custom: [
                ['due_date' => '2026-09-01', 'amount' => 500],
                ['due_date' => '2026-12-01', 'amount' => 400],
            ],
        );

        $this->assertCount(2, $plan->installments);
        $this->assertSame('custom', $plan->frequency);
        $this->assertEqualsWithDelta(500, (float) $plan->installments->first()->amount, 0.001);
    }

    public function test_weekly_and_quarterly_frequencies_step_correctly(): void
    {
        $weekly = InstallmentService::createPlan(
            referenceType: 'sale', referenceId: $this->sale->id, totalAmount: 300,
            count: 3, user: $this->manager, frequency: 'weekly',
            startDate: now()->toDateString(),
        );
        $this->assertSame(
            now()->addWeek()->toDateString(),
            $weekly->installments->first()->due_date->toDateString()
        );
        InstallmentService::cancelPlan($weekly);

        $quarterly = InstallmentService::createPlan(
            referenceType: 'sale', referenceId: $this->sale->id, totalAmount: 300,
            count: 2, user: $this->manager, frequency: 'quarterly',
            startDate: now()->toDateString(),
        );
        $this->assertSame(
            now()->addMonths(3)->toDateString(),
            $quarterly->installments->first()->due_date->toDateString()
        );
    }

    public function test_a_document_cannot_have_two_active_plans(): void
    {
        $this->plan();

        $this->expectException(InvalidTransition::class);
        $this->plan();
    }

    public function test_down_payment_larger_than_the_total_is_rejected(): void
    {
        $this->expectException(InvalidTransition::class);
        $this->plan(1000, 3, down: 1500);
    }

    // ---------- payment ----------

    public function test_paying_an_installment_in_full_marks_it_paid(): void
    {
        $plan = $this->plan(1200, 6);
        $first = $plan->installments->first();

        PaymentService::settleInstallment(
            installment: $first->load('plan'),
            amount: 200,
            method: Payment::METHOD_CASH,
            user: $this->manager,
        );

        $first->refresh();
        $this->assertSame(Installment::STATUS_PAID, $first->status);
        $this->assertEqualsWithDelta(200, (float) $first->paid_amount, 0.001);
        $this->assertEqualsWithDelta(200, (float) $plan->refresh()->paid_amount, 0.001);
        $this->assertEqualsWithDelta(1000, $plan->remainingAmount(), 0.001);
    }

    public function test_partial_payment_leaves_the_installment_open(): void
    {
        $plan = $this->plan(1200, 6);
        $first = $plan->installments->first();

        PaymentService::settleInstallment(
            installment: $first->load('plan'), amount: 80,
            method: Payment::METHOD_CASH, user: $this->manager,
        );

        $first->refresh();
        $this->assertSame(Installment::STATUS_PARTIAL, $first->status);
        $this->assertEqualsWithDelta(120, $first->remainingAmount(), 0.001);
    }

    public function test_overpaying_an_installment_is_refused(): void
    {
        $plan = $this->plan(1200, 6);

        $this->expectException(InvalidTransition::class);
        PaymentService::settleInstallment(
            installment: $plan->installments->first()->load('plan'),
            amount: 500,
            method: Payment::METHOD_CASH,
            user: $this->manager,
        );
    }

    public function test_cash_payment_posts_to_cash_and_clears_the_receivable(): void
    {
        $plan = $this->plan(1200, 6);
        $payment = PaymentService::settleInstallment(
            installment: $plan->installments->first()->load('plan'),
            amount: 200, method: Payment::METHOD_CASH, user: $this->manager,
        );

        $this->assertNotNull($payment->journal_entry_id);
        $entry = $payment->journalEntry->load('lines.account');
        $this->assertEqualsWithDelta(
            $entry->lines->sum('debit'), $entry->lines->sum('credit'), 0.001
        );
        $codes = $entry->lines->pluck('account.code')->all();
        $this->assertContains(AccountMap::code('cash'), $codes);
        $this->assertContains(AccountMap::code('receivable'), $codes);
    }

    public function test_plan_completes_when_every_installment_is_paid(): void
    {
        $plan = $this->plan(300, 3);
        foreach ($plan->installments as $installment) {
            PaymentService::settleInstallment(
                installment: $installment->load('plan'),
                amount: (float) $installment->amount,
                method: Payment::METHOD_CASH,
                user: $this->manager,
            );
        }

        $this->assertSame(InstallmentPlan::STATUS_COMPLETED, $plan->refresh()->status);
    }

    // ---------- overdue ----------

    public function test_installments_past_the_grace_period_become_overdue(): void
    {
        CompanyProfile::current()->update(['late_payment_grace_days' => 5]);

        $plan = $this->plan(300, 3);
        $plan->installments->first()->update(['due_date' => now()->subDays(10)->toDateString()]);
        $plan->installments[1]->update(['due_date' => now()->subDays(2)->toDateString()]);

        InstallmentService::markOverdue();

        // 10 days late is past the 5-day grace; 2 days is not.
        $this->assertSame(
            Installment::STATUS_OVERDUE,
            $plan->installments()->where('sequence', 1)->first()->status
        );
        $this->assertSame(
            Installment::STATUS_PENDING,
            $plan->installments()->where('sequence', 2)->first()->status
        );
    }

    public function test_customer_credit_view_reports_exposure_and_arrears(): void
    {
        $plan = $this->plan(1200, 6);
        $plan->installments->first()->update(['due_date' => now()->subDays(30)->toDateString()]);
        InstallmentService::markOverdue();

        $credit = InstallmentService::customerCredit($this->customer->id);

        $this->assertSame(1, $credit['plan_count']);
        $this->assertEqualsWithDelta(1200, $credit['outstanding_amount'], 0.001);
        $this->assertEqualsWithDelta(200, $credit['overdue_amount'], 0.001);
        $this->assertTrue($credit['has_arrears']);
    }

    // ---------- instrument interaction ----------

    public function test_a_cheque_only_credits_its_installment_once_it_clears(): void
    {
        $plan = $this->plan(1200, 6);
        $first = $plan->installments->first();

        $cheque = InstrumentService::create([
            'kind' => PaymentInstrument::KIND_CHEQUE,
            'direction' => PaymentInstrument::DIRECTION_IN,
            'amount' => 200,
            'issue_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'reference_type' => 'installment',
            'reference_id' => $first->id,
        ], $this->manager);

        // Received, not cleared: the instalment is untouched.
        $this->assertEqualsWithDelta(0, (float) $first->refresh()->paid_amount, 0.001);

        $bankAccount = BankAccount::create([
            'bank_id' => Bank::first()->id, 'label' => 'Compte', 'currency' => 'TND',
        ]);
        InstrumentService::deposit($cheque, $this->manager, $bankAccount->id);
        InstrumentService::clear($cheque->refresh(), $this->manager);

        $this->assertSame(Installment::STATUS_PAID, $first->refresh()->status);
    }

    public function test_settling_by_cheque_does_not_credit_the_schedule_twice(): void
    {
        $plan = $this->plan(1200, 6);
        $first = $plan->installments->first();

        $bankAccount = BankAccount::create([
            'bank_id' => Bank::first()->id, 'label' => 'Compte', 'currency' => 'TND',
        ]);
        $cheque = InstrumentService::create([
            'kind' => PaymentInstrument::KIND_CHEQUE,
            'direction' => PaymentInstrument::DIRECTION_IN,
            'amount' => 200,
            'issue_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
        ], $this->manager);

        // Recording the promise must not move the schedule…
        PaymentService::settleInstallment(
            installment: $first->load('plan'),
            amount: 200,
            method: Payment::METHOD_CHEQUE,
            user: $this->manager,
            instrumentId: $cheque->id,
        );
        $this->assertEqualsWithDelta(0, (float) $first->refresh()->paid_amount, 0.001);

        // …and clearing it must credit exactly once, not twice.
        InstrumentService::deposit($cheque->refresh(), $this->manager, $bankAccount->id);
        InstrumentService::clear($cheque->refresh(), $this->manager);

        $first->refresh();
        $this->assertEqualsWithDelta(200, (float) $first->paid_amount, 0.001);
        $this->assertSame(Installment::STATUS_PAID, $first->status);
        $this->assertEqualsWithDelta(200, (float) $plan->refresh()->paid_amount, 0.001);
    }

    public function test_a_bounced_cheque_reopens_its_installment(): void
    {
        $plan = $this->plan(1200, 6);
        $first = $plan->installments->first();

        $cheque = InstrumentService::create([
            'kind' => PaymentInstrument::KIND_CHEQUE,
            'direction' => PaymentInstrument::DIRECTION_IN,
            'amount' => 200,
            'issue_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'reference_type' => 'installment',
            'reference_id' => $first->id,
        ], $this->manager);

        $bankAccount = BankAccount::create([
            'bank_id' => Bank::first()->id, 'label' => 'Compte', 'currency' => 'TND',
        ]);
        InstrumentService::deposit($cheque, $this->manager, $bankAccount->id);
        InstrumentService::clear($cheque->refresh(), $this->manager);
        $this->assertSame(Installment::STATUS_PAID, $first->refresh()->status);

        InstrumentService::bounce($cheque->refresh(), $this->manager, reason: 'Sans provision');

        $first->refresh();
        $this->assertSame(Installment::STATUS_PENDING, $first->status);
        $this->assertEqualsWithDelta(0, (float) $first->paid_amount, 0.001);
        $this->assertNull($first->paid_at);
    }

    // ---------- API & RBAC ----------

    public function test_api_rejects_a_custom_schedule_that_does_not_add_up(): void
    {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/installment-plans', [
                'reference_type' => 'sale',
                'reference_id' => $this->sale->id,
                'total_amount' => 1000,
                'installments' => [
                    ['due_date' => '2026-09-01', 'amount' => 400],
                    ['due_date' => '2026-10-01', 'amount' => 400],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_employee_can_read_plans_but_not_create_them(): void
    {
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->plan();

        $this->actingAs($employee, 'api')->getJson('/api/v1/installment-plans')->assertOk();
        $this->actingAs($employee, 'api')
            ->postJson('/api/v1/installment-plans', [
                'reference_type' => 'sale',
                'reference_id' => $this->sale->id,
                'total_amount' => 100,
                'installment_count' => 2,
            ])
            ->assertStatus(403);
    }

    public function test_cancelling_a_plan_cancels_its_unpaid_installments(): void
    {
        $plan = $this->plan(300, 3);
        PaymentService::settleInstallment(
            installment: $plan->installments->first()->load('plan'),
            amount: 100, method: Payment::METHOD_CASH, user: $this->manager,
        );

        InstallmentService::cancelPlan($plan->refresh(), 'Customer withdrew');

        $plan->refresh()->load('installments');
        $this->assertSame(InstallmentPlan::STATUS_CANCELLED, $plan->status);
        // The paid one keeps its history; the rest are cancelled.
        $this->assertSame(Installment::STATUS_PAID, $plan->installments[0]->status);
        $this->assertSame(Installment::STATUS_CANCELLED, $plan->installments[1]->status);
    }
}
