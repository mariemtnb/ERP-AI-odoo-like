<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** RBAC matrix tests mirroring docs/ARCHITECTURE.md §3. */
class RbacTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['email' => 'a@t.t', 'password' => 'x', 'role' => 'admin']);
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
    }

    private function as(User $user)
    {
        return $this->actingAs($user, 'api');
    }

    public function test_users_module_is_admin_only(): void
    {
        $this->as($this->admin)->getJson('/api/v1/users')->assertOk();
        $this->as($this->manager)->getJson('/api/v1/users')->assertForbidden();
        $this->as($this->employee)->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_products_matrix(): void
    {
        $this->as($this->manager)
            ->postJson('/api/v1/products', ['sku' => 'T-1', 'name' => 'T'])
            ->assertCreated();
        $this->as($this->employee)
            ->postJson('/api/v1/products', ['sku' => 'T-2', 'name' => 'T'])
            ->assertForbidden();
        $this->as($this->employee)->getJson('/api/v1/products')->assertOk();
    }

    public function test_stock_movements_matrix(): void
    {
        $this->as($this->employee)->getJson('/api/v1/stock/movements')->assertOk();
        $this->as($this->employee)->postJson('/api/v1/stock/movements', [])->assertForbidden();
    }

    public function test_customers_matrix(): void
    {
        $response = $this->as($this->employee)
            ->postJson('/api/v1/customers', ['name' => 'Walk-in']);
        $response->assertCreated();
        $id = $response->json('id');

        $this->as($this->employee)
            ->patchJson("/api/v1/customers/{$id}", ['name' => 'X'])
            ->assertForbidden();
        $this->as($this->manager)
            ->postJson('/api/v1/customers', ['name' => 'Corp'])
            ->assertCreated();
    }

    public function test_suppliers_matrix(): void
    {
        $this->as($this->employee)
            ->postJson('/api/v1/suppliers', ['name' => 'S'])
            ->assertForbidden();
        $this->as($this->manager)
            ->postJson('/api/v1/suppliers', ['name' => 'S'])
            ->assertCreated();
        $this->as($this->employee)->getJson('/api/v1/suppliers')->assertOk();
    }

    public function test_anonymous_is_denied(): void
    {
        foreach (['/api/v1/products', '/api/v1/customers', '/api/v1/users'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
    }
}
