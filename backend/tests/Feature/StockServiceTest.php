<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['email' => 't@t.t', 'password' => 'x']);
        $this->product = Product::create(['sku' => 'TEST-1', 'name' => 'Test product']);
    }

    private function move(string $type, string $qty): StockMovement
    {
        return StockService::recordMovement(
            productId: $this->product->id,
            movementType: $type,
            quantity: $qty,
            user: $this->user,
        );
    }

    private function stock(): float
    {
        return (float) $this->product->refresh()->quantity_in_stock;
    }

    public function test_stock_in_increases_quantity(): void
    {
        $this->move('in', '10');
        $this->assertSame(10.0, $this->stock());
    }

    public function test_stock_out_decreases_quantity(): void
    {
        $this->move('in', '10');
        $this->move('out', '4');
        $this->assertSame(6.0, $this->stock());
    }

    public function test_out_more_than_available_raises_and_rolls_back(): void
    {
        $this->move('in', '5');
        try {
            $this->move('out', '6');
            $this->fail('Expected InsufficientStock');
        } catch (InsufficientStock) {
        }
        $this->assertSame(5.0, $this->stock());
        $this->assertSame(1, StockMovement::count());
    }

    public function test_adjustment_accepts_signed_delta(): void
    {
        $this->move('in', '10');
        $this->move('adjustment', '-3');
        $this->assertSame(7.0, $this->stock());
    }

    public function test_negative_adjustment_below_zero_rejected(): void
    {
        $this->expectException(InsufficientStock::class);
        $this->move('adjustment', '-1');
    }

    public function test_in_out_require_positive_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->move('in', '-5');
    }

    public function test_ledger_sum_matches_cached_quantity(): void
    {
        $this->move('in', '10');
        $this->move('out', '2');
        $this->move('adjustment', '1.5');
        $total = StockMovement::all()->sum(fn ($m) => match ($m->movement_type) {
            'in', 'adjustment' => (float) $m->quantity,
            'out' => -(float) $m->quantity,
        });
        $this->assertSame($total, $this->stock());
    }
}
