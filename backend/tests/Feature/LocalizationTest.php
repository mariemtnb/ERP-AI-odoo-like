<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\PaymentInstrument;
use App\Models\User;
use App\Services\InstrumentService;
use App\Support\AccountMap;
use App\Support\LegalValidation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The localization layer's defining property: accounting behaviour is
 * configuration, not code. These tests pin that down.
 */
class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        AccountMap::flush();
        $this->admin = User::create(['email' => 'a@t.t', 'password' => 'x', 'role' => 'admin']);
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
    }

    // ---------- company profile ----------

    public function test_profile_exists_with_tunisian_defaults(): void
    {
        $profile = CompanyProfile::current();

        $this->assertSame('TN', $profile->country);
        $this->assertSame('TND', $profile->currency);
        $this->assertSame(3, $profile->currency_decimals);
        // Legal enforcement is off by default — warnings, not blocks.
        $this->assertFalse($profile->enforce_legal_validation);
    }

    public function test_admin_can_update_the_fiscal_profile(): void
    {
        $this->actingAs($this->admin, 'api')
            ->patchJson('/api/v1/localization/profile', [
                'legal_name' => 'Demo SARL',
                'tax_id' => '1234567',
                'vat_code' => 'A',
                'default_vat_rate' => 19,
                'late_payment_grace_days' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('legal_name', 'Demo SARL')
            ->assertJsonPath('full_tax_id', '1234567/A');
    }

    public function test_manager_cannot_change_localization_settings(): void
    {
        $this->actingAs($this->manager, 'api')
            ->patchJson('/api/v1/localization/profile', ['legal_name' => 'Nope'])
            ->assertStatus(403);
    }

    public function test_a_malformed_tax_id_warns_but_still_saves(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->patchJson('/api/v1/localization/profile', ['tax_id' => '123 456'])
            ->assertOk();

        $this->assertNotEmpty($response->json('warnings'));
        $this->assertSame('123 456', CompanyProfile::current()->tax_id);
    }

    public function test_enforcement_turns_the_same_warning_into_a_rejection(): void
    {
        CompanyProfile::current()->update(['enforce_legal_validation' => true]);

        $this->actingAs($this->admin, 'api')
            ->patchJson('/api/v1/localization/profile', ['tax_id' => '123 456'])
            ->assertStatus(422);
    }

    // ---------- legal validation is advisory ----------

    public function test_rib_check_reports_shape_problems_only(): void
    {
        $this->assertEmpty(LegalValidation::checkRib(''));
        $this->assertEmpty(LegalValidation::checkRib('08100012345678901234'));
        $this->assertNotEmpty(LegalValidation::checkRib('123'));
        $this->assertNotEmpty(LegalValidation::checkRib('0810001234567890123X'));
    }

    public function test_a_short_rib_is_saved_with_a_warning_by_default(): void
    {
        $response = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/bank-accounts', [
                'bank_id' => Bank::first()->id,
                'label' => 'Compte',
                'rib' => '123',
            ])
            ->assertCreated();

        $this->assertNotEmpty($response->json('warnings'));
        $this->assertSame(1, BankAccount::count());
    }

    // ---------- journals ----------

    public function test_tunisian_journals_are_seeded(): void
    {
        foreach ([Journal::SALES, Journal::PURCHASE, Journal::CASH, Journal::BANK,
            Journal::CHEQUE, Journal::COMMERCIAL_PAPER, Journal::INSTALLMENT,
            Journal::ADVANCE, Journal::MISC] as $code) {
            $this->assertTrue(Journal::where('code', $code)->exists(), "Journal {$code} missing.");
        }
    }

    public function test_cheque_postings_are_filed_in_the_cheque_journal(): void
    {
        $customer = Customer::create(['name' => 'Ahmed']);
        InstrumentService::create([
            'kind' => PaymentInstrument::KIND_CHEQUE,
            'direction' => PaymentInstrument::DIRECTION_IN,
            'amount' => 100,
            'issue_date' => now()->toDateString(),
            'customer_id' => $customer->id,
        ], $this->manager);

        $entry = JournalEntry::where('reference_type', 'instrument')->firstOrFail();
        $this->assertSame(Journal::CHEQUE, $entry->journal->code);
    }

    public function test_a_traite_is_filed_under_commercial_paper(): void
    {
        $customer = Customer::create(['name' => 'Ahmed']);
        InstrumentService::create([
            'kind' => PaymentInstrument::KIND_TRAITE,
            'direction' => PaymentInstrument::DIRECTION_IN,
            'amount' => 100,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'customer_id' => $customer->id,
        ], $this->manager);

        $entry = JournalEntry::where('reference_type', 'instrument')->firstOrFail();
        $this->assertSame(Journal::COMMERCIAL_PAPER, $entry->journal->code);
    }

    // ---------- account mapping ----------

    public function test_every_key_the_services_use_is_mapped(): void
    {
        foreach (AccountMapping::KEYS as $key) {
            $code = AccountMap::codeOrNull($key);
            $this->assertNotNull($code, "Key '{$key}' has no mapping.");
            $this->assertTrue(
                Account::where('code', $code)->exists(),
                "Key '{$key}' maps to missing account {$code}."
            );
        }
    }

    public function test_defaults_keep_the_pre_existing_chart_so_nothing_regresses(): void
    {
        // The core keys must still resolve to the original generic accounts,
        // otherwise the localization migration would have silently changed
        // how existing sales and purchases post.
        $this->assertSame(Account::RECEIVABLE, AccountMap::code('receivable'));
        $this->assertSame(Account::PAYABLE, AccountMap::code('payable'));
        $this->assertSame(Account::CASH, AccountMap::code('cash'));
        $this->assertSame(Account::REVENUE, AccountMap::code('revenue'));
    }

    public function test_remapping_a_key_changes_where_new_entries_post(): void
    {
        $customer = Customer::create(['name' => 'Ahmed']);

        // Point cheques-in-hand at a different account.
        $this->actingAs($this->admin, 'api')
            ->patchJson('/api/v1/localization/mappings', [
                'mappings' => [['key' => 'cheques_receivable', 'account_code' => '416']],
            ])
            ->assertOk();

        InstrumentService::create([
            'kind' => PaymentInstrument::KIND_CHEQUE,
            'direction' => PaymentInstrument::DIRECTION_IN,
            'amount' => 100,
            'issue_date' => now()->toDateString(),
            'customer_id' => $customer->id,
        ], $this->manager);

        $entry = JournalEntry::with('lines.account')
            ->where('reference_type', 'instrument')->firstOrFail();
        $this->assertContains('416', $entry->lines->pluck('account.code')->all());
    }

    public function test_mapping_to_an_unknown_account_is_refused(): void
    {
        $this->actingAs($this->admin, 'api')
            ->patchJson('/api/v1/localization/mappings', [
                'mappings' => [['key' => 'receivable', 'account_code' => '99999']],
            ])
            ->assertStatus(422);
    }

    public function test_applying_the_tunisian_chart_repoints_the_keys(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/localization/chart-template', ['template' => 'tunisia'])
            ->assertOk();

        AccountMap::flush();
        $this->assertSame('411', AccountMap::code('receivable'));
        $this->assertSame('401', AccountMap::code('payable'));
        $this->assertSame('532', AccountMap::code('bank'));
        $this->assertSame('54', AccountMap::code('cash'));
    }

    public function test_switching_charts_is_reversible(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/localization/chart-template', ['template' => 'tunisia']);
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/localization/chart-template', ['template' => 'default']);

        AccountMap::flush();
        $this->assertSame(Account::RECEIVABLE, AccountMap::code('receivable'));
        // No account or history was destroyed on the way.
        $this->assertTrue(Account::where('code', '411')->exists());
    }

    public function test_manager_cannot_apply_a_chart_template(): void
    {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/localization/chart-template', ['template' => 'tunisia'])
            ->assertStatus(403);
    }

    // ---------- banks ----------

    public function test_tunisian_banks_are_seeded_without_invented_swift_codes(): void
    {
        $this->assertTrue(Bank::where('code', 'BIAT')->exists());
        $this->assertTrue(Bank::where('code', 'STB')->exists());
        // SWIFT is left blank rather than guessed.
        $this->assertSame('', Bank::where('code', 'BIAT')->value('swift'));
    }
}
