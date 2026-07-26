<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTransition;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentInstrument;
use App\Models\ReconciliationMatch;
use App\Models\User;
use App\Services\InstrumentService;
use App\Services\PaymentService;
use App\Services\ReconciliationService;
use App\Support\AccountMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bank statement import, matching and the reconciliation report. */
class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private BankAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        AccountMap::flush();

        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->account = BankAccount::create([
            'bank_id' => Bank::where('code', 'BIAT')->value('id'),
            'label' => 'Compte courant',
            'currency' => 'TND',
            'opening_balance' => 1000,
            'current_balance' => 1000,
        ]);
    }

    // ---------- CSV parsing ----------

    public function test_parses_a_semicolon_csv_with_french_headers_and_comma_decimals(): void
    {
        $csv = "Date;Libelle;Reference;Montant;Solde\n"
            . "05/03/2026;VIREMENT RECU;VIR-01;1 200,500;2 200,500\n"
            . "07/03/2026;FRAIS DE TENUE;;-12,000;2 188,500\n";

        $rows = ReconciliationService::parseCsv($csv);

        $this->assertCount(2, $rows);
        $this->assertSame('2026-03-05', $rows[0]['operation_date']);
        $this->assertEqualsWithDelta(1200.5, $rows[0]['amount'], 0.001);
        $this->assertSame('VIR-01', $rows[0]['reference']);
        // Negative amounts stay negative (money out).
        $this->assertEqualsWithDelta(-12, $rows[1]['amount'], 0.001);
    }

    public function test_parses_separate_debit_and_credit_columns(): void
    {
        $csv = "date,libelle,debit,credit\n"
            . "2026-03-05,Encaissement cheque,,850.000\n"
            . "2026-03-06,Retour impaye,850.000,\n";

        $rows = ReconciliationService::parseCsv($csv);

        $this->assertEqualsWithDelta(850, $rows[0]['amount'], 0.001);
        $this->assertEqualsWithDelta(-850, $rows[1]['amount'], 0.001);
    }

    public function test_rejects_a_file_without_an_amount_column(): void
    {
        $this->expectException(InvalidTransition::class);
        ReconciliationService::parseCsv("date,libelle\n2026-03-05,Something\n");
    }

    public function test_footer_rows_without_a_date_are_skipped(): void
    {
        $csv = "date,libelle,montant\n"
            . "05/03/2026,Virement,100,000\n"
            . "TOTAL,,100,000\n";

        $rows = ReconciliationService::parseCsv($csv);
        $this->assertCount(1, $rows);
    }

    // ---------- import ----------

    public function test_import_stores_rows_and_skips_duplicates(): void
    {
        $rows = [
            ['operation_date' => '2026-03-05', 'label' => 'VIR', 'reference' => 'A1', 'amount' => 500],
            ['operation_date' => '2026-03-06', 'label' => 'FRAIS', 'reference' => '', 'amount' => -12],
        ];

        $first = ReconciliationService::import($this->account, $rows, $this->manager);
        $this->assertSame(2, $first['imported']);
        $this->assertSame(0, $first['skipped']);

        // Re-importing an overlapping statement must not duplicate anything.
        $second = ReconciliationService::import($this->account, $rows, $this->manager);
        $this->assertSame(0, $second['imported']);
        $this->assertSame(2, $second['skipped']);
        $this->assertSame(2, BankTransaction::count());
    }

    public function test_two_identical_lines_in_one_statement_are_both_kept(): void
    {
        // Two equal cash deposits on the same day with no reference is real
        // money twice, not a duplicate — dropping the second would silently
        // break the reconciliation.
        $rows = [
            ['operation_date' => '2026-03-05', 'label' => 'VERSEMENT', 'reference' => '', 'amount' => 200],
            ['operation_date' => '2026-03-05', 'label' => 'VERSEMENT', 'reference' => '', 'amount' => 200],
        ];

        $result = ReconciliationService::import($this->account, $rows, $this->manager);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(2, BankTransaction::count());
    }

    // ---------- matching ----------

    private function transaction(float $amount, string $label = 'VIR', string $ref = ''): BankTransaction
    {
        return BankTransaction::create([
            'bank_account_id' => $this->account->id,
            'operation_date' => now()->toDateString(),
            'label' => $label,
            'reference' => $ref,
            'amount' => $amount,
            'created_by' => $this->manager->id,
        ]);
    }

    public function test_matching_a_payment_marks_the_line_matched(): void
    {
        $customer = Customer::create(['name' => 'Ahmed']);
        $payment = PaymentService::record([
            'direction' => Payment::DIRECTION_IN,
            'method' => Payment::METHOD_TRANSFER,
            'amount' => 500,
            'customer_id' => $customer->id,
            'bank_account_id' => $this->account->id,
        ], $this->manager);

        $tx = $this->transaction(500);
        ReconciliationService::match(
            $tx, ReconciliationMatch::TYPE_PAYMENT, $payment->id, 500, $this->manager
        );

        $tx->refresh();
        $this->assertSame(BankTransaction::STATUS_MATCHED, $tx->status);
        $this->assertEqualsWithDelta(0, $tx->remainingAmount(), 0.001);
    }

    public function test_partial_match_leaves_the_line_partially_matched(): void
    {
        $tx = $this->transaction(500);
        ReconciliationService::match(
            $tx, ReconciliationMatch::TYPE_ADJUSTMENT, null, 200, $this->manager, 'part'
        );

        $tx->refresh();
        $this->assertSame(BankTransaction::STATUS_PARTIAL, $tx->status);
        $this->assertEqualsWithDelta(300, $tx->remainingAmount(), 0.001);
    }

    public function test_cannot_match_more_than_the_line_is_worth(): void
    {
        $tx = $this->transaction(500);

        $this->expectException(InvalidTransition::class);
        ReconciliationService::match(
            $tx, ReconciliationMatch::TYPE_ADJUSTMENT, null, 600, $this->manager
        );
    }

    public function test_matching_a_deposited_cheque_clears_it(): void
    {
        $customer = Customer::create(['name' => 'Ahmed']);
        $cheque = InstrumentService::create([
            'kind' => PaymentInstrument::KIND_CHEQUE,
            'direction' => PaymentInstrument::DIRECTION_IN,
            'amount' => 850,
            'issue_date' => now()->toDateString(),
            'customer_id' => $customer->id,
            'bank_account_id' => $this->account->id,
        ], $this->manager);
        InstrumentService::deposit($cheque, $this->manager, $this->account->id);

        $tx = $this->transaction(850, 'REMISE CHEQUE');
        ReconciliationService::match(
            $tx, ReconciliationMatch::TYPE_INSTRUMENT, $cheque->id, 850, $this->manager
        );

        // The bank line IS the moment it cleared.
        $this->assertSame(PaymentInstrument::STATUS_CLEARED, $cheque->refresh()->status);
    }

    public function test_side_effects_can_be_skipped_when_backfilling(): void
    {
        $customer = Customer::create(['name' => 'Ahmed']);
        $cheque = InstrumentService::create([
            'kind' => PaymentInstrument::KIND_CHEQUE,
            'direction' => PaymentInstrument::DIRECTION_IN,
            'amount' => 850,
            'issue_date' => now()->toDateString(),
            'customer_id' => $customer->id,
            'bank_account_id' => $this->account->id,
        ], $this->manager);
        InstrumentService::deposit($cheque, $this->manager, $this->account->id);

        $tx = $this->transaction(850);
        ReconciliationService::match(
            $tx, ReconciliationMatch::TYPE_INSTRUMENT, $cheque->id, 850,
            $this->manager, '', applySideEffects: false
        );

        $this->assertSame(PaymentInstrument::STATUS_DEPOSITED, $cheque->refresh()->status);
    }

    public function test_an_adjustment_posts_a_balanced_entry(): void
    {
        $tx = $this->transaction(-12, 'FRAIS DE TENUE DE COMPTE');

        $match = ReconciliationService::match(
            $tx, ReconciliationMatch::TYPE_ADJUSTMENT, null, 12, $this->manager, 'Bank charges'
        );

        $this->assertNotNull($match->journal_entry_id);
        $entry = $match->journalEntry->load('lines.account');
        $this->assertEqualsWithDelta(
            $entry->lines->sum('debit'), $entry->lines->sum('credit'), 0.001
        );
        // Money out with no ERP counterpart is a bank fee.
        $this->assertContains(
            AccountMap::code('bank_fees'),
            $entry->lines->pluck('account.code')->all()
        );
    }

    public function test_unmatching_reopens_the_line(): void
    {
        $tx = $this->transaction(500);
        $match = ReconciliationService::match(
            $tx, ReconciliationMatch::TYPE_ADJUSTMENT, null, 500, $this->manager
        );
        $this->assertSame(BankTransaction::STATUS_MATCHED, $tx->refresh()->status);

        ReconciliationService::unmatch($match, $this->manager);
        $this->assertSame(BankTransaction::STATUS_UNMATCHED, $tx->refresh()->status);
    }

    public function test_disputed_line_stays_disputed_until_fully_matched(): void
    {
        $tx = $this->transaction(500);
        ReconciliationService::dispute($tx, 'Unknown debit', $this->manager);
        $this->assertSame(BankTransaction::STATUS_DISPUTED, $tx->refresh()->status);

        ReconciliationService::match(
            $tx, ReconciliationMatch::TYPE_ADJUSTMENT, null, 200, $this->manager
        );
        $this->assertSame(BankTransaction::STATUS_DISPUTED, $tx->refresh()->status);

        ReconciliationService::match(
            $tx->refresh(), ReconciliationMatch::TYPE_ADJUSTMENT, null, 300, $this->manager
        );
        $this->assertSame(BankTransaction::STATUS_MATCHED, $tx->refresh()->status);
    }

    // ---------- suggestions ----------

    public function test_suggestions_rank_an_exact_amount_first(): void
    {
        $customer = Customer::create(['name' => 'Ahmed']);
        PaymentService::record([
            'direction' => Payment::DIRECTION_IN,
            'method' => Payment::METHOD_TRANSFER,
            'amount' => 999,
            'customer_id' => $customer->id,
            'bank_account_id' => $this->account->id,
        ], $this->manager);
        PaymentService::record([
            'direction' => Payment::DIRECTION_IN,
            'method' => Payment::METHOD_TRANSFER,
            'amount' => 500,
            'customer_id' => $customer->id,
            'bank_account_id' => $this->account->id,
        ], $this->manager);

        $tx = $this->transaction(500);
        $suggestions = ReconciliationService::suggestions($tx);

        $this->assertNotEmpty($suggestions);
        $this->assertEqualsWithDelta(500, (float) $suggestions[0]['amount'], 0.001);
    }

    // ---------- report ----------

    public function test_report_summarises_matched_and_open_items(): void
    {
        $matched = $this->transaction(500);
        ReconciliationService::match(
            $matched, ReconciliationMatch::TYPE_ADJUSTMENT, null, 500, $this->manager
        );
        $this->transaction(-12, 'FRAIS');

        $report = ReconciliationService::report($this->account);

        $this->assertSame(2, $report['counts']['total']);
        $this->assertSame(1, $report['counts']['matched']);
        $this->assertSame(1, $report['counts']['unmatched']);
        $this->assertCount(1, $report['open_items']);
        // Opening 1000 + 500 - 12
        $this->assertEqualsWithDelta(1488, (float) $report['statement_balance'], 0.001);
    }

    public function test_report_lists_instruments_deposited_but_not_yet_credited(): void
    {
        $customer = Customer::create(['name' => 'Ahmed']);
        $cheque = InstrumentService::create([
            'kind' => PaymentInstrument::KIND_CHEQUE,
            'direction' => PaymentInstrument::DIRECTION_IN,
            'amount' => 300,
            'issue_date' => now()->toDateString(),
            'customer_id' => $customer->id,
            'bank_account_id' => $this->account->id,
        ], $this->manager);
        InstrumentService::deposit($cheque, $this->manager, $this->account->id);

        $report = ReconciliationService::report($this->account);

        $this->assertSame(1, $report['instruments_in_transit']['count']);
        $this->assertEqualsWithDelta(300, $report['instruments_in_transit']['amount'], 0.001);
    }

    // ---------- RBAC ----------

    public function test_employee_cannot_match_or_import(): void
    {
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $tx = $this->transaction(500);

        $this->actingAs($employee, 'api')
            ->getJson("/api/v1/reconciliation/{$tx->id}/suggestions")
            ->assertOk();

        $this->actingAs($employee, 'api')
            ->postJson("/api/v1/reconciliation/{$tx->id}/match", [
                'matchable_type' => 'adjustment',
                'amount' => 500,
            ])
            ->assertStatus(403);

        $this->actingAs($employee, 'api')
            ->postJson('/api/v1/bank-transactions/import', [
                'bank_account_id' => $this->account->id,
            ])
            ->assertStatus(403);
    }
}
