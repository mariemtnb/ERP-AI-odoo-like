<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTransition;
use App\Models\Ticket;
use App\Models\User;
use App\Services\HelpdeskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpdeskTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
    }

    public function test_create_with_opening_message(): void
    {
        $t = HelpdeskService::create('Broken widget', null, 'high', $this->employee, 'It stopped working.');
        $this->assertEquals(Ticket::STATUS_OPEN, $t->status);
        $this->assertEquals('high', $t->priority);
        $this->assertEquals(1, $t->messages()->count());
    }

    public function test_reply_appends_messages(): void
    {
        $t = HelpdeskService::create('Q', null, 'normal', $this->employee, 'first');
        HelpdeskService::addMessage($t, $this->manager, 'looking into it');
        $this->assertEquals(2, $t->messages()->count());
    }

    public function test_cannot_reply_to_closed(): void
    {
        $t = HelpdeskService::create('Q', null, 'normal', $this->employee, null);
        HelpdeskService::transition($t, Ticket::STATUS_CLOSED);
        $this->expectException(InvalidTransition::class);
        HelpdeskService::addMessage($t->refresh(), $this->manager, 'hi');
    }

    public function test_valid_status_lifecycle(): void
    {
        $t = HelpdeskService::create('Q', null, 'normal', $this->employee, null);
        HelpdeskService::transition($t, Ticket::STATUS_IN_PROGRESS);
        HelpdeskService::transition($t->refresh(), Ticket::STATUS_RESOLVED);
        HelpdeskService::transition($t->refresh(), Ticket::STATUS_CLOSED);
        $this->assertEquals(Ticket::STATUS_CLOSED, $t->refresh()->status);
    }

    public function test_invalid_transition_rejected(): void
    {
        $t = HelpdeskService::create('Q', null, 'normal', $this->employee, null);
        // open -> resolved is not allowed (must go through in_progress)
        $this->expectException(InvalidTransition::class);
        HelpdeskService::transition($t, Ticket::STATUS_RESOLVED);
    }

    public function test_closed_is_terminal(): void
    {
        $t = HelpdeskService::create('Q', null, 'normal', $this->employee, null);
        HelpdeskService::transition($t, Ticket::STATUS_CLOSED);
        $this->expectException(InvalidTransition::class);
        HelpdeskService::transition($t->refresh(), Ticket::STATUS_OPEN);
    }

    public function test_http_flow_and_rbac(): void
    {
        // employee creates and replies
        $t = $this->actingAs($this->employee, 'api')->postJson('/api/v1/tickets', [
            'subject' => 'Late delivery', 'priority' => 'urgent', 'message' => 'where is my order?',
        ])->assertCreated()->assertJsonPath('priority', 'urgent')->json();

        $this->actingAs($this->employee, 'api')->postJson("/api/v1/tickets/{$t['id']}/reply", ['body' => 'still waiting'])
            ->assertCreated();

        // employee cannot change status; manager can
        $this->actingAs($this->employee, 'api')->postJson("/api/v1/tickets/{$t['id']}/status/in_progress")->assertForbidden();
        $this->actingAs($this->manager, 'api')->postJson("/api/v1/tickets/{$t['id']}/status/in_progress")
            ->assertOk()->assertJsonPath('status', 'in_progress');
    }
}
