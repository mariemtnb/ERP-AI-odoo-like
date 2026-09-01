<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTransition;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\PayslipLine;
use App\Models\User;
use App\Services\PayrollService;
use App\Support\AccountMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Payroll, advances and bonuses. The point of these tests is the accounting:
 * every run must post a balanced entry, and an advance must move money now and
 * be taken back later without double-counting.
 */
class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Employee $emp;

    protected function setUp(): void
    {
        parent::setUp();
        AccountMap::flush();
        // The flag cache is a per-process static; a fresh DB per test isn't
        // enough on its own, so clear it or an earlier test that toggles the
        // payroll flag leaks its value into this one.
        \App\Models\FeatureFlag::flush();
        // These tests exercise the payroll *accounting* (gross → net, advances,
        // bonuses), not the statutory calculation, so run them with the tax
        // config zeroed — then net equals gross. Tunisian CNSS/IRPP/CSS have
        // their own dedicated test (PayrollTaxTest).
        \App\Models\PayrollSetting::current()->update([
            'cnss_employee_rate' => 0, 'cnss_employer_rate' => 0, 'css_rate' => 0,
            'expense_abatement_rate' => 0, 'expense_abatement_cap' => 0,
            'head_of_family_deduction' => 0, 'child_deduction' => 0,
            'irpp_brackets' => [['upto' => null, 'rate' => 0]],
        ]);
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->emp = Employee::create([
            'code' => 'EMP-1', 'first_name' => 'Ali', 'last_name' => 'Ben Salah',
            'base_salary' => 1000,
        ]);
    }

    /** Net movement on a mapped account, debit positive. */
    private function bal(string $key): float
    {
        $code = AccountMap::code($key);

        return (float) DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('a.code', $code)
            ->sum(DB::raw('l.debit - l.credit'));
    }

    private function assertAllEntriesBalance(): void
    {
        foreach (JournalEntry::with('lines')->get() as $e) {
            $this->assertEqualsWithDelta(
                $e->lines->sum('debit'), $e->lines->sum('credit'), 0.001,
                "Entry {$e->number} does not balance."
            );
        }
    }

    // ---------- advances ----------

    public function test_paying_an_advance_moves_money_and_records_the_debt(): void
    {
        $advance = PayrollService::requestAdvance($this->emp, 300, $this->manager, reason: 'Sickness');
        $this->assertSame(EmployeeAdvance::STATUS_PENDING, $advance->status);
        $this->assertSame(0.0, $this->bal('employee_advances'));   // nothing posted yet

        PayrollService::payAdvance($advance, $this->manager);

        $advance->refresh();
        $this->assertSame(EmployeeAdvance::STATUS_PAID, $advance->status);
        // Dr employee advances (asset up), Cr cash (money out).
        $this->assertEqualsWithDelta(300, $this->bal('employee_advances'), 0.001);
        $this->assertEqualsWithDelta(-300, $this->bal('cash'), 0.001);
        $this->assertNotNull($advance->journal_entry_id);
    }

    public function test_an_advance_cannot_be_paid_twice(): void
    {
        $advance = PayrollService::requestAdvance($this->emp, 300, $this->manager);
        PayrollService::payAdvance($advance, $this->manager);

        $this->expectException(InvalidTransition::class);
        PayrollService::payAdvance($advance->refresh(), $this->manager);
    }

    // ---------- pay run ----------

    public function test_a_simple_run_posts_a_balanced_entry(): void
    {
        $run = PayrollService::createRun('2026-06-01', $this->manager);
        $this->assertCount(1, $run->payslips);
        $this->assertEqualsWithDelta(1000, (float) $run->payslips->first()->net_pay, 0.001);

        PayrollService::approveRun($run, $this->manager);

        // Dr salary expense 1000 / Cr salaries payable 1000.
        $this->assertEqualsWithDelta(1000, $this->bal('salary_expense'), 0.001);
        $this->assertEqualsWithDelta(-1000, $this->bal('salaries_payable'), 0.001);
        $this->assertAllEntriesBalance();
    }

    public function test_a_bonus_increases_gross_and_the_expense(): void
    {
        $run = PayrollService::createRun('2026-06-01', $this->manager);
        $slip = $run->payslips->first();

        PayrollService::addLine($slip->load('run'), PayslipLine::EARNING, 'Prime de rendement', 200, isBonus: true);

        $slip->refresh();
        $this->assertEqualsWithDelta(1200, (float) $slip->gross_pay, 0.001);
        $this->assertEqualsWithDelta(1200, (float) $slip->net_pay, 0.001);

        PayrollService::approveRun($run->refresh(), $this->manager);
        $this->assertEqualsWithDelta(1200, $this->bal('salary_expense'), 0.001);
        $this->assertAllEntriesBalance();
    }

    public function test_an_outstanding_advance_is_taken_back_from_the_payslip(): void
    {
        // Pay a 300 advance first.
        $advance = PayrollService::requestAdvance($this->emp, 300, $this->manager);
        PayrollService::payAdvance($advance, $this->manager);

        // The run should auto-add a recovery line.
        $run = PayrollService::createRun('2026-06-01', $this->manager);
        $slip = $run->payslips->first();
        $this->assertEqualsWithDelta(300, (float) $slip->advance_recovered, 0.001);
        // Net = 1000 base − 300 recovered.
        $this->assertEqualsWithDelta(700, (float) $slip->net_pay, 0.001);

        PayrollService::approveRun($run, $this->manager);

        // Salary expense is still the full 1000 (the advance wasn't extra pay).
        $this->assertEqualsWithDelta(1000, $this->bal('salary_expense'), 0.001);
        // Salaries payable is the net 700.
        $this->assertEqualsWithDelta(-700, $this->bal('salaries_payable'), 0.001);
        // The advance asset is relieved: +300 when paid, −300 now = 0.
        $this->assertEqualsWithDelta(0, $this->bal('employee_advances'), 0.001);
        $this->assertAllEntriesBalance();

        // And the advance itself is marked recovered.
        $this->assertSame(EmployeeAdvance::STATUS_RECOVERED, $advance->refresh()->status);
    }

    public function test_paying_the_run_moves_the_net_out_of_the_bank(): void
    {
        $run = PayrollService::createRun('2026-06-01', $this->manager);
        PayrollService::approveRun($run, $this->manager);
        PayrollService::payRun($run->refresh(), $this->manager, method: 'cash');

        // Salaries payable cleared, cash down by the net.
        $this->assertEqualsWithDelta(0, $this->bal('salaries_payable'), 0.001);
        $this->assertEqualsWithDelta(-1000, $this->bal('cash'), 0.001);
        $this->assertSame(PayrollRun::STATUS_PAID, $run->refresh()->status);
        $this->assertAllEntriesBalance();
    }

    public function test_lines_cannot_be_added_after_approval(): void
    {
        $run = PayrollService::createRun('2026-06-01', $this->manager);
        PayrollService::approveRun($run, $this->manager);

        $this->expectException(InvalidTransition::class);
        PayrollService::addLine($run->payslips->first()->load('run'), PayslipLine::EARNING, 'Late', 50);
    }

    public function test_only_one_open_run_per_month(): void
    {
        PayrollService::createRun('2026-06-01', $this->manager);

        $this->expectException(InvalidTransition::class);
        PayrollService::createRun('2026-06-15', $this->manager);
    }

    // ---------- API & RBAC ----------

    public function test_payroll_hidden_when_the_feature_is_off(): void
    {
        \App\Models\FeatureFlag::where('key', 'payroll')->update(['enabled' => false]);
        \App\Models\FeatureFlag::flush();

        // A disabled module looks absent (404), not forbidden.
        $this->actingAs($this->manager, 'api')->getJson('/api/v1/employees')->assertStatus(404);
    }

    public function test_creating_an_employee_generates_a_code_without_a_numbering_sequence(): void
    {
        // No `emp` numbering sequence is seeded, so the legacy fallback runs.
        // It must count against the employees table's actual numbering column
        // (`code`), not the `number` column other documents use — otherwise
        // the query hits an undefined column and the request 500s.
        $this->assertSame(0, \App\Models\NumberingSequence::where('key', 'emp')->count());

        $response = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/employees', [
                'first_name' => 'Nadia',
                'base_salary' => 1200,
            ])
            ->assertStatus(201);

        $this->assertNotEmpty($response->json('code'));
        $this->assertStringStartsWith('EMP-', $response->json('code'));
    }

    public function test_employee_cannot_run_payroll(): void
    {
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $run = PayrollService::createRun('2026-06-01', $this->manager);

        $this->actingAs($employee, 'api')->getJson('/api/v1/payroll/runs')->assertOk();
        $this->actingAs($employee, 'api')
            ->postJson("/api/v1/payroll/runs/{$run->id}/approve")
            ->assertStatus(403);
    }
}
