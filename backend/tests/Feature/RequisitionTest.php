<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\FeatureFlag;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Purchase requisitions routed through the multi-level approval engine. */
class RequisitionTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;
    private User $manager;
    private User $admin;
    private Supplier $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureFlag::flush();
        $this->employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->admin = User::create(['email' => 'a@t.t', 'password' => 'x', 'role' => 'admin']);
        $this->supplier = Supplier::create(['name' => 'Acme']);
        $this->product = Product::create(['sku' => 'P1', 'name' => 'Widget', 'sale_price' => 10, 'cost_price' => 100, 'unit' => 'pcs']);
    }

    private function raise(float $price, float $qty = 10): PurchaseRequisition
    {
        $res = $this->actingAs($this->employee, 'api')->postJson('/api/v1/requisitions', [
            'supplier_id' => $this->supplier->id,
            'lines' => [['product_id' => $this->product->id, 'quantity' => $qty, 'estimated_price' => $price]],
        ])->assertStatus(201);

        return PurchaseRequisition::find($res->json('id'));
    }

    private function act(User $user, ApprovalRequest $request, string $decision)
    {
        return $this->actingAs($user, 'api')->postJson("/api/v1/approvals/{$request->id}/act", ['decision' => $decision]);
    }

    public function test_a_small_requisition_needs_only_manager_approval(): void
    {
        $req = $this->raise(100); // total 1000 < 5000

        $res = $this->actingAs($this->employee, 'api')->postJson("/api/v1/requisitions/{$req->id}/submit")
            ->assertOk()->assertJsonPath('status', 'pending');
        $this->assertSame(1, $res->json('approval.current_sequence'));

        $request = $req->approvalRequest;
        $this->act($this->manager, $request, 'approved')->assertOk()->assertJsonPath('status', 'approved');
        $this->assertSame('approved', $req->refresh()->status); // no admin step at this amount
    }

    public function test_a_large_requisition_escalates_to_admin(): void
    {
        $req = $this->raise(1000); // total 10000 ≥ 5000 → manager then admin
        $this->actingAs($this->employee, 'api')->postJson("/api/v1/requisitions/{$req->id}/submit")->assertOk();
        $request = $req->approvalRequest;

        $this->act($this->manager, $request, 'approved')->assertOk()->assertJsonPath('status', 'pending');
        $this->assertSame('pending', $req->refresh()->status);

        // The manager cannot sign the admin step.
        $this->act($this->manager, $request->refresh(), 'approved')->assertStatus(422);

        $this->act($this->admin, $request->refresh(), 'approved')->assertOk()->assertJsonPath('status', 'approved');
        $this->assertSame('approved', $req->refresh()->status);
    }

    public function test_a_rejection_stops_the_chain(): void
    {
        $req = $this->raise(100);
        $this->actingAs($this->employee, 'api')->postJson("/api/v1/requisitions/{$req->id}/submit")->assertOk();

        $this->act($this->manager, $req->approvalRequest, 'rejected')->assertOk()->assertJsonPath('status', 'rejected');
        $this->assertSame('rejected', $req->refresh()->status);
    }

    public function test_an_employee_cannot_approve(): void
    {
        $req = $this->raise(100);
        $this->actingAs($this->employee, 'api')->postJson("/api/v1/requisitions/{$req->id}/submit")->assertOk();

        $this->act($this->employee, $req->approvalRequest, 'approved')->assertStatus(422);
        $this->assertSame('pending', $req->refresh()->status);
    }

    public function test_the_pending_inbox_only_shows_what_you_can_sign(): void
    {
        $req = $this->raise(100);
        $this->actingAs($this->employee, 'api')->postJson("/api/v1/requisitions/{$req->id}/submit")->assertOk();

        $this->actingAs($this->manager, 'api')->getJson('/api/v1/approvals/pending')->assertOk()->assertJsonCount(1);
        $this->actingAs($this->employee, 'api')->getJson('/api/v1/approvals/pending')->assertOk()->assertJsonCount(0);
    }

    public function test_an_approved_requisition_converts_to_a_purchase_order(): void
    {
        $req = $this->raise(100);
        $this->actingAs($this->employee, 'api')->postJson("/api/v1/requisitions/{$req->id}/submit")->assertOk();
        $this->act($this->manager, $req->approvalRequest, 'approved')->assertOk();

        $res = $this->actingAs($this->manager, 'api')->postJson("/api/v1/requisitions/{$req->id}/convert")
            ->assertStatus(201);

        $po = PurchaseOrder::find($res->json('purchase_order_id'));
        $this->assertNotNull($po);
        $this->assertEqualsWithDelta(1000, (float) $po->total_amount, 0.001);
        $this->assertSame(1, $po->lines()->count());
        $this->assertSame('converted', $req->refresh()->status);
        $this->assertSame($po->id, $req->purchase_order_id);
    }

    public function test_converting_before_approval_is_rejected(): void
    {
        $req = $this->raise(100);
        $this->actingAs($this->manager, 'api')->postJson("/api/v1/requisitions/{$req->id}/convert")->assertStatus(422);
    }

    public function test_the_module_is_absent_when_the_flag_is_off(): void
    {
        FeatureFlag::updateOrCreate(['key' => 'requisitions'], ['enabled' => false]);
        FeatureFlag::flush();
        $this->actingAs($this->employee, 'api')->getJson('/api/v1/requisitions')->assertStatus(404);
    }
}
