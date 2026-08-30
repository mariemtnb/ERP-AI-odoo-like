<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTransition;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\RfqBid;
use App\Models\Supplier;
use App\Models\User;
use App\Services\RfqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Product $p1;

    private Product $p2;

    private Supplier $s1;

    private Supplier $s2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->p1 = Product::create(['sku' => 'A', 'name' => 'A', 'sale_price' => 10, 'cost_price' => 5]);
        $this->p2 = Product::create(['sku' => 'B', 'name' => 'B', 'sale_price' => 20, 'cost_price' => 8]);
        $this->s1 = Supplier::create(['name' => 'Cheap Co']);
        $this->s2 = Supplier::create(['name' => 'Pricey Co']);
    }

    private function rfq(): Rfq
    {
        return RfqService::createRfq('Q3 stock', null, [
            ['product' => $this->p1->id, 'quantity' => 10],
            ['product' => $this->p2->id, 'quantity' => 5],
        ], $this->manager);
    }

    public function test_bid_total_is_quantity_times_price(): void
    {
        $rfq = $this->rfq();
        $lines = $rfq->lines()->get();
        $bid = RfqService::submitBid($rfq, $this->s1->id, [
            $lines[0]->id => 4,  // 10 * 4 = 40
            $lines[1]->id => 6,  // 5 * 6 = 30
        ], '', $this->manager);

        $this->assertEquals('70.00', $bid->total_amount);
    }

    public function test_comparison_flags_the_lowest_bid(): void
    {
        $rfq = $this->rfq();
        $lines = $rfq->lines()->get();
        RfqService::submitBid($rfq, $this->s1->id, [$lines[0]->id => 4, $lines[1]->id => 6], '', $this->manager); // 70
        RfqService::submitBid($rfq, $this->s2->id, [$lines[0]->id => 5, $lines[1]->id => 7], '', $this->manager); // 50+35=85

        $cmp = collect(RfqService::compare($rfq));
        $this->assertTrue($cmp->first()['is_lowest']);
        $this->assertEquals($this->s1->id, $cmp->first()['supplier']);
        $this->assertFalse($cmp->last()['is_lowest']);
    }

    public function test_supplier_cannot_bid_twice(): void
    {
        $rfq = $this->rfq();
        $lines = $rfq->lines()->get();
        RfqService::submitBid($rfq, $this->s1->id, [$lines[0]->id => 4, $lines[1]->id => 6], '', $this->manager);

        $this->expectException(InvalidTransition::class);
        RfqService::submitBid($rfq, $this->s1->id, [$lines[0]->id => 3, $lines[1]->id => 3], '', $this->manager);
    }

    public function test_awarding_creates_draft_po_and_rejects_others(): void
    {
        $rfq = $this->rfq();
        $lines = $rfq->lines()->get();
        $win = RfqService::submitBid($rfq, $this->s1->id, [$lines[0]->id => 4, $lines[1]->id => 6], '', $this->manager); // 70
        $lose = RfqService::submitBid($rfq, $this->s2->id, [$lines[0]->id => 5, $lines[1]->id => 7], '', $this->manager);

        $po = RfqService::award($rfq, $win, $this->manager);

        $this->assertEquals(PurchaseOrder::STATUS_DRAFT, $po->status);
        $this->assertEquals('70.00', $po->total_amount);
        $this->assertEquals($this->s1->id, $po->supplier_id);
        $this->assertEquals(RfqBid::STATUS_AWARDED, $win->refresh()->status);
        $this->assertEquals(RfqBid::STATUS_REJECTED, $lose->refresh()->status);
        $this->assertEquals(Rfq::STATUS_AWARDED, $rfq->refresh()->status);
    }

    public function test_cannot_award_twice(): void
    {
        $rfq = $this->rfq();
        $lines = $rfq->lines()->get();
        $bid = RfqService::submitBid($rfq, $this->s1->id, [$lines[0]->id => 4, $lines[1]->id => 6], '', $this->manager);
        RfqService::award($rfq, $bid, $this->manager);

        $this->expectException(InvalidTransition::class);
        RfqService::award($rfq->refresh(), $bid->refresh(), $this->manager);
    }

    public function test_http_flow_and_rbac(): void
    {
        $rfq = $this->rfq();
        $lines = $rfq->lines()->get();

        $bid = $this->actingAs($this->manager, 'api')->postJson("/api/v1/rfqs/{$rfq->id}/bids", [
            'supplier' => $this->s1->id,
            'prices' => [$lines[0]->id => 4, $lines[1]->id => 6],
        ])->assertCreated()->assertJsonPath('total_amount', '70.00')->json();

        $this->actingAs($this->manager, 'api')->postJson("/api/v1/rfqs/{$rfq->id}/award/{$bid['id']}")
            ->assertOk()->assertJsonPath('total_amount', '70.00');

        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')->getJson('/api/v1/rfqs')->assertForbidden();
    }
}
