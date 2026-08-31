<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'email' => 'me@t.t', 'password' => 'Secret123', 'role' => 'employee',
            'first_name' => 'Sam', 'last_name' => 'Old', 'is_active' => true,
        ]);
    }

    public function test_update_name(): void
    {
        $this->actingAs($this->user, 'api')
            ->patchJson('/api/v1/auth/profile', ['first_name' => 'Sami', 'last_name' => 'New'])
            ->assertOk()->assertJsonPath('first_name', 'Sami')->assertJsonPath('last_name', 'New');
    }

    public function test_email_change_requires_current_password(): void
    {
        $this->actingAs($this->user, 'api')
            ->patchJson('/api/v1/auth/profile', ['email' => 'new@t.t', 'current_password' => 'wrong'])
            ->assertStatus(400);

        $this->actingAs($this->user, 'api')
            ->patchJson('/api/v1/auth/profile', ['email' => 'new@t.t', 'current_password' => 'Secret123'])
            ->assertOk()->assertJsonPath('email', 'new@t.t');
    }

    public function test_email_must_be_unique(): void
    {
        User::create(['email' => 'taken@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($this->user, 'api')
            ->patchJson('/api/v1/auth/profile', ['email' => 'taken@t.t', 'current_password' => 'Secret123'])
            ->assertStatus(422);
    }

    public function test_forgot_returns_dev_token_and_reset_works(): void
    {
        $res = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'me@t.t'])->assertOk();
        $token = $res->json('dev_token');
        $this->assertNotEmpty($token);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'me@t.t', 'token' => $token, 'new_password' => 'BrandNew123',
        ])->assertOk();

        // old password no longer works, new one does
        $this->postJson('/api/v1/auth/login', ['email' => 'me@t.t', 'password' => 'Secret123'])->assertStatus(401);
        $this->postJson('/api/v1/auth/login', ['email' => 'me@t.t', 'password' => 'BrandNew123'])->assertOk();
    }

    public function test_forgot_does_not_reveal_unknown_email(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@t.t'])
            ->assertOk()->assertJsonPath('dev_token', null);
    }

    public function test_reset_rejects_bad_token(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'me@t.t']);
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'me@t.t', 'token' => 'not-the-token', 'new_password' => 'Whatever123',
        ])->assertStatus(400);
    }

    public function test_change_password_flow(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/auth/change-password', ['current_password' => 'nope', 'new_password' => 'NewPass123'])
            ->assertStatus(400);
        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/auth/change-password', ['current_password' => 'Secret123', 'new_password' => 'NewPass123'])
            ->assertOk();
        $this->assertTrue(Hash::check('NewPass123', $this->user->fresh()->password));
    }
}
