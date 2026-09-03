<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DepreciationEntry;
use App\Models\DunningLog;
use App\Models\FeatureFlag;
use App\Models\FixedAsset;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\SubscriptionService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The scheduled commands that automate the time-based business jobs. */
class SchedulerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureFlag::flush();
        Mail::fake();
        $this->admin = User::create(['id' => 1, 'email' => 'a@t.t', 'password' => 'x', 'role' => 'admin']);
    }

    public function test_dunning_run_command_sends_due_reminders(): void
    {
        $customer = Customer::create(['name' => 'Ahmed', 'email' => 'buyer@example.com']);
        $product = Product::create(['sku' => 'P1', 'name' => 'Chair', 'sale_price' => 1000, 'cost_price' => 0, 'unit' => 'pcs']);
        $sale = Sale::create(['number' => 'SO-1', 'customer_id' => $customer->id, 'sale_date' => '2026-01-01',
            'status' => Sale::STATUS_CONFIRMED, 'created_by' => 1]);
        SaleLine::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]);
        $sale->load('lines')->recomputeTotal();
        DocumentService::generateInvoice($sale->refresh())->update(['issued_at' => '2026-01-01 09:00:00']);

        $this->artisan('dunning:run', ['--as-of' => '2026-02-10'])->assertExitCode(0);

        $this->assertSame(1, DunningLog::where('sale_id', $sale->id)->count());
    }

    public function test_subscriptions_bill_command_generates_due_invoices(): void
    {
        $customer = Customer::create(['name' => 'Ahmed', 'email' => 'a@b.c']);
        SubscriptionService::create($customer->id, 'Hosting', 100, 'monthly', '2026-01-01', $this->admin);

        $this->artisan('subscriptions:bill', ['--as-of' => '2026-01-15'])->assertExitCode(0);

        $this->assertSame(1, SubscriptionInvoice::count());
    }

    public function test_assets_depreciate_command_posts_a_monthly_charge(): void
    {
        $asset = FixedAsset::create(['name' => 'Laptop', 'acquisition_date' => '2026-01-01',
            'acquisition_cost' => 1200, 'useful_life_months' => 12, 'created_by' => 1]);

        $this->artisan('assets:depreciate', ['--period' => '2026-03'])->assertExitCode(0);

        $entry = DepreciationEntry::where('fixed_asset_id', $asset->id)->first();
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(100, (float) $entry->amount, 0.01); // 1200 / 12
    }

    public function test_a_command_is_a_no_op_when_its_module_is_off(): void
    {
        FeatureFlag::updateOrCreate(['key' => 'subscriptions'], ['enabled' => false]);
        FeatureFlag::flush();
        $customer = Customer::create(['name' => 'Ahmed', 'email' => 'a@b.c']);
        SubscriptionService::create($customer->id, 'Hosting', 100, 'monthly', '2026-01-01', $this->admin);

        $this->artisan('subscriptions:bill')->assertExitCode(0);

        $this->assertSame(0, SubscriptionInvoice::count());
    }

    public function test_the_time_based_jobs_are_on_the_schedule(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command ?? '')->implode(' ');

        foreach (['notifications:scan', 'dunning:run', 'subscriptions:bill', 'assets:depreciate'] as $job) {
            $this->assertStringContainsString($job, $commands, "{$job} is not scheduled");
        }
    }
}
