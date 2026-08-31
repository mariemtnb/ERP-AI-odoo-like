<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomRoleTest extends TestCase
{
    use RefreshDatabase;

    private function super(): User
    {
        return User::create(['email' => 'super@t.t', 'password' => 'x', 'role' => 'super_admin']);
    }

    public function test_super_admin_creates_a_custom_role_with_modules(): void
    {
        $this->actingAs($this->super(), 'api')
            ->postJson('/api/v1/roles', ['name' => 'Cashier', 'modules' => ['pos', 'sales']])
            ->assertCreated()
            ->assertJsonPath('key', 'cashier')
            ->assertJsonPath('is_system', false)
            ->assertJsonPath('modules', ['sales', 'pos']); // catalogue order

        $this->assertDatabaseHas('roles', ['key' => 'cashier', 'is_system' => false]);
    }

    public function test_unknown_modules_are_rejected(): void
    {
        $this->actingAs($this->super(), 'api')
            ->postJson('/api/v1/roles', ['name' => 'Bad', 'modules' => ['pos', 'nonsense']])
            ->assertStatus(422);
    }

    public function test_admin_cannot_create_a_role_but_can_read_them(): void
    {
        $admin = User::create(['email' => 'a@t.t', 'password' => 'x', 'role' => 'admin']);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/roles', ['name' => 'X', 'modules' => ['pos']])
            ->assertStatus(403);

        $this->actingAs($admin, 'api')->getJson('/api/v1/roles')->assertOk();
    }

    public function test_context_returns_allowlist_for_custom_role_and_null_for_builtins(): void
    {
        $this->actingAs($this->super(), 'api')
            ->postJson('/api/v1/roles', ['name' => 'Cashier', 'modules' => ['pos', 'sales', 'inventory']])
            ->assertCreated();

        $cashier = User::create(['email' => 'c@t.t', 'password' => 'x', 'role' => 'cashier']);
        $this->actingAs($cashier, 'api')->getJson('/api/v1/me/context')
            ->assertOk()
            ->assertJsonPath('modules', ['inventory', 'sales', 'pos']);

        $admin = User::create(['email' => 'a@t.t', 'password' => 'x', 'role' => 'admin']);
        $this->actingAs($admin, 'api')->getJson('/api/v1/me/context')
            ->assertOk()
            ->assertJsonPath('modules', null);
    }

    public function test_a_user_can_be_assigned_a_custom_role(): void
    {
        $super = $this->super();
        $this->actingAs($super, 'api')
            ->postJson('/api/v1/roles', ['name' => 'Cashier', 'modules' => ['pos']])
            ->assertCreated();

        $this->actingAs($super, 'api')->postJson('/api/v1/users', [
            'email' => 'newcash@t.t', 'password' => 'password1', 'role' => 'cashier',
        ])->assertCreated()->assertJsonPath('role', 'cashier');
    }

    public function test_assigning_an_unknown_role_is_rejected(): void
    {
        $this->actingAs($this->super(), 'api')->postJson('/api/v1/users', [
            'email' => 'x@t.t', 'password' => 'password1', 'role' => 'ghost',
        ])->assertStatus(422);
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $super = $this->super();
        $role = Role::create(['key' => 'cashier', 'name' => 'Cashier', 'modules' => ['pos'], 'level' => 10]);
        User::create(['email' => 'c@t.t', 'password' => 'x', 'role' => 'cashier']);

        $this->actingAs($super, 'api')->deleteJson("/api/v1/roles/{$role->id}")
            ->assertStatus(422);
    }

    public function test_builtin_roles_cannot_be_edited_or_deleted(): void
    {
        $super = $this->super();
        $builtin = Role::firstOrCreate(['key' => 'admin'], ['name' => 'Admin', 'is_system' => true]);

        $this->actingAs($super, 'api')->patchJson("/api/v1/roles/{$builtin->id}", ['name' => 'Nope'])
            ->assertStatus(422);
        $this->actingAs($super, 'api')->deleteJson("/api/v1/roles/{$builtin->id}")
            ->assertStatus(422);
    }

    public function test_updating_a_custom_role_resyncs_modules(): void
    {
        $super = $this->super();
        $role = Role::create(['key' => 'cashier', 'name' => 'Cashier', 'modules' => ['pos'], 'level' => 10]);

        $this->actingAs($super, 'api')
            ->patchJson("/api/v1/roles/{$role->id}", ['modules' => ['pos', 'hr']])
            ->assertOk()
            ->assertJsonPath('modules', ['pos', 'hr']);
    }
}
