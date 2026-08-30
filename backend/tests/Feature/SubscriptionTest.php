<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->customer = Customer::create(['name' => 'Acme']);
    }

    private function sub(string $interval = 'monthly', string $start = '2026-01-15', float $amount = 100): Subscription
    {
        return SubscriptionService::create($this->customer->id, 'Support plan', $amount, $interval, $start, $this->manager);
    }

    public function test_new_subscription_bills_from_start_date(): void
    {
        $s = $this->sub('monthly', '2026-01-15');
        $this->assertEquals('2026-01-15', $s->next_invoice_date->format('Y-m-d'));
    }

    public function test_running_billing_catches_up_missed_months_and_advances(): void
    {
        $s = $this->sub('monthly', '2026-01-15', 100);
        // as of mid-March, Jan/Feb/Mar are due -> 3 invoices
        $generated = SubscriptionService::runBilling('2026-03-20');

        $this->assertCount(3, $generated);
        $this->assertEquals(3, $s->refresh()->invoices()->count());
        $this->assertEquals('2026-04-15', $s->next_invoice_date->format('Y-m-d'));
    }

    public function test_running_twice_does_not_double_bill(): void
    {
        $s = $this->sub('monthly', '2026-01-15');
        SubscriptionService::runBilling('2026-02-20'); // Jan + Feb = 2
        $again = SubscriptionService::runBilling('2026-02-20'); // nothing new

        $this->assertCount(0, $again);
        $this->assertEquals(2, $s->refresh()->invoices()->count());
    }

    public function test_paused_subscription_is_not_billed(): void
    {
        $s = $this->sub('monthly', '2026-01-15');
        SubscriptionService::setStatus($s, Subscription::STATUS_PAUSED);

        $this->assertCount(0, SubscriptionService::runBilling('2026-06-01'));
        $this->assertEquals(0, $s->refresh()->invoices()->count());
    }

    public function test_quarterly_and_yearly_intervals_advance_correctly(): void
    {
        $q = $this->sub('quarterly', '2026-01-01');
        SubscriptionService::runBilling('2026-01-01');
        $this->assertEquals('2026-04-01', $q->refresh()->next_invoice_date->format('Y-m-d'));

        $y = $this->sub('yearly', '2026-01-01');
        SubscriptionService::runBilling('2026-01-01');
        $this->assertEquals('2027-01-01', $y->refresh()->next_invoice_date->format('Y-m-d'));
    }

    public function test_http_flow_and_rbac(): void
    {
        $this->actingAs($this->manager, 'api')->postJson('/api/v1/subscriptions', [
            'customer' => $this->customer->id, 'description' => 'Hosting',
            'amount' => 50, 'interval' => 'monthly', 'start_date' => '2026-01-01',
        ])->assertCreated()->assertJsonPath('next_invoice_date', '2026-01-01');

        $this->actingAs($this->manager, 'api')->postJson('/api/v1/subscriptions/run-billing', ['as_of' => '2026-02-15'])
            ->assertOk()->assertJsonPath('generated', 2); // Jan + Feb

        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')->getJson('/api/v1/subscriptions')->assertForbidden();
    }
}
