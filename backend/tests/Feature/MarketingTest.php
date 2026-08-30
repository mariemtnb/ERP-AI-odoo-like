<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTransition;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\User;
use App\Services\MarketingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        Customer::create(['name' => 'Has email', 'email' => 'a@x.com', 'phone' => '']);
        Customer::create(['name' => 'Has phone', 'email' => '', 'phone' => '+216 20 000 000']);
        Customer::create(['name' => 'Has both', 'email' => 'b@x.com', 'phone' => '+216 20 111 111']);
        Customer::create(['name' => 'Inactive', 'email' => 'c@x.com', 'is_active' => false]);
    }

    private function campaign(string $channel): Campaign
    {
        return MarketingService::create('Promo', $channel, 'Sale!', 'Big discounts', $this->manager);
    }

    public function test_email_audience_only_includes_active_with_email(): void
    {
        $audience = MarketingService::audience($this->campaign('email'));
        // Has email + Has both = 2 (Inactive excluded, Has phone has no email)
        $this->assertCount(2, $audience);
    }

    public function test_sms_audience_uses_phone(): void
    {
        $audience = MarketingService::audience($this->campaign('sms'));
        // Has phone + Has both = 2
        $this->assertCount(2, $audience);
    }

    public function test_sending_records_recipients_and_marks_sent(): void
    {
        $c = $this->campaign('email');
        $count = MarketingService::send($c);

        $this->assertEquals(2, $count);
        $this->assertEquals(Campaign::STATUS_SENT, $c->refresh()->status);
        $this->assertEquals(2, $c->sent_count);
        $this->assertEquals(2, $c->recipients()->count());
        $this->assertNotNull($c->sent_at);
    }

    public function test_cannot_send_twice(): void
    {
        $c = $this->campaign('email');
        MarketingService::send($c);
        $this->expectException(InvalidTransition::class);
        MarketingService::send($c->refresh());
    }

    public function test_cannot_send_with_no_audience(): void
    {
        Customer::query()->update(['email' => '', 'phone' => '']);
        $c = $this->campaign('email');
        $this->expectException(InvalidTransition::class);
        MarketingService::send($c);
    }

    public function test_http_flow_and_rbac(): void
    {
        $c = $this->actingAs($this->manager, 'api')->postJson('/api/v1/campaigns', [
            'name' => 'Eid promo', 'channel' => 'email', 'subject' => 'Eid', 'body' => 'Mabrouk',
        ])->assertCreated()->assertJsonPath('audience_size', 2)->json();

        $this->actingAs($this->manager, 'api')->postJson("/api/v1/campaigns/{$c['id']}/send")
            ->assertOk()->assertJsonPath('status', 'sent')->assertJsonPath('sent', 2);

        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')->getJson('/api/v1/campaigns')->assertForbidden();
    }
}
