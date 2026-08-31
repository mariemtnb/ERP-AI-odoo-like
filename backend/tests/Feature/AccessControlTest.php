<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login:vic@t.t|127.0.0.1');
    }

    public function test_account_locks_after_five_failed_logins(): void
    {
        User::create(['email' => 'vic@t.t', 'password' => 'Correct123', 'role' => 'employee']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'vic@t.t', 'password' => 'wrong'])
                ->assertStatus(401);
        }
        // 6th attempt is locked out — even with the right password.
        $this->postJson('/api/v1/auth/login', ['email' => 'vic@t.t', 'password' => 'Correct123'])
            ->assertStatus(429);
    }

    public function test_successful_login_clears_the_counter(): void
    {
        User::create(['email' => 'vic@t.t', 'password' => 'Correct123', 'role' => 'employee']);
        $this->postJson('/api/v1/auth/login', ['email' => 'vic@t.t', 'password' => 'wrong'])->assertStatus(401);
        $this->postJson('/api/v1/auth/login', ['email' => 'vic@t.t', 'password' => 'Correct123'])->assertOk();
        // counter cleared → no lockout carried over
        $this->assertFalse(RateLimiter::tooManyAttempts('login:vic@t.t|127.0.0.1', 5));
    }

    public function test_super_admin_passes_admin_gates(): void
    {
        $super = User::create(['email' => 's@t.t', 'password' => 'x', 'role' => 'super_admin']);
        $this->actingAs($super, 'api')->getJson('/api/v1/users')->assertOk();
    }

    public function test_admin_cannot_grant_admin_or_super_admin(): void
    {
        $admin = User::create(['email' => 'a@t.t', 'password' => 'x', 'role' => 'admin']);

        $this->actingAs($admin, 'api')->postJson('/api/v1/users', [
            'email' => 'new@t.t', 'password' => 'password1', 'role' => 'admin',
        ])->assertStatus(403);

        $this->actingAs($admin, 'api')->postJson('/api/v1/users', [
            'email' => 'new2@t.t', 'password' => 'password1', 'role' => 'super_admin',
        ])->assertStatus(403);

        // but an admin can still create managers/employees
        $this->actingAs($admin, 'api')->postJson('/api/v1/users', [
            'email' => 'emp@t.t', 'password' => 'password1', 'role' => 'employee',
        ])->assertCreated();
    }

    public function test_super_admin_can_grant_admin(): void
    {
        $super = User::create(['email' => 's@t.t', 'password' => 'x', 'role' => 'super_admin']);
        $this->actingAs($super, 'api')->postJson('/api/v1/users', [
            'email' => 'newadmin@t.t', 'password' => 'password1', 'role' => 'admin',
        ])->assertCreated()->assertJsonPath('role', 'admin');
    }

    public function test_admin_cannot_edit_another_admin(): void
    {
        $admin = User::create(['email' => 'a@t.t', 'password' => 'x', 'role' => 'admin']);
        $peer = User::create(['email' => 'peer@t.t', 'password' => 'x', 'role' => 'admin']);

        $this->actingAs($admin, 'api')->patchJson("/api/v1/users/{$peer->id}", ['role' => 'employee'])
            ->assertStatus(403);
    }

    public function test_api_sets_security_headers(): void
    {
        $this->getJson('/api/v1/auth/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}
