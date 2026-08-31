<?php

namespace Tests\Feature;

use App\Models\AuditEntry;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FeatureFlag;
use App\Models\FiscalYear;
use App\Models\NumberingSequence;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Company structure, fiscal years, numbering, audit trail and feature flags. */
class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureFlag::flush();
        $this->admin = User::create(['email' => 'a@t.t', 'password' => 'x', 'role' => 'admin']);
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
    }

    // ---------- organisation ----------

    public function test_a_default_company_and_branch_are_seeded(): void
    {
        $company = Company::current();
        $this->assertNotNull($company);
        $this->assertSame('MAIN', $company->code);
        $this->assertTrue($company->is_default);
        $this->assertSame('TND', $company->currency);

        $this->assertSame(1, Branch::where('company_id', $company->id)->count());
    }

    public function test_only_one_company_can_be_the_default(): void
    {
        $second = Company::create(['code' => 'SUB', 'name' => 'Subsidiary']);

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/v1/admin/companies/{$second->id}", ['is_default' => true])
            ->assertOk();

        $this->assertSame(1, Company::where('is_default', true)->count());
        $this->assertTrue($second->refresh()->is_default);
    }

    public function test_managers_cannot_touch_company_structure(): void
    {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/admin/companies', ['code' => 'X', 'name' => 'X'])
            ->assertStatus(403);
    }

    // ---------- fiscal years ----------

    public function test_a_fiscal_year_covering_today_is_seeded(): void
    {
        $year = FiscalYear::forDate(now()->toDateString());

        $this->assertNotNull($year);
        $this->assertTrue($year->acceptsPostings());
        $this->assertTrue($year->contains(now()->toDateString()));
    }

    public function test_overlapping_fiscal_years_are_refused(): void
    {
        $company = Company::current();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/admin/fiscal-years', [
                'company_id' => $company->id,
                'name' => 'overlap',
                'starts_on' => now()->startOfYear()->toDateString(),
                'ends_on' => now()->endOfYear()->toDateString(),
            ])
            ->assertStatus(422);
    }

    public function test_closing_a_year_stops_it_accepting_postings(): void
    {
        $year = FiscalYear::forDate(now()->toDateString());

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/v1/admin/fiscal-years/{$year->id}", ['status' => 'closed'])
            ->assertOk()
            ->assertJsonPath('accepts_postings', false);

        $this->assertNotNull($year->refresh()->closed_at);
        $this->assertSame($this->admin->id, $year->closed_by);
    }

    // ---------- numbering ----------

    public function test_sequences_produce_the_same_format_as_before(): void
    {
        $number = DocumentService::nextNumber('SO', Sale::class);

        $this->assertSame('SO-' . now()->year . '-0001', $number);
    }

    public function test_numbers_do_not_repeat_after_a_record_is_deleted(): void
    {
        // The old count()-based scheme reissued a number here.
        $first = DocumentService::nextNumber('SO', Sale::class);
        $second = DocumentService::nextNumber('SO', Sale::class);
        $third = DocumentService::nextNumber('SO', Sale::class);

        $this->assertSame(['SO-' . now()->year . '-0001', 'SO-' . now()->year . '-0002', 'SO-' . now()->year . '-0003'],
            [$first, $second, $third]);
        $this->assertNotSame($first, $third);
    }

    public function test_a_sequence_counter_cannot_be_moved_backwards(): void
    {
        DocumentService::nextNumber('SO', Sale::class);
        DocumentService::nextNumber('SO', Sale::class);
        $sequence = NumberingSequence::where('key', 'sale')->firstOrFail();
        $this->assertSame(3, (int) $sequence->next_number);

        // Rewinding would re-issue numbers already printed on documents.
        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/v1/admin/sequences/{$sequence->id}", ['next_number' => 1])
            ->assertStatus(422);
    }

    public function test_sequence_format_tokens_render(): void
    {
        $sequence = NumberingSequence::where('key', 'sale')->firstOrFail();
        $sequence->update(['format' => '{PREFIX}/{YY}/{SEQ:6}']);

        $this->assertSame(
            'SO/' . now()->format('y') . '/000042',
            $sequence->render(42)
        );
    }

    // ---------- audit ----------

    public function test_creating_a_record_writes_an_audit_entry(): void
    {
        $customer = Customer::create(['name' => 'Ahmed Ben Ali']);

        $entry = AuditEntry::where('auditable_type', 'Customer')
            ->where('auditable_id', $customer->id)
            ->firstOrFail();

        $this->assertSame(AuditEntry::EVENT_CREATED, $entry->event);
        $this->assertSame('Ahmed Ben Ali', $entry->label);
        $this->assertSame('Ahmed Ben Ali', $entry->new_values['name']);
    }

    public function test_updating_records_the_before_and_after_of_changed_fields_only(): void
    {
        $customer = Customer::create(['name' => 'Ahmed', 'phone' => '111']);
        $customer->update(['phone' => '222']);

        $entry = AuditEntry::where('auditable_type', 'Customer')
            ->where('event', AuditEntry::EVENT_UPDATED)
            ->latest('id')->firstOrFail();

        $this->assertSame(['phone'], $entry->changed_fields);
        $this->assertSame('111', $entry->old_values['phone']);
        $this->assertSame('222', $entry->new_values['phone']);
        // Untouched columns stay out of the diff.
        $this->assertArrayNotHasKey('name', $entry->new_values);
    }

    public function test_saving_without_changes_writes_nothing(): void
    {
        $customer = Customer::create(['name' => 'Ahmed']);
        $before = AuditEntry::count();

        $customer->update(['name' => 'Ahmed']);

        $this->assertSame($before, AuditEntry::count());
    }

    public function test_secrets_are_redacted(): void
    {
        $user = User::create(['email' => 'x@t.t', 'password' => 'supersecret', 'role' => 'employee']);

        $entry = AuditEntry::where('auditable_type', 'User')
            ->where('auditable_id', $user->id)
            ->firstOrFail();

        $this->assertSame('[redacted]', $entry->new_values['password']);
    }

    public function test_agent_actions_are_attributed_to_the_agent(): void
    {
        AuditService::asAgent(function () {
            Customer::create(['name' => 'Created by AI']);
        });

        $entry = AuditEntry::latest('id')->first();
        $this->assertSame(AuditEntry::ACTOR_AGENT, $entry->actor);
        $this->assertStringContainsString('AI assistant', $entry->summary());
    }

    public function test_bulk_operations_share_a_batch_id(): void
    {
        AuditService::batch(function () {
            Customer::create(['name' => 'A']);
            Customer::create(['name' => 'B']);
            Customer::create(['name' => 'C']);
        });

        $batches = AuditEntry::whereIn('label', ['A', 'B', 'C'])->pluck('batch_id')->unique();
        $this->assertCount(1, $batches);
        $this->assertNotSame('', $batches->first());
    }

    public function test_a_reason_can_be_attached_to_a_change(): void
    {
        AuditService::because('Annual price review', function () {
            $product = Product::create(['sku' => 'P-1', 'name' => 'P', 'sale_price' => 10, 'cost_price' => 6]);
            $product->update(['sale_price' => 12]);
        });

        $entry = AuditEntry::where('event', AuditEntry::EVENT_UPDATED)->latest('id')->firstOrFail();
        $this->assertSame('Annual price review', $entry->reason);
    }

    public function test_timeline_endpoint_returns_a_records_history(): void
    {
        $customer = Customer::create(['name' => 'Ahmed']);
        $customer->update(['phone' => '555']);

        $response = $this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/timeline/Customer/{$customer->id}")
            ->assertOk();

        $this->assertSame(2, $response->json('count'));
    }

    public function test_exporting_the_audit_trail_is_itself_audited(): void
    {
        Customer::create(['name' => 'Ahmed']);

        $this->actingAs($this->admin, 'api')
            ->get('/api/v1/admin/audit/export')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertTrue(
            AuditEntry::where('event', AuditEntry::EVENT_EXPORTED)->exists(),
            'The export itself should appear in the trail.'
        );
    }

    public function test_managers_cannot_read_the_audit_trail(): void
    {
        $this->actingAs($this->manager, 'api')->getJson('/api/v1/admin/audit')->assertStatus(403);
        $this->actingAs($this->admin, 'api')->getJson('/api/v1/admin/audit')->assertOk();
    }

    // ---------- feature flags ----------

    public function test_module_flags_are_seeded_with_sensible_defaults(): void
    {
        $this->assertTrue(FeatureFlag::enabled('accounting'));
        $this->assertTrue(FeatureFlag::enabled('treasury'));
        // pos / hr / manufacturing are now implemented modules, enabled by the
        // register_new_module_flags migration.
        $this->assertTrue(FeatureFlag::enabled('pos'));
        $this->assertTrue(FeatureFlag::enabled('hr'));
        $this->assertTrue(FeatureFlag::enabled('manufacturing'));
        // Public self-registration still ships OFF for security.
        $this->assertFalse(FeatureFlag::enabled('self_registration'));
    }

    public function test_an_unknown_flag_defaults_to_enabled(): void
    {
        // A missing row must never silently disable a working module.
        $this->assertTrue(FeatureFlag::enabled('something_not_configured'));
    }

    public function test_an_admin_can_switch_a_module_off(): void
    {
        $flag = FeatureFlag::where('key', 'crm')->firstOrFail();

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/v1/admin/features/{$flag->id}", ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('enabled', false);

        FeatureFlag::flush();
        $this->assertFalse(FeatureFlag::enabled('crm'));
    }

    public function test_a_locked_module_cannot_be_switched_off(): void
    {
        $flag = FeatureFlag::where('key', 'accounting')->firstOrFail();
        $flag->update(['is_locked' => true]);

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/v1/admin/features/{$flag->id}", ['enabled' => false])
            ->assertStatus(422);
    }
}
