<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStock;
use App\Exceptions\InvalidTransition;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\PosService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cashier = User::create(['email' => 'cash@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->product = Product::create([
            'sku' => 'W-1', 'name' => 'Water 1L', 'sale_price' => 1.20, 'cost_price' => 0.60,
        ]);
        // seed stock
        StockService::recordMovement(
            productId: $this->product->id,
            movementType: StockMovement::TYPE_IN,
            quantity: 100,
            user: $this->cashier,
            reason: 'seed',
        );
    }

    private function stock(): float
    {
        return (float) $this->product->refresh()->quantity_in_stock;
    }

    private function as(User $u): static
    {
        return $this->actingAs($u, 'api');
    }

    public function test_opening_a_session_creates_an_open_till(): void
    {
        $session = PosService::openSession($this->cashier, 50);

        $this->assertTrue($session->isOpen());
        $this->assertEquals('50.00', $session->opening_float);
        $this->assertSame($session->id, PosService::openSessionFor($this->cashier)->id);
    }

    public function test_a_cashier_cannot_open_two_sessions(): void
    {
        PosService::openSession($this->cashier, 0);

        $this->expectException(InvalidTransition::class);
        PosService::openSession($this->cashier, 0);
    }

    public function test_checkout_decrements_stock_and_records_change(): void
    {
        $session = PosService::openSession($this->cashier, 20);

        $order = PosService::checkout(
            $session,
            [['product' => $this->product->id, 'quantity' => 3, 'unit_price' => 1.20]],
            [['method' => 'cash', 'amount' => 5.00]],
            null,
            $this->cashier,
        );

        $this->assertEquals('3.60', $order->total_amount);
        $this->assertEquals('1.40', $order->change_due); // 5.00 - 3.60
        $this->assertEquals(97.0, $this->stock());        // 100 - 3
        $this->assertCount(1, $order->payments);
    }

    public function test_checkout_rejects_insufficient_stock_and_rolls_back(): void
    {
        $session = PosService::openSession($this->cashier, 0);

        try {
            PosService::checkout(
                $session,
                [['product' => $this->product->id, 'quantity' => 500, 'unit_price' => 1.20]],
                [['method' => 'card', 'amount' => 600]],
                null,
                $this->cashier,
            );
            $this->fail('Expected InsufficientStock.');
        } catch (InsufficientStock) {
            // rolled back: no stock moved, no order persisted
            $this->assertEquals(100.0, $this->stock());
            $this->assertEquals(0, $session->orders()->count());
        }
    }

    public function test_checkout_rejects_underpayment(): void
    {
        $session = PosService::openSession($this->cashier, 0);

        $this->expectException(InvalidTransition::class);
        PosService::checkout(
            $session,
            [['product' => $this->product->id, 'quantity' => 10, 'unit_price' => 1.20]], // 12.00
            [['method' => 'cash', 'amount' => 5.00]],
            null,
            $this->cashier,
        );
    }

    public function test_closing_computes_expected_cash_and_variance(): void
    {
        $session = PosService::openSession($this->cashier, 30);
        // cash sale of 12.00, card sale of 6.00 (card never enters the drawer)
        PosService::checkout($session, [['product' => $this->product->id, 'quantity' => 10, 'unit_price' => 1.20]], [['method' => 'cash', 'amount' => 12]], null, $this->cashier);
        PosService::checkout($session, [['product' => $this->product->id, 'quantity' => 5, 'unit_price' => 1.20]], [['method' => 'card', 'amount' => 6]], null, $this->cashier);

        $closed = PosService::closeSession($session, 41.50);

        $this->assertEquals('42.00', $closed->expected_cash); // 30 float + 12 cash
        $this->assertEquals(-0.50, $closed->variance());      // 41.50 counted - 42.00
        $this->assertFalse($closed->isOpen());
    }

    public function test_checkout_endpoint_requires_an_open_till(): void
    {
        $this->as($this->cashier)
            ->postJson('/api/v1/pos/orders', [
                'lines' => [['product' => $this->product->id, 'quantity' => 1, 'unit_price' => 1.2]],
                'payments' => [['method' => 'cash', 'amount' => 2]],
            ])
            ->assertStatus(409);
    }

    public function test_full_till_flow_over_http(): void
    {
        $this->as($this->cashier)
            ->postJson('/api/v1/pos/session/open', ['opening_float' => 20])
            ->assertCreated()
            ->assertJsonPath('status', 'open');

        $this->as($this->cashier)
            ->postJson('/api/v1/pos/orders', [
                'lines' => [['product' => $this->product->id, 'quantity' => 2, 'unit_price' => 1.2]],
                'payments' => [['method' => 'cash', 'amount' => 3]],
            ])
            ->assertCreated()
            ->assertJsonPath('total_amount', '2.40')
            ->assertJsonPath('change_due', '0.60');

        $session = PosSession::where('user_id', $this->cashier->id)->firstOrFail();
        $this->as($this->cashier)
            ->postJson("/api/v1/pos/session/{$session->id}/close", ['counted_cash' => 23])
            ->assertOk()
            ->assertJsonPath('status', 'closed')
            ->assertJsonPath('expected_cash', '23.00'); // 20 + 3 cash
    }
}
