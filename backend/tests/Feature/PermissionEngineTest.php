<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FieldPermission;
use App\Models\ObjectPermission;
use App\Models\Permission;
use App\Models\PermissionAudit;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The permission engine.
 *
 * The most important property is the *first* group of tests: the engine is
 * additive, so nothing an existing user could do yesterday stops working.
 */
class PermissionEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        PermissionService::flush();

        $this->admin = User::create(['email' => 'a@t.t', 'password' => 'x', 'role' => 'admin']);
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
    }

    // ---------- built-ins mirror the old RBAC matrix ----------

    public function test_built_in_roles_exist_and_inherit(): void
    {
        $employee = Role::where('key', 'employee')->firstOrFail();
        $manager = Role::where('key', 'manager')->firstOrFail();
        $admin = Role::where('key', 'admin')->firstOrFail();

        $this->assertTrue($employee->is_system);
        $this->assertSame($employee->id, $manager->parent_id);
        $this->assertSame($manager->id, $admin->parent_id);

        // Admin's lineage walks all the way down.
        $this->assertSame(
            ['admin', 'manager', 'employee'],
            array_map(fn (Role $r) => $r->key, $admin->lineage())
        );
    }

    public function test_matrix_matches_the_documented_rbac(): void
    {
        // Employee reads, and may create customers and sales.
        $this->assertTrue(PermissionService::has($this->employee, 'products.view'));
        $this->assertTrue(PermissionService::has($this->employee, 'customers.create'));
        $this->assertTrue(PermissionService::has($this->employee, 'sales.create'));
        $this->assertFalse(PermissionService::has($this->employee, 'products.create'));
        $this->assertFalse(PermissionService::has($this->employee, 'users.view'));

        // Manager runs operations but not user administration.
        $this->assertTrue(PermissionService::has($this->manager, 'products.create'));
        $this->assertTrue(PermissionService::has($this->manager, 'instruments.clear'));
        $this->assertFalse(PermissionService::has($this->manager, 'users.create'));

        // Admin can do everything, including approving large orders.
        $this->assertTrue(PermissionService::has($this->admin, 'users.create'));
        $this->assertTrue(PermissionService::has($this->admin, 'purchases.approve'));
        $this->assertFalse(PermissionService::has($this->manager, 'purchases.approve'));
    }

    public function test_inheritance_gives_managers_everything_employees_have(): void
    {
        foreach (PermissionService::keysFor($this->employee) as $key) {
            $this->assertTrue(
                PermissionService::has($this->manager, $key),
                "Manager should inherit '{$key}' from employee."
            );
        }
    }

    public function test_an_unknown_permission_key_is_denied_not_allowed(): void
    {
        $this->assertFalse(PermissionService::has($this->admin, 'nonsense.key'));
    }

    // ---------- the additive guarantee ----------

    public function test_existing_role_middleware_still_gates_every_route(): void
    {
        // Exactly the assertions RbacTest makes — proving the engine landing
        // did not shift any existing route's behaviour.
        $this->actingAs($this->employee, 'api')->getJson('/api/v1/users')->assertStatus(403);
        $this->actingAs($this->manager, 'api')->getJson('/api/v1/users')->assertStatus(403);
        $this->actingAs($this->admin, 'api')->getJson('/api/v1/users')->assertOk();
    }

    public function test_a_custom_role_inheriting_manager_passes_the_manager_gate(): void
    {
        $managerRole = Role::where('key', 'manager')->firstOrFail();
        $custom = Role::create([
            'key' => 'treasury_lead', 'name' => 'Treasury lead',
            'parent_id' => $managerRole->id, 'level' => 21,
        ]);

        // The user's users.role column still says "employee".
        DB::table('user_roles')->insert([
            'user_id' => $this->employee->id,
            'role_id' => $custom->id,
            'granted_by' => $this->admin->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        PermissionService::flush();

        // Custom roles are a way IN, never a way to be locked out.
        $this->actingAs($this->employee, 'api')
            ->getJson('/api/v1/reports/sales')
            ->assertOk();
        $this->assertTrue(PermissionService::has($this->employee, 'products.create'));
    }

    // ---------- per-user overrides ----------

    public function test_a_user_grant_beats_the_role(): void
    {
        $this->assertFalse(PermissionService::has($this->employee, 'products.create'));

        PermissionService::grantToUser($this->employee, 'products.create', $this->admin);
        PermissionService::flush();

        $this->assertTrue(PermissionService::has($this->employee, 'products.create'));
    }

    public function test_a_user_deny_beats_an_inherited_grant(): void
    {
        $this->assertTrue(PermissionService::has($this->manager, 'products.create'));

        PermissionService::grantToUser($this->manager, 'products.create', $this->admin, allow: false);
        PermissionService::flush();

        $this->assertFalse(PermissionService::has($this->manager, 'products.create'));
    }

    public function test_a_temporary_grant_stops_working_once_it_expires(): void
    {
        PermissionService::grantToUser(
            $this->employee, 'purchases.approve', $this->admin,
            expiresAt: now()->addDay()->toDateTimeString(),
            reason: 'Covering while the manager is away',
        );
        PermissionService::flush();
        $this->assertTrue(PermissionService::has($this->employee, 'purchases.approve'));

        // Travel past the expiry.
        $this->travel(2)->days();
        PermissionService::flush();

        $this->assertFalse(PermissionService::has($this->employee, 'purchases.approve'));
    }

    public function test_a_future_dated_grant_is_not_active_yet(): void
    {
        $permission = Permission::where('key', 'purchases.approve')->firstOrFail();
        DB::table('user_permissions')->insert([
            'user_id' => $this->employee->id,
            'permission_id' => $permission->id,
            'allow' => true,
            'starts_at' => now()->addWeek(),
            'granted_by' => $this->admin->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        PermissionService::flush();

        $this->assertFalse(PermissionService::has($this->employee, 'purchases.approve'));
    }

    public function test_every_grant_is_written_to_the_permission_audit(): void
    {
        PermissionService::grantToUser(
            $this->employee, 'products.create', $this->admin, reason: 'Stocktake week'
        );

        $audit = PermissionAudit::latest('id')->first();
        $this->assertSame('grant', $audit->action);
        $this->assertSame($this->admin->id, $audit->actor_id);
        $this->assertSame($this->employee->id, $audit->target_user_id);
        $this->assertSame('products.create', $audit->permission_key);
        $this->assertSame('Stocktake week', $audit->reason);
    }

    // ---------- object level ----------

    public function test_object_rules_narrow_but_never_widen(): void
    {
        $customer = Customer::create(['name' => 'Ahmed']);
        $sale = Sale::create([
            'number' => DocumentService::nextNumber('SO', Sale::class),
            'customer_id' => $customer->id,
            'sale_date' => now()->toDateString(),
            'created_by' => $this->manager->id,
        ]);

        // Manager can update sales in general, so the blanket permission stands.
        $this->assertTrue(PermissionService::canOn($this->manager, 'update', $sale));

        // A deny on this specific record narrows it.
        ObjectPermission::create([
            'user_id' => $this->manager->id,
            'subject_type' => 'sales',
            'subject_id' => $sale->id,
            'ability' => 'update',
            'allow' => false,
        ]);
        $this->assertFalse(PermissionService::canOn($this->manager, 'update', $sale));

        // An object GRANT cannot rescue someone lacking the base permission.
        ObjectPermission::create([
            'user_id' => $this->employee->id,
            'subject_type' => 'sales',
            'subject_id' => $sale->id,
            'ability' => 'delete',
            'allow' => true,
        ]);
        PermissionService::flush();
        $this->assertFalse(PermissionService::canOn($this->employee, 'delete', $sale));
    }

    // ---------- field level ----------

    public function test_field_rules_hide_columns_from_a_role(): void
    {
        $employeeRole = Role::where('key', 'employee')->firstOrFail();
        FieldPermission::create([
            'role_id' => $employeeRole->id,
            'subject_type' => 'products',
            'field' => 'cost_price',
            'access' => FieldPermission::HIDDEN,
        ]);

        $access = PermissionService::fieldAccess($this->employee, \App\Models\Product::class);
        $this->assertContains('cost_price', $access['hidden']);

        $filtered = PermissionService::filterFields(
            $this->employee,
            \App\Models\Product::class,
            ['sku' => 'P-1', 'cost_price' => '6.00', 'sale_price' => '10.00'],
        );
        $this->assertArrayNotHasKey('cost_price', $filtered);
        $this->assertArrayHasKey('sale_price', $filtered);

        // A manager without the rule still sees it.
        $this->assertNotContains(
            'cost_price',
            PermissionService::fieldAccess($this->manager, \App\Models\Product::class)['hidden']
        );
    }

    public function test_restrictions_do_not_travel_up_the_role_hierarchy(): void
    {
        // Inheritance propagates capability upward — an admin has everything an
        // employee has. Propagating a RESTRICTION the same way would invert the
        // hierarchy, hiding a column from employees and from their bosses too.
        $employeeRole = Role::where('key', 'employee')->firstOrFail();
        FieldPermission::create([
            'role_id' => $employeeRole->id, 'subject_type' => 'products',
            'field' => 'cost_price', 'access' => FieldPermission::HIDDEN,
        ]);

        $this->assertContains(
            'cost_price',
            PermissionService::fieldAccess($this->employee, \App\Models\Product::class)['hidden']
        );
        foreach ([$this->manager, $this->admin] as $senior) {
            $this->assertNotContains(
                'cost_price',
                PermissionService::fieldAccess($senior, \App\Models\Product::class)['hidden'],
                'A junior role\'s restriction must not apply to a senior one.'
            );
        }
    }

    public function test_an_explicit_visible_rule_re_grants_a_hidden_field(): void
    {
        $employeeRole = Role::where('key', 'employee')->firstOrFail();
        FieldPermission::create([
            'role_id' => $employeeRole->id, 'subject_type' => 'products',
            'field' => 'cost_price', 'access' => FieldPermission::HIDDEN,
        ]);
        FieldPermission::create([
            'user_id' => $this->employee->id, 'subject_type' => 'products',
            'field' => 'cost_price', 'access' => FieldPermission::VISIBLE,
        ]);

        $this->assertNotContains(
            'cost_price',
            PermissionService::fieldAccess($this->employee, \App\Models\Product::class)['hidden']
        );
    }

    // ---------- API ----------

    public function test_context_endpoint_gives_the_frontend_its_permissions(): void
    {
        $response = $this->actingAs($this->manager, 'api')
            ->getJson('/api/v1/me/context')
            ->assertOk();

        $this->assertContains('products.create', $response->json('permissions'));
        $this->assertNotContains('users.create', $response->json('permissions'));
        $this->assertTrue($response->json('features.accounting'));
    }

    public function test_only_admins_reach_the_administration_api(): void
    {
        $this->actingAs($this->manager, 'api')->getJson('/api/v1/admin/roles')->assertStatus(403);
        $this->actingAs($this->admin, 'api')->getJson('/api/v1/admin/roles')->assertOk();
    }

    public function test_admin_cannot_lock_itself_out_of_permission_management(): void
    {
        $adminRole = Role::where('key', 'admin')->firstOrFail();

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/v1/admin/roles/{$adminRole->id}/permissions", [
                'permissions' => ['products.view'],
            ])
            ->assertStatus(422);
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $managerRole = Role::where('key', 'manager')->firstOrFail();

        $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/v1/admin/roles/{$managerRole->id}")
            ->assertStatus(422);
    }
}
