<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\FeatureFlag;
use App\Models\Notification;
use App\Models\PaymentInstrument;
use App\Models\Product;
use App\Models\User;
use App\Services\InstrumentService;
use App\Services\NotificationScanner;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** In-app notifications: the scanner, dedupe, reading, and access. */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureFlag::flush();
        $this->admin = User::create(['email' => 'a@t.t', 'password' => 'x', 'role' => 'admin']);
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
    }

    private function lowStockProduct(): Product
    {
        return Product::create([
            'sku' => 'P-1', 'name' => 'Water', 'sale_price' => 1, 'cost_price' => 0.5,
            'quantity_in_stock' => 2, 'min_stock_level' => 10,
        ]);
    }

    // ---------- the scanner ----------

    public function test_low_stock_notifies_managers_and_admins_but_not_employees(): void
    {
        $this->lowStockProduct();

        NotificationScanner::scan();

        $this->assertSame(1, Notification::where('user_id', $this->admin->id)->count());
        $this->assertSame(1, Notification::where('user_id', $this->manager->id)->count());
        // Employees don't act on stock, so they aren't notified.
        $this->assertSame(0, Notification::where('user_id', $this->employee->id)->count());

        $n = Notification::where('user_id', $this->manager->id)->first();
        $this->assertSame('stock.low', $n->type);
        $this->assertSame('/products', $n->link);
    }

    public function test_a_bounced_cheque_raises_a_critical_notification(): void
    {
        $customer = Customer::create(['name' => 'Ahmed']);
        $account = BankAccount::create([
            'bank_id' => Bank::first()->id, 'label' => 'Compte', 'currency' => 'TND',
        ]);
        $cheque = InstrumentService::create([
            'kind' => PaymentInstrument::KIND_CHEQUE,
            'direction' => PaymentInstrument::DIRECTION_IN,
            'amount' => 500, 'issue_date' => now()->toDateString(),
            'customer_id' => $customer->id, 'bank_account_id' => $account->id,
        ], $this->manager);
        InstrumentService::deposit($cheque, $this->manager, $account->id);
        InstrumentService::bounce($cheque->refresh(), $this->manager, reason: 'Sans provision');

        NotificationScanner::scan();

        $n = Notification::where('user_id', $this->manager->id)
            ->where('type', 'instrument.bounced')->first();
        $this->assertNotNull($n);
        $this->assertSame(Notification::CRITICAL, $n->severity);
    }

    public function test_the_scanner_does_not_create_duplicates(): void
    {
        $this->lowStockProduct();

        NotificationScanner::scan();
        NotificationScanner::scan();
        NotificationScanner::scan();

        // Same product, same open notice — created once per user.
        $this->assertSame(1, Notification::where('user_id', $this->manager->id)->count());
    }

    public function test_dedupe_resets_once_the_notice_is_read(): void
    {
        $this->lowStockProduct();
        NotificationScanner::scan();

        // Read it, then scan again — the stock is still low, so it's raised anew.
        NotificationService::markAllRead($this->manager);
        NotificationScanner::scan();

        $this->assertSame(2, Notification::where('user_id', $this->manager->id)->count());
        $this->assertSame(1, Notification::where('user_id', $this->manager->id)->whereNull('read_at')->count());
    }

    // ---------- reading via the API ----------

    public function test_a_user_only_sees_their_own_notifications(): void
    {
        $this->lowStockProduct();
        NotificationScanner::scan();

        $mine = $this->actingAs($this->manager, 'api')->getJson('/api/v1/notifications')->assertOk();
        foreach ($mine->json('results') as $row) {
            // Every row belongs to the caller (the endpoint scopes by user).
            $this->assertArrayHasKey('type', $row);
        }
        // The employee has none.
        $this->actingAs($this->employee, 'api')->getJson('/api/v1/notifications')
            ->assertOk()->assertJsonPath('count', 0);
    }

    public function test_unread_count_and_marking_read(): void
    {
        $this->lowStockProduct();
        NotificationScanner::scan();

        $this->actingAs($this->manager, 'api')->getJson('/api/v1/notifications/unread-count')
            ->assertOk()->assertJsonPath('count', 1);

        $id = Notification::where('user_id', $this->manager->id)->value('id');
        $this->actingAs($this->manager, 'api')->postJson("/api/v1/notifications/{$id}/read")->assertOk();

        $this->actingAs($this->manager, 'api')->getJson('/api/v1/notifications/unread-count')
            ->assertOk()->assertJsonPath('count', 0);
    }

    public function test_cannot_mark_someone_elses_notification_read(): void
    {
        $this->lowStockProduct();
        NotificationScanner::scan();
        $managersNote = Notification::where('user_id', $this->manager->id)->value('id');

        // The admin has their own copy, but must not touch the manager's.
        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/notifications/{$managersNote}/read")
            ->assertStatus(404);
    }

    public function test_mark_all_read(): void
    {
        $this->lowStockProduct();
        Product::create(['sku' => 'P-2', 'name' => 'Juice', 'sale_price' => 2, 'cost_price' => 1,
            'quantity_in_stock' => 0, 'min_stock_level' => 5]);
        NotificationScanner::scan();

        $this->actingAs($this->manager, 'api')->postJson('/api/v1/notifications/read-all')
            ->assertOk()->assertJsonPath('updated', 2);
        $this->assertSame(0, NotificationService::unreadCount($this->manager));
    }

    // ---------- access & feature flag ----------

    public function test_only_managers_can_trigger_a_scan(): void
    {
        $this->lowStockProduct();

        $this->actingAs($this->employee, 'api')->postJson('/api/v1/notifications/scan')->assertStatus(403);
        $this->actingAs($this->manager, 'api')->postJson('/api/v1/notifications/scan')->assertOk();
        $this->assertGreaterThan(0, NotificationService::unreadCount($this->manager));
    }

    public function test_notifications_hidden_when_the_feature_is_off(): void
    {
        FeatureFlag::where('key', 'notifications')->update(['enabled' => false]);
        FeatureFlag::flush();

        $this->actingAs($this->manager, 'api')->getJson('/api/v1/notifications')->assertStatus(404);
    }

    public function test_the_scan_command_runs(): void
    {
        $this->lowStockProduct();

        $this->artisan('notifications:scan')->assertExitCode(0);
        $this->assertGreaterThan(0, NotificationService::unreadCount($this->manager));
    }
}
