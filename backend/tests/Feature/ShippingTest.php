<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTransition;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->customer = Customer::create(['name' => 'Ali']);
    }

    private function shipment(): Shipment
    {
        return ShippingService::create(null, $this->customer->id, 'Aramex', 'Tunis', $this->manager);
    }

    public function test_ship_then_deliver_lifecycle(): void
    {
        $s = $this->shipment();
        $this->assertEquals(Shipment::STATUS_PENDING, $s->status);

        ShippingService::ship($s, 'TRK123');
        $s->refresh();
        $this->assertEquals(Shipment::STATUS_SHIPPED, $s->status);
        $this->assertEquals('TRK123', $s->tracking_number);
        $this->assertNotNull($s->shipped_at);

        ShippingService::deliver($s);
        $s->refresh();
        $this->assertEquals(Shipment::STATUS_DELIVERED, $s->status);
        $this->assertNotNull($s->delivered_at);
    }

    public function test_cannot_deliver_before_shipping(): void
    {
        $s = $this->shipment();
        $this->expectException(InvalidTransition::class);
        ShippingService::deliver($s);
    }

    public function test_cannot_ship_twice(): void
    {
        $s = $this->shipment();
        ShippingService::ship($s, null);
        $this->expectException(InvalidTransition::class);
        ShippingService::ship($s->refresh(), null);
    }

    public function test_cancel_before_delivery_but_not_after(): void
    {
        $s = $this->shipment();
        ShippingService::ship($s, null);
        ShippingService::cancel($s);
        $this->assertEquals(Shipment::STATUS_CANCELLED, $s->refresh()->status);

        $s2 = $this->shipment();
        ShippingService::ship($s2, null);
        ShippingService::deliver($s2);
        $this->expectException(InvalidTransition::class);
        ShippingService::cancel($s2->refresh());
    }

    public function test_http_flow_and_rbac(): void
    {
        // any authenticated user can create a shipment
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $s = $this->actingAs($employee, 'api')->postJson('/api/v1/shipments', [
            'customer' => $this->customer->id, 'carrier' => 'First Delivery', 'address' => 'Sfax',
        ])->assertCreated()->assertJsonPath('status', 'pending')->json();

        // but only managers can advance it
        $this->actingAs($employee, 'api')->postJson("/api/v1/shipments/{$s['id']}/ship", [])->assertForbidden();
        $this->actingAs($this->manager, 'api')->postJson("/api/v1/shipments/{$s['id']}/ship", ['tracking_number' => 'X1'])
            ->assertOk()->assertJsonPath('status', 'shipped')->assertJsonPath('tracking_number', 'X1');
        $this->actingAs($this->manager, 'api')->postJson("/api/v1/shipments/{$s['id']}/deliver")
            ->assertOk()->assertJsonPath('status', 'delivered');
    }
}
