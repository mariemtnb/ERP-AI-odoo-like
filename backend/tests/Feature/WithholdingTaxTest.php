<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\FeatureFlag;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AccountMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Withholding tax (retenue à la source) on supplier payments. */
class WithholdingTaxTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        AccountMap::flush();
        FeatureFlag::flush();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->supplier = Supplier::create(['name' => 'Acme']);
    }

    private function byCode(JournalEntry $entry): array
    {
        $out = [];
        foreach ($entry->load('lines.account')->lines as $l) {
            $out[$l->account->code] ??= ['debit' => 0.0, 'credit' => 0.0];
            $out[$l->account->code]['debit'] += (float) $l->debit;
            $out[$l->account->code]['credit'] += (float) $l->credit;
        }

        return $out;
    }

    public function test_withholding_pays_net_and_owes_the_retenue(): void
    {
        $res = $this->actingAs($this->manager, 'api')->postJson('/api/v1/payments/withhold-supplier', [
            'method' => Payment::METHOD_TRANSFER, 'gross_amount' => 1000, 'withholding_rate' => 1.5,
            'supplier_id' => $this->supplier->id,
        ])->assertStatus(201)
            ->assertJsonPath('withholding_amount', '15.000');
        $this->assertEqualsWithDelta(985, (float) $res->json('amount'), 0.001);

        $entry = JournalEntry::find(Payment::find($res->json('id'))->journal_entry_id);
        $this->assertEqualsWithDelta($entry->lines->sum('debit'), $entry->lines->sum('credit'), 0.001);

        $by = $this->byCode($entry);
        $this->assertEqualsWithDelta(1000, $by[AccountMap::code('payable')]['debit'], 0.001);
        $this->assertEqualsWithDelta(985, $by[AccountMap::code('bank')]['credit'], 0.001);
        $this->assertEqualsWithDelta(15, $by[AccountMap::code('withholding_payable')]['credit'], 0.001);
    }

    public function test_the_rate_defaults_to_the_company_profile(): void
    {
        CompanyProfile::current()->update(['withholding_rate' => 3]);

        $this->actingAs($this->manager, 'api')->postJson('/api/v1/payments/withhold-supplier', [
            'method' => Payment::METHOD_CASH, 'gross_amount' => 1000, 'supplier_id' => $this->supplier->id,
        ])->assertStatus(201)->assertJsonPath('withholding_amount', '30.000');
    }

    public function test_a_zero_company_rate_with_no_override_is_rejected(): void
    {
        // Default profile withholding_rate is 0 and no rate was supplied.
        $this->actingAs($this->manager, 'api')->postJson('/api/v1/payments/withhold-supplier', [
            'method' => Payment::METHOD_CASH, 'gross_amount' => 1000, 'supplier_id' => $this->supplier->id,
        ])->assertStatus(422);
    }

    public function test_the_module_is_absent_when_the_flag_is_off(): void
    {
        FeatureFlag::updateOrCreate(['key' => 'withholding'], ['enabled' => false]);
        FeatureFlag::flush();
        $this->actingAs($this->manager, 'api')->postJson('/api/v1/payments/withhold-supplier', [
            'method' => Payment::METHOD_CASH, 'gross_amount' => 1000, 'withholding_rate' => 1.5,
        ])->assertStatus(404);
    }

    public function test_an_ordinary_employee_cannot_withhold(): void
    {
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')->postJson('/api/v1/payments/withhold-supplier', [
            'method' => Payment::METHOD_CASH, 'gross_amount' => 1000, 'withholding_rate' => 1.5,
        ])->assertStatus(403);
    }
}
