<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AccountingService;
use App\Support\AccountMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Closing a fiscal year: the closing entry rolls the result to retained earnings. */
class FiscalYearCloseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        AccountMap::flush();
        $this->admin = User::create(['email' => 'a@t.t', 'password' => 'x', 'role' => 'admin']);
    }

    private function year(string $status = FiscalYear::OPEN): FiscalYear
    {
        return FiscalYear::create(['company_id' => Company::current()->id, 'name' => 'FY26',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => $status]);
    }

    private function entry(string $dr, string $cr, float $amount, string $date): void
    {
        AccountingService::post(lines: [
            ['account' => $dr, 'debit' => $amount], ['account' => $cr, 'credit' => $amount],
        ], user: $this->admin, memo: 't', date: $date);
    }

    private function balance(string $code): float
    {
        return (float) DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('a.code', $code)->sum(DB::raw('l.debit - l.credit'));
    }

    public function test_closing_rolls_the_profit_into_retained_earnings(): void
    {
        $year = $this->year();
        $this->entry(Account::CASH, Account::REVENUE, 500, '2026-06-01');  // income 500
        $this->entry('6000', Account::CASH, 200, '2026-06-15');            // expense 200

        $res = $this->actingAs($this->admin, 'api')->postJson("/api/v1/admin/fiscal-years/{$year->id}/close")
            ->assertOk()->assertJsonPath('status', 'closed');
        $this->assertNotNull($res->json('closing_entry_id'));

        // The closing entry balances and moves 500/200/300.
        $entry = JournalEntry::with('lines.account')->find($res->json('closing_entry_id'));
        $this->assertEqualsWithDelta($entry->lines->sum('debit'), $entry->lines->sum('credit'), 0.01);

        // P&L is zeroed and the 300 profit now sits in retained earnings.
        $this->assertEqualsWithDelta(0, $this->balance('4000'), 0.01);
        $this->assertEqualsWithDelta(0, $this->balance('6000'), 0.01);
        $this->assertEqualsWithDelta(-300, $this->balance('3100'), 0.01); // equity: credit balance 300
    }

    public function test_a_loss_is_debited_to_retained_earnings(): void
    {
        $year = $this->year();
        $this->entry(Account::CASH, Account::REVENUE, 100, '2026-06-01');
        $this->entry('6000', Account::CASH, 400, '2026-06-15');   // net loss 300

        $this->actingAs($this->admin, 'api')->postJson("/api/v1/admin/fiscal-years/{$year->id}/close")->assertOk();
        $this->assertEqualsWithDelta(300, $this->balance('3100'), 0.01); // debit balance = loss
    }

    public function test_a_year_cannot_be_closed_twice(): void
    {
        $year = $this->year();
        $this->entry(Account::CASH, Account::REVENUE, 500, '2026-06-01');
        $this->actingAs($this->admin, 'api')->postJson("/api/v1/admin/fiscal-years/{$year->id}/close")->assertOk();

        $this->actingAs($this->admin, 'api')->postJson("/api/v1/admin/fiscal-years/{$year->id}/close")->assertStatus(422);
    }

    public function test_only_an_open_year_can_be_closed(): void
    {
        $year = $this->year(FiscalYear::CLOSED);
        $this->actingAs($this->admin, 'api')->postJson("/api/v1/admin/fiscal-years/{$year->id}/close")->assertStatus(422);
    }

    public function test_only_admins_can_close(): void
    {
        $year = $this->year();
        $manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->actingAs($manager, 'api')->postJson("/api/v1/admin/fiscal-years/{$year->id}/close")->assertStatus(403);
    }
}
