<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\FeatureFlag;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\AccountingService;
use App\Support\AccountMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Analytic accounting: cost-centre tagging and per-dimension P&L. */
class AnalyticTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private BusinessUnit $sales;
    private BusinessUnit $ops;

    protected function setUp(): void
    {
        parent::setUp();
        AccountMap::flush();
        FeatureFlag::flush();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $companyId = Company::current()->id;
        $this->sales = BusinessUnit::create(['company_id' => $companyId, 'code' => 'SALES', 'name' => 'Sales', 'kind' => 'profit_centre']);
        $this->ops = BusinessUnit::create(['company_id' => $companyId, 'code' => 'OPS', 'name' => 'Operations']);
    }

    private function income(float $amount, ?int $bu): void
    {
        AccountingService::post(lines: [
            ['account' => Account::CASH, 'debit' => $amount],
            ['account' => Account::REVENUE, 'credit' => $amount, 'business_unit' => $bu],
        ], user: $this->manager, memo: 'sale', date: '2026-06-01');
    }

    private function expense(float $amount, ?int $bu): void
    {
        AccountingService::post(lines: [
            ['account' => '6000', 'debit' => $amount, 'business_unit' => $bu],
            ['account' => Account::CASH, 'credit' => $amount],
        ], user: $this->manager, memo: 'cost', date: '2026-06-01');
    }

    public function test_pnl_rolls_up_income_and_expense_per_business_unit(): void
    {
        $this->income(1000, $this->sales->id);
        $this->expense(400, $this->ops->id);
        $this->expense(100, null); // untagged → unallocated

        $res = $this->actingAs($this->manager, 'api')->getJson('/api/v1/reports/analytic')->assertOk();
        $rows = collect($res->json('rows'))->keyBy('code');

        $this->assertEqualsWithDelta(1000, $rows['SALES']['income'], 0.001);
        $this->assertEqualsWithDelta(1000, $rows['SALES']['net'], 0.001);
        $this->assertEqualsWithDelta(400, $rows['OPS']['expense'], 0.001);
        $this->assertEqualsWithDelta(-400, $rows['OPS']['net'], 0.001);

        $unallocated = collect($res->json('rows'))->firstWhere('business_unit_id', null);
        $this->assertSame('Unallocated', $unallocated['name']);
        $this->assertEqualsWithDelta(100, $unallocated['expense'], 0.001);

        $this->assertEqualsWithDelta(1000, $res->json('total_income'), 0.001);
        $this->assertEqualsWithDelta(500, $res->json('total_expense'), 0.001);
        $this->assertEqualsWithDelta(500, $res->json('total_net'), 0.001);
    }

    public function test_tagging_a_line_moves_it_out_of_unallocated(): void
    {
        $this->expense(250, null);
        $line = JournalEntryLine::where('debit', 250)->firstOrFail();
        $this->assertNull($line->business_unit_id);

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/journal-lines/{$line->id}/analytic", ['business_unit_id' => $this->ops->id])
            ->assertOk()
            ->assertJsonPath('business_unit_code', 'OPS');

        $res = $this->actingAs($this->manager, 'api')->getJson('/api/v1/reports/analytic')->assertOk();
        $this->assertEqualsWithDelta(250, collect($res->json('rows'))->firstWhere('code', 'OPS')['expense'], 0.001);
        $this->assertNull(collect($res->json('rows'))->firstWhere('business_unit_id', null)); // nothing left unallocated
    }

    public function test_tagging_with_an_unknown_unit_is_rejected(): void
    {
        $this->expense(50, null);
        $line = JournalEntryLine::where('debit', 50)->firstOrFail();

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/journal-lines/{$line->id}/analytic", ['business_unit_id' => 99999])
            ->assertStatus(422);
    }

    public function test_the_module_is_absent_when_the_flag_is_off(): void
    {
        FeatureFlag::updateOrCreate(['key' => 'analytic'], ['enabled' => false]);
        FeatureFlag::flush();
        $this->actingAs($this->manager, 'api')->getJson('/api/v1/reports/analytic')->assertStatus(404);
    }

    public function test_an_ordinary_employee_cannot_read_the_analytic_report(): void
    {
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')->getJson('/api/v1/reports/analytic')->assertStatus(403);
    }
}
