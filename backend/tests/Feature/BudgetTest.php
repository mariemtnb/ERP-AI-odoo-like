<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\AccountingService;
use App\Support\AccountMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Budgets and budget-vs-actual against the ledger. */
class BudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        AccountMap::flush();
        FeatureFlag::flush();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
    }

    private function createBudget(): Budget
    {
        $res = $this->actingAs($this->manager, 'api')->postJson('/api/v1/budgets', [
            'name' => 'FY26', 'period_start' => '2026-01-01', 'period_end' => '2026-12-31',
            'lines' => [
                ['account_code' => Account::REVENUE, 'amount' => 1000],  // income target
                ['account_code' => '6000', 'amount' => 500],             // expense cap
            ],
        ])->assertStatus(201);

        return Budget::find($res->json('id'));
    }

    /** Post a one-line-each balanced entry touching $code on $date. */
    private function postEntry(string $code, float $amount, string $date, bool $debit): void
    {
        $lines = $debit
            ? [['account' => $code, 'debit' => $amount], ['account' => Account::CASH, 'credit' => $amount]]
            : [['account' => Account::CASH, 'debit' => $amount], ['account' => $code, 'credit' => $amount]];
        AccountingService::post(lines: $lines, user: $this->manager, memo: 'test', date: $date);
    }

    public function test_vs_actual_reads_the_ledger_over_the_period(): void
    {
        $budget = $this->createBudget();
        $this->postEntry(Account::REVENUE, 800, '2026-06-01', debit: false); // income earned 800
        $this->postEntry('6000', 600, '2026-06-01', debit: true);            // expense incurred 600
        $this->postEntry('6000', 999, '2027-02-01', debit: true);            // outside the period — ignored

        $res = $this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/budgets/{$budget->id}/vs-actual")->assertOk();

        $rows = collect($res->json('rows'))->keyBy('account_code');

        $this->assertEqualsWithDelta(1000, $rows[Account::REVENUE]['budget'], 0.001);
        $this->assertEqualsWithDelta(800, $rows[Account::REVENUE]['actual'], 0.001);
        $this->assertEqualsWithDelta(200, $rows[Account::REVENUE]['variance'], 0.001);
        $this->assertFalse($rows[Account::REVENUE]['favourable']); // under the income target

        $this->assertEqualsWithDelta(500, $rows['6000']['budget'], 0.001);
        $this->assertEqualsWithDelta(600, $rows['6000']['actual'], 0.001); // 2027 entry excluded
        $this->assertEqualsWithDelta(-100, $rows['6000']['variance'], 0.001);
        $this->assertFalse($rows['6000']['favourable']); // over the expense cap
    }

    public function test_an_expense_under_cap_is_favourable(): void
    {
        $budget = $this->createBudget();
        $this->postEntry('6000', 300, '2026-05-01', debit: true);

        $res = $this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/budgets/{$budget->id}/vs-actual")->assertOk();
        $row = collect($res->json('rows'))->firstWhere('account_code', '6000');

        $this->assertEqualsWithDelta(200, $row['variance'], 0.001);
        $this->assertTrue($row['favourable']);
    }

    public function test_a_line_can_be_upserted(): void
    {
        $budget = $this->createBudget();

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/budgets/{$budget->id}/lines", ['account_code' => Account::REVENUE, 'amount' => 1500])
            ->assertStatus(201);

        $this->assertEqualsWithDelta(1500, $budget->lines()->where('account_code', Account::REVENUE)->value('amount'), 0.001);
        $this->assertSame(2, $budget->lines()->count()); // upsert, not a duplicate
    }

    public function test_a_line_on_an_unknown_account_is_rejected(): void
    {
        $this->actingAs($this->manager, 'api')->postJson('/api/v1/budgets', [
            'name' => 'X', 'period_start' => '2026-01-01', 'period_end' => '2026-12-31',
            'lines' => [['account_code' => '9999', 'amount' => 100]],
        ])->assertStatus(422);
    }

    public function test_end_before_start_is_rejected(): void
    {
        $this->actingAs($this->manager, 'api')->postJson('/api/v1/budgets', [
            'name' => 'X', 'period_start' => '2026-12-31', 'period_end' => '2026-01-01',
        ])->assertStatus(422);
    }

    public function test_the_module_is_absent_when_the_flag_is_off(): void
    {
        FeatureFlag::updateOrCreate(['key' => 'budgets'], ['enabled' => false]);
        FeatureFlag::flush();
        $this->actingAs($this->manager, 'api')->getJson('/api/v1/budgets')->assertStatus(404);
    }

    public function test_an_ordinary_employee_cannot_see_budgets(): void
    {
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')->getJson('/api/v1/budgets')->assertStatus(403);
    }
}
