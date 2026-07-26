<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTransition;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\PaymentInstrument;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InstrumentService;
use App\Support\AccountMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cheques and effets de commerce (traites / kembyelet): lifecycle, the
 * postings each step produces, and the bounce reversal.
 */
class InstrumentTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Customer $customer;
    private Supplier $supplier;
    private BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();
        AccountMap::flush();

        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->customer = Customer::create(['name' => 'Ahmed Ben Ali']);
        $this->supplier = Supplier::create(['name' => 'SOTUFAB']);
        $this->bankAccount = BankAccount::create([
            'bank_id' => Bank::where('code', 'BIAT')->value('id'),
            'label' => 'Compte courant',
            'currency' => 'TND',
        ]);
    }

    private function incomingCheque(float $amount = 1000): PaymentInstrument
    {
        return InstrumentService::create([
            'kind' => PaymentInstrument::KIND_CHEQUE,
            'direction' => PaymentInstrument::DIRECTION_IN,
            'instrument_reference' => '123456',
            'amount' => $amount,
            'issue_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'bank_account_id' => $this->bankAccount->id,
        ], $this->manager);
    }

    /** Net movement on one account across every entry, debit positive. */
    private function balanceOf(string $mappingKey): float
    {
        $code = AccountMap::code($mappingKey);

        return (float) DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('a.code', $code)
            ->sum(DB::raw('l.debit - l.credit'));
    }

    // ---------- creation ----------

    public function test_receiving_a_cheque_converts_the_receivable(): void
    {
        $cheque = $this->incomingCheque(1000);

        $this->assertSame(PaymentInstrument::STATUS_RECEIVED, $cheque->status);
        $this->assertStringStartsWith('CHQ-', $cheque->number);

        // Dr cheques receivable / Cr accounts receivable — the debt changed
        // form, it did not disappear.
        $this->assertEqualsWithDelta(1000, $this->balanceOf('cheques_receivable'), 0.001);
        $this->assertEqualsWithDelta(-1000, $this->balanceOf('receivable'), 0.001);
    }

    public function test_a_traite_uses_its_own_accounts(): void
    {
        $traite = InstrumentService::create([
            'kind' => PaymentInstrument::KIND_TRAITE,
            'direction' => PaymentInstrument::DIRECTION_IN,
            'amount' => 500,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addMonths(2)->toDateString(),
            'customer_id' => $this->customer->id,
        ], $this->manager);

        $this->assertSame(PaymentInstrument::STATUS_RECEIVED, $traite->status);
        $this->assertStringStartsWith('EFF-', $traite->number);
        // Notes receivable, not cheques receivable.
        $this->assertEqualsWithDelta(500, $this->balanceOf('notes_receivable'), 0.001);
        $this->assertEqualsWithDelta(0, $this->balanceOf('cheques_receivable'), 0.001);
    }

    public function test_issued_cheque_reduces_the_payable(): void
    {
        $cheque = InstrumentService::create([
            'kind' => PaymentInstrument::KIND_CHEQUE,
            'direction' => PaymentInstrument::DIRECTION_OUT,
            'amount' => 800,
            'issue_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'bank_account_id' => $this->bankAccount->id,
        ], $this->manager);

        $this->assertSame(PaymentInstrument::STATUS_ISSUED, $cheque->status);
        // Dr payable / Cr cheques payable
        $this->assertEqualsWithDelta(800, $this->balanceOf('payable'), 0.001);
        $this->assertEqualsWithDelta(-800, $this->balanceOf('cheques_payable'), 0.001);
    }

    // ---------- happy path ----------

    public function test_deposit_then_clear_lands_the_money_in_the_bank(): void
    {
        $cheque = $this->incomingCheque(1000);

        InstrumentService::deposit($cheque, $this->manager, $this->bankAccount->id);
        $cheque->refresh();
        $this->assertSame(PaymentInstrument::STATUS_DEPOSITED, $cheque->status);
        $this->assertEqualsWithDelta(1000, $this->balanceOf('cheques_in_collection'), 0.001);
        $this->assertEqualsWithDelta(0, $this->balanceOf('cheques_receivable'), 0.001);

        InstrumentService::clear($cheque, $this->manager);
        $cheque->refresh();
        $this->assertSame(PaymentInstrument::STATUS_CLEARED, $cheque->status);
        $this->assertEqualsWithDelta(1000, $this->balanceOf('bank'), 0.001);
        $this->assertEqualsWithDelta(0, $this->balanceOf('cheques_in_collection'), 0.001);
        // The customer's debt is gone for good this time.
        $this->assertEqualsWithDelta(-1000, $this->balanceOf('receivable'), 0.001);
    }

    public function test_bank_fees_are_expensed_on_clearing(): void
    {
        $cheque = $this->incomingCheque(1000);
        InstrumentService::deposit($cheque, $this->manager, $this->bankAccount->id);
        InstrumentService::clear($cheque->refresh(), $this->manager, fees: 2.5);

        // Bank receives 997.5, 2.5 goes to fees, 1000 leaves collection.
        $this->assertEqualsWithDelta(997.5, $this->balanceOf('bank'), 0.001);
        $this->assertEqualsWithDelta(2.5, $this->balanceOf('bank_fees'), 0.001);
    }

    // ---------- bounce ----------

    public function test_bounced_cheque_reverses_and_restores_the_debt(): void
    {
        $cheque = $this->incomingCheque(850);
        InstrumentService::deposit($cheque, $this->manager, $this->bankAccount->id);

        InstrumentService::bounce(
            $cheque->refresh(),
            $this->manager,
            reason: 'Provision insuffisante',
            fees: 15,
        );
        $cheque->refresh();

        $this->assertSame(PaymentInstrument::STATUS_BOUNCED, $cheque->status);
        $this->assertSame('Provision insuffisante', $cheque->bounce_reason);

        // Collection account emptied, the customer owes us again.
        $this->assertEqualsWithDelta(0, $this->balanceOf('cheques_in_collection'), 0.001);
        $this->assertEqualsWithDelta(0, $this->balanceOf('receivable'), 0.001);
        // The return fee is ours, taken from the bank.
        $this->assertEqualsWithDelta(15, $this->balanceOf('bank_fees'), 0.001);
        $this->assertEqualsWithDelta(-15, $this->balanceOf('bank'), 0.001);
    }

    public function test_bounce_can_move_the_debt_to_doubtful_receivables(): void
    {
        $cheque = $this->incomingCheque(400);
        InstrumentService::deposit($cheque, $this->manager, $this->bankAccount->id);
        InstrumentService::bounce($cheque->refresh(), $this->manager, moveToDoubtful: true);

        $this->assertEqualsWithDelta(400, $this->balanceOf('doubtful_receivable'), 0.001);
        // The ordinary receivable stays relieved — the debt moved, not doubled.
        $this->assertEqualsWithDelta(-400, $this->balanceOf('receivable'), 0.001);
    }

    public function test_every_entry_produced_by_the_lifecycle_balances(): void
    {
        $cheque = $this->incomingCheque(1000);
        InstrumentService::deposit($cheque, $this->manager, $this->bankAccount->id);
        InstrumentService::bounce($cheque->refresh(), $this->manager, fees: 15);

        $this->assertGreaterThan(0, JournalEntry::count());
        foreach (JournalEntry::with('lines')->get() as $entry) {
            $this->assertEqualsWithDelta(
                $entry->lines->sum('debit'),
                $entry->lines->sum('credit'),
                0.001,
                "Entry {$entry->number} does not balance."
            );
        }
    }

    // ---------- state machine ----------

    public function test_cannot_clear_a_cheque_that_was_never_deposited(): void
    {
        $cheque = $this->incomingCheque();

        $this->expectException(InvalidTransition::class);
        InstrumentService::clear($cheque, $this->manager);
    }

    public function test_cannot_deposit_twice(): void
    {
        $cheque = $this->incomingCheque();
        InstrumentService::deposit($cheque, $this->manager, $this->bankAccount->id);

        $this->expectException(InvalidTransition::class);
        InstrumentService::deposit($cheque->refresh(), $this->manager, $this->bankAccount->id);
    }

    public function test_a_cleared_cheque_is_final(): void
    {
        $cheque = $this->incomingCheque();
        InstrumentService::deposit($cheque, $this->manager, $this->bankAccount->id);
        InstrumentService::clear($cheque->refresh(), $this->manager);

        $this->expectException(InvalidTransition::class);
        InstrumentService::bounce($cheque->refresh(), $this->manager);
    }

    public function test_a_bounced_cheque_can_be_redeposited_or_settled(): void
    {
        $cheque = $this->incomingCheque();
        InstrumentService::deposit($cheque, $this->manager, $this->bankAccount->id);
        InstrumentService::bounce($cheque->refresh(), $this->manager);

        InstrumentService::settle($cheque->refresh(), $this->manager, 'Paid in cash');
        $this->assertSame(PaymentInstrument::STATUS_SETTLED, $cheque->refresh()->status);
    }

    public function test_lifecycle_is_recorded_as_an_append_only_history(): void
    {
        $cheque = $this->incomingCheque();
        InstrumentService::deposit($cheque, $this->manager, $this->bankAccount->id);
        InstrumentService::clear($cheque->refresh(), $this->manager);

        $events = $cheque->refresh()->events;
        $this->assertSame(
            ['created', 'received', 'deposited', 'cleared'],
            $events->pluck('event')->all()
        );
        // Each posting step points at the entry it produced.
        $this->assertNotNull($events->firstWhere('event', 'cleared')->journal_entry_id);
    }

    // ---------- API & RBAC ----------

    public function test_employee_can_read_instruments_but_not_act_on_them(): void
    {
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $cheque = $this->incomingCheque();

        $this->actingAs($employee, 'api')->getJson('/api/v1/instruments')->assertOk();
        $this->actingAs($employee, 'api')
            ->postJson("/api/v1/instruments/{$cheque->id}/deposit")
            ->assertStatus(403);
    }

    public function test_api_refuses_an_invalid_transition_with_409(): void
    {
        $cheque = $this->incomingCheque();

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/instruments/{$cheque->id}/clear")
            ->assertStatus(409);
    }

    public function test_api_rejects_a_due_date_before_the_issue_date(): void
    {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/instruments', [
                'kind' => 'traite',
                'direction' => 'incoming',
                'amount' => 100,
                'issue_date' => '2026-05-10',
                'due_date' => '2026-05-01',
                'customer_id' => $this->customer->id,
            ])
            ->assertStatus(422);
    }

    public function test_api_rejects_mixing_a_customer_with_an_outgoing_instrument(): void
    {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/instruments', [
                'kind' => 'cheque',
                'direction' => 'outgoing',
                'amount' => 100,
                'issue_date' => now()->toDateString(),
                'customer_id' => $this->customer->id,
            ])
            ->assertStatus(422);
    }

    public function test_summary_counts_outstanding_and_bounced(): void
    {
        $a = $this->incomingCheque(1000);
        InstrumentService::deposit($a, $this->manager, $this->bankAccount->id);
        InstrumentService::bounce($a->refresh(), $this->manager);
        $this->incomingCheque(250);

        $summary = InstrumentService::summary();
        $this->assertSame(1, $summary['bounced_count']);
        $this->assertEqualsWithDelta(1000, $summary['bounced_amount'], 0.001);
        $this->assertSame(1, $summary['outstanding_incoming_count']);
        $this->assertEqualsWithDelta(250, $summary['outstanding_incoming_amount'], 0.001);
    }
}
