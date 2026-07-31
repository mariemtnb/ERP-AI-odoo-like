<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\FeatureFlag;
use App\Models\PaymentInstrument;
use App\Models\User;
use App\Services\InstrumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the hardening pass. Each one pins a hole that was
 * genuinely open, so a future refactor cannot quietly reopen it.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureFlag::flush();
        $this->admin = User::create(['email' => 'a@t.t', 'password' => 'x', 'role' => 'admin']);
        $this->employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
    }

    // ---------- deactivation must revoke access ----------

    public function test_a_deactivated_user_loses_access_immediately(): void
    {
        // Access works while the account is live.
        $this->actingAs($this->employee, 'api')->getJson('/api/v1/products')->assertOk();

        $this->employee->update(['is_active' => false]);

        // The token has not expired, but the account is gone: previously this
        // kept working until the JWT ran out.
        $this->actingAs($this->employee->refresh(), 'api')
            ->getJson('/api/v1/products')
            ->assertStatus(401);
    }

    public function test_a_deactivated_user_cannot_refresh_their_token(): void
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'e@t.t', 'password' => 'x',
        ]);
        // The seeded password is not a real hash, so log in through the model.
        if ($login->status() !== 200) {
            $this->employee->update(['password' => 'Password123!']);
            $login = $this->postJson('/api/v1/auth/login', [
                'email' => 'e@t.t', 'password' => 'Password123!',
            ])->assertOk();
        }

        $refresh = $login->json('refresh');
        $this->employee->update(['is_active' => false]);

        // Without this check a fired employee could refresh forever.
        $this->postJson('/api/v1/auth/refresh', ['refresh' => $refresh])
            ->assertStatus(401);
    }

    // ---------- self-registration is closed by default ----------

    public function test_self_registration_is_disabled_by_default(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'Password123!',
        ])->assertStatus(403);

        $this->assertNull(User::where('email', 'intruder@example.com')->first());
    }

    public function test_self_registration_works_when_a_company_opts_in(): void
    {
        FeatureFlag::where('key', 'self_registration')->update(['enabled' => true]);
        FeatureFlag::flush();

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Amine',
            'email' => 'amine@example.com',
            'password' => 'Password123!',
        ])->assertCreated();

        // Still the lowest role — opting in must not grant more than employee.
        $this->assertSame('employee', User::where('email', 'amine@example.com')->value('role'));
    }

    // ---------- attachments ----------

    public function test_an_attachment_whose_instrument_is_gone_is_not_served(): void
    {
        $customer = Customer::create(['name' => 'Ahmed']);
        BankAccount::create([
            'bank_id' => Bank::first()->id, 'label' => 'Compte', 'currency' => 'TND',
        ]);
        $instrument = InstrumentService::create([
            'kind' => PaymentInstrument::KIND_CHEQUE,
            'direction' => PaymentInstrument::DIRECTION_IN,
            'amount' => 100,
            'issue_date' => now()->toDateString(),
            'customer_id' => $customer->id,
        ], $this->admin);

        $attachment = Attachment::create([
            'owner_type' => Attachment::OWNER_INSTRUMENT,
            'owner_id' => $instrument->id,
            'path' => 'instruments/missing.png',
            'filename' => 'cheque.png',
            'uploaded_by' => $this->admin->id,
            'created_at' => now(),
        ]);

        // Orphan the attachment: the id alone must no longer be a key to the file.
        $instrument->forceDelete();

        $this->actingAs($this->employee, 'api')
            ->get("/api/v1/attachments/{$attachment->id}")
            ->assertStatus(404);
    }

    // ---------- rate limiting ----------

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'e@t.t', 'password' => 'wrong',
            ]);
        }

        // Brute-forcing a password should hit a wall, not run forever.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'e@t.t', 'password' => 'wrong',
        ])->assertStatus(429);
    }

    public function test_changing_a_password_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->employee, 'api')->postJson('/api/v1/auth/change-password', [
                'current_password' => 'guess',
                'new_password' => 'Password123!',
            ]);
        }

        // Guessing current_password was previously unbounded.
        $this->actingAs($this->employee, 'api')
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'guess',
                'new_password' => 'Password123!',
            ])
            ->assertStatus(429);
    }

    // ---------- authorization surface ----------

    public function test_sales_cannot_be_hard_deleted(): void
    {
        // Deleting a confirmed sale would orphan its stock movements and
        // journal entries; the API refuses outright.
        $this->actingAs($this->admin, 'api')
            ->deleteJson('/api/v1/sales/1')
            ->assertStatus(405);
    }

    public function test_anonymous_requests_are_rejected_everywhere(): void
    {
        foreach ([
            '/api/v1/products', '/api/v1/instruments', '/api/v1/admin/audit',
            '/api/v1/me/context', '/api/v1/payments',
        ] as $path) {
            $this->getJson($path)->assertStatus(401);
        }
    }
}
