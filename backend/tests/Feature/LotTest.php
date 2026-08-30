<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStock;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\User;
use App\Services\LotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->product = Product::create(['sku' => 'MILK', 'name' => 'Milk', 'sale_price' => 2, 'cost_price' => 1]);
    }

    private function stock(): float
    {
        return (float) $this->product->refresh()->quantity_in_stock;
    }

    public function test_receiving_a_lot_raises_aggregate_stock(): void
    {
        LotService::receive($this->product->id, null, 'L1', '2026-12-31', 50, $this->user);

        $this->assertEquals(50.0, $this->stock());
        $this->assertDatabaseHas('stock_lots', ['lot_number' => 'L1', 'quantity' => 50]);
    }

    public function test_fefo_consumes_earliest_expiry_first(): void
    {
        // later expiry received first, earlier expiry received second
        LotService::receive($this->product->id, null, 'LATE', '2026-12-31', 20, $this->user);
        LotService::receive($this->product->id, null, 'SOON', '2026-09-15', 20, $this->user);

        $taken = LotService::consumeFefo($this->product->id, null, 25, $this->user, 'sale');

        // 20 from SOON (earliest) then 5 from LATE
        $this->assertEquals('SOON', $taken[0]['lot_number']);
        $this->assertEquals(20.0, $taken[0]['taken']);
        $this->assertEquals('LATE', $taken[1]['lot_number']);
        $this->assertEquals(5.0, $taken[1]['taken']);

        $this->assertEquals(0.0, (float) StockLot::where('lot_number', 'SOON')->value('quantity'));
        $this->assertEquals(15.0, (float) StockLot::where('lot_number', 'LATE')->value('quantity'));
        $this->assertEquals(15.0, $this->stock()); // 40 received - 25 consumed
    }

    public function test_undated_lots_are_consumed_last(): void
    {
        LotService::receive($this->product->id, null, 'NODATE', null, 10, $this->user);
        LotService::receive($this->product->id, null, 'DATED', '2027-01-01', 10, $this->user);

        $taken = LotService::consumeFefo($this->product->id, null, 5, $this->user);
        $this->assertEquals('DATED', $taken[0]['lot_number']);
    }

    public function test_cannot_consume_more_than_available(): void
    {
        LotService::receive($this->product->id, null, 'L1', '2026-12-31', 10, $this->user);

        $this->expectException(InsufficientStock::class);
        LotService::consumeFefo($this->product->id, null, 15, $this->user);
    }

    public function test_expiring_and_expired_queries(): void
    {
        LotService::receive($this->product->id, null, 'GOOD', now()->addDays(60)->toDateString(), 5, $this->user);
        LotService::receive($this->product->id, null, 'SOON', now()->addDays(3)->toDateString(), 5, $this->user);
        LotService::receive($this->product->id, null, 'OLD', now()->subDays(2)->toDateString(), 5, $this->user);

        $expiring = LotService::expiring(7)->pluck('lot_number')->all();
        $expired = LotService::expired()->pluck('lot_number')->all();

        $this->assertContains('SOON', $expiring);
        $this->assertNotContains('GOOD', $expiring);
        $this->assertContains('OLD', $expired);
    }

    public function test_receive_and_consume_over_http(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/lots', ['product' => $this->product->id, 'lot_number' => 'H1', 'expiry_date' => '2026-12-31', 'quantity' => 30])
            ->assertCreated()
            ->assertJsonPath('lot_number', 'H1')
            ->assertJsonPath('status', 'ok');

        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/lots/consume', ['product' => $this->product->id, 'quantity' => 10])
            ->assertOk()
            ->assertJsonPath('consumed.0.lot_number', 'H1');

        $this->assertEquals(20.0, $this->stock());
    }

    public function test_employees_cannot_receive_lots(): void
    {
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')
            ->postJson('/api/v1/lots', ['product' => $this->product->id, 'lot_number' => 'X', 'quantity' => 1])
            ->assertForbidden();
    }
}
