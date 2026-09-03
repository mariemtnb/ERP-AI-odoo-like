<?php

namespace Tests\Feature;

use App\Mail\PlainMail;
use App\Models\User;
use App\Services\MessagingService;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSenders;
use App\Services\Sms\TwilioSmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Outbound messaging: the pluggable SMS channel and the admin test-send. */
class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['email' => 'a@t.t', 'password' => 'x', 'role' => 'admin']);
    }

    public function test_sms_provider_defaults_to_the_log_channel(): void
    {
        config(['services.sms.provider' => 'log']);
        $this->assertInstanceOf(LogSmsSender::class, SmsSenders::current());

        config(['services.sms.provider' => 'twilio']);
        $this->assertInstanceOf(TwilioSmsSender::class, SmsSenders::current());
    }

    public function test_the_log_sms_channel_reports_success(): void
    {
        $this->assertTrue(MessagingService::sendSms('+21620000000', 'hello'));
    }

    public function test_admin_can_see_the_configured_channels(): void
    {
        $this->actingAs($this->admin, 'api')->getJson('/api/v1/admin/messaging/channels')
            ->assertOk()
            ->assertJsonStructure(['mail_mailer', 'mail_from', 'sms_provider']);
    }

    public function test_a_test_email_is_sent(): void
    {
        Mail::fake();

        $this->actingAs($this->admin, 'api')->postJson('/api/v1/admin/messaging/test', [
            'channel' => 'email', 'to' => 'ops@example.com',
        ])->assertOk()->assertJsonPath('sent', true);

        Mail::assertSent(PlainMail::class, fn ($m) => $m->hasTo('ops@example.com'));
    }

    public function test_a_test_sms_goes_through_the_log_channel(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/v1/admin/messaging/test', [
            'channel' => 'sms', 'to' => '+21620000000',
        ])->assertOk()->assertJsonPath('sent', true);
    }

    public function test_an_unknown_channel_is_rejected(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/v1/admin/messaging/test', [
            'channel' => 'carrier-pigeon', 'to' => 'x',
        ])->assertStatus(422);
    }

    public function test_only_admins_can_test_send(): void
    {
        $manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->actingAs($manager, 'api')->postJson('/api/v1/admin/messaging/test', [
            'channel' => 'email', 'to' => 'x@y.z',
        ])->assertStatus(403);
    }
}
