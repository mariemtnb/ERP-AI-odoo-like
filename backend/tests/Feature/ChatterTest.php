<?php

namespace Tests\Feature;

use App\Models\RecordActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Comments and scheduled activities on any record. */
class ChatterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\FeatureFlag::flush();
        $this->user = User::create(['email' => 'u@t.t', 'password' => 'x', 'role' => 'manager', 'first_name' => 'Amine']);
    }

    public function test_a_comment_can_be_posted_and_read_back(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/chatter/sales/5/messages', ['body' => 'Called the customer.'])
            ->assertCreated()->assertJsonPath('body', 'Called the customer.')->assertJsonPath('author', 'Amine');

        $this->actingAs($this->user, 'api')->getJson('/api/v1/chatter/sales/5')
            ->assertOk()->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.body', 'Called the customer.');
    }

    public function test_chatter_is_scoped_to_the_record(): void
    {
        $this->actingAs($this->user, 'api')->postJson('/api/v1/chatter/sales/5/messages', ['body' => 'A']);
        $this->actingAs($this->user, 'api')->postJson('/api/v1/chatter/sales/6/messages', ['body' => 'B']);

        $this->actingAs($this->user, 'api')->getJson('/api/v1/chatter/sales/5')->assertJsonCount(1, 'messages');
        // A different record type with the same id must not see it.
        $this->actingAs($this->user, 'api')->getJson('/api/v1/chatter/customers/5')->assertJsonCount(0, 'messages');
    }

    public function test_an_unknown_record_type_is_rejected(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/chatter/hackers/1/messages', ['body' => 'x'])->assertStatus(404);
    }

    public function test_an_activity_can_be_scheduled_and_completed(): void
    {
        $res = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/chatter/customers/9/activities', ['title' => 'Follow up', 'due_date' => '2020-01-01'])
            ->assertCreated()->assertJsonPath('overdue', true)->assertJsonPath('done', false);
        $id = $res->json('id');

        // It shows up in the user's own to-do list (assigned to the creator).
        $this->actingAs($this->user, 'api')->getJson('/api/v1/activities/mine')
            ->assertOk()->assertJsonCount(1, 'results');

        $this->actingAs($this->user, 'api')->postJson("/api/v1/activities/{$id}/toggle", ['done' => true])
            ->assertOk()->assertJsonPath('done', true);

        // Done activities drop off the to-do list.
        $this->actingAs($this->user, 'api')->getJson('/api/v1/activities/mine')->assertJsonCount(0, 'results');
        $this->assertNotNull(RecordActivity::find($id)->done_at);
    }
}
