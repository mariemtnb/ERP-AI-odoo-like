<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\FeatureFlag;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\User;
use App\Support\AccountMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Settling a foreign-currency invoice and posting the realized FX gain/loss. */
class ForeignSettlementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        AccountMap::flush();
        FeatureFlag::flush();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        Currency::create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimals' => 2]);
    }

    /** @return array<string,array{debit:float,credit:float}> account code => totals */
    private function byCode(JournalEntry $entry): array
    {
        $out = [];
        foreach ($entry->load('lines.account')->lines as $l) {
            $code = $l->account->code;
            $out[$code] ??= ['debit' => 0.0, 'credit' => 0.0];
            $out[$code]['debit'] += (float) $l->debit;
            $out[$code]['credit'] += (float) $l->credit;
        }

        return $out;
    }

    private function settle(array $override = [])
    {
        return $this->actingAs($this->manager, 'api')->postJson('/api/v1/payments/settle-foreign', array_merge([
            'direction' => Payment::DIRECTION_IN,
            'method' => Payment::METHOD_TRANSFER,
            'currency_code' => 'EUR',
            'foreign_amount' => 100,
            'book_rate' => 3.0,
            'settlement_rate' => 3.2,
        ], $override));
    }

    public function test_inbound_gain_posts_a_balanced_entry_with_an_fx_gain_line(): void
    {
        $res = $this->settle()->assertStatus(201)->assertJsonPath('fx_gain_loss', '20.000');

        $entry = JournalEntry::with('lines')->find(Payment::find($res->json('id'))->journal_entry_id);
        $this->assertEqualsWithDelta($entry->lines->sum('debit'), $entry->lines->sum('credit'), 0.001);

        $by = $this->byCode($entry);
        $this->assertEqualsWithDelta(320, $by[AccountMap::code('bank')]['debit'], 0.001);      // treasury at settlement rate
        $this->assertEqualsWithDelta(300, $by[AccountMap::code('receivable')]['credit'], 0.001); // receivable at book rate
        $this->assertEqualsWithDelta(20, $by[AccountMap::code('fx_gain')]['credit'], 0.001);
    }

    public function test_inbound_loss_posts_an_fx_loss_line(): void
    {
        $res = $this->settle(['book_rate' => 3.2, 'settlement_rate' => 3.0])
            ->assertStatus(201)->assertJsonPath('fx_gain_loss', '-20.000');

        $by = $this->byCode(JournalEntry::find(Payment::find($res->json('id'))->journal_entry_id));
        $this->assertEqualsWithDelta(300, $by[AccountMap::code('bank')]['debit'], 0.001);
        $this->assertEqualsWithDelta(320, $by[AccountMap::code('receivable')]['credit'], 0.001);
        $this->assertEqualsWithDelta(20, $by[AccountMap::code('fx_loss')]['debit'], 0.001);
    }

    public function test_outbound_payment_relieves_the_payable_and_books_the_loss(): void
    {
        // Paying a 100 EUR bill booked at 3.0 but now costing 3.2 base: a loss.
        $res = $this->settle(['direction' => Payment::DIRECTION_OUT, 'supplier_id' => null])
            ->assertStatus(201)->assertJsonPath('fx_gain_loss', '-20.000');

        $by = $this->byCode(JournalEntry::find(Payment::find($res->json('id'))->journal_entry_id));
        $this->assertEqualsWithDelta(300, $by[AccountMap::code('payable')]['debit'], 0.001);
        $this->assertEqualsWithDelta(320, $by[AccountMap::code('bank')]['credit'], 0.001);
        $this->assertEqualsWithDelta(20, $by[AccountMap::code('fx_loss')]['debit'], 0.001);
    }

    public function test_no_fx_line_when_the_rate_is_unchanged(): void
    {
        $res = $this->settle(['settlement_rate' => 3.0])->assertStatus(201)->assertJsonPath('fx_gain_loss', '0.000');

        $entry = JournalEntry::with('lines')->find(Payment::find($res->json('id'))->journal_entry_id);
        $this->assertCount(2, $entry->lines);
    }

    public function test_the_base_currency_is_rejected(): void
    {
        $this->settle(['currency_code' => 'TND'])->assertStatus(422);
    }

    public function test_an_unknown_currency_is_rejected(): void
    {
        $this->settle(['currency_code' => 'GBP'])->assertStatus(422);
    }

    public function test_the_module_is_absent_when_the_flag_is_off(): void
    {
        FeatureFlag::updateOrCreate(['key' => 'foreign_currency'], ['enabled' => false]);
        FeatureFlag::flush();
        $this->settle()->assertStatus(404);
    }

    public function test_an_ordinary_employee_cannot_settle(): void
    {
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')->postJson('/api/v1/payments/settle-foreign', [
            'direction' => Payment::DIRECTION_IN, 'method' => Payment::METHOD_TRANSFER,
            'currency_code' => 'EUR', 'foreign_amount' => 100, 'book_rate' => 3.0, 'settlement_rate' => 3.2,
        ])->assertStatus(403);
    }
}
