<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequisition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Purchase requisitions: raise a request to buy, route it through the approval
 * engine, and convert an approved requisition into a purchase order.
 */
class RequisitionService
{
    /**
     * Create a draft requisition with its lines.
     *
     * @param  array<int,array{product_id:int,quantity:float,estimated_price?:float,notes?:string}>  $lines
     */
    public static function create(array $data, array $lines, User $user): PurchaseRequisition
    {
        return DB::transaction(function () use ($data, $lines, $user) {
            $req = PurchaseRequisition::create([
                'number' => DocumentService::nextNumber('REQ', PurchaseRequisition::class),
                'requested_by' => $data['requested_by'] ?? $user->id,
                'supplier_id' => $data['supplier_id'] ?? null,
                'notes' => $data['notes'] ?? '',
                'created_by' => $user->id,
            ]);

            foreach ($lines as $line) {
                $product = Product::findOrFail($line['product_id']);
                $req->lines()->create([
                    'product_id' => $product->id,
                    'quantity' => $line['quantity'],
                    'estimated_price' => $line['estimated_price'] ?? (float) ($product->cost_price ?? 0),
                    'notes' => $line['notes'] ?? '',
                ]);
            }

            $req->load('lines')->recomputeTotal();

            return $req->refresh();
        });
    }

    /** Submit a draft for approval; routes it through the engine by its estimate. */
    public static function submit(PurchaseRequisition $req, User $user): PurchaseRequisition
    {
        if ($req->status !== PurchaseRequisition::STATUS_DRAFT) {
            throw new InvalidTransition('Only a draft requisition can be submitted.');
        }
        if ($req->lines()->count() === 0) {
            throw new InvalidTransition('A requisition needs at least one line.');
        }

        $req->load('lines')->recomputeTotal();
        $request = ApprovalService::start($req, 'purchase_requisition', (float) $req->total_estimate, $user);

        // The engine auto-approves when no step applies; otherwise it is pending.
        $req->update(['status' => $request->status === $request::STATUS_APPROVED
            ? PurchaseRequisition::STATUS_APPROVED
            : PurchaseRequisition::STATUS_PENDING]);

        return $req->refresh();
    }

    /** Turn an approved requisition into a purchase order. */
    public static function convert(PurchaseRequisition $req, User $user): PurchaseOrder
    {
        if ($req->status !== PurchaseRequisition::STATUS_APPROVED) {
            throw new InvalidTransition('Only an approved requisition can become a purchase order.');
        }
        if (! $req->supplier_id) {
            throw new InvalidTransition('Set a supplier before converting to a purchase order.');
        }

        return DB::transaction(function () use ($req, $user) {
            $po = PurchaseOrder::create([
                'number' => DocumentService::nextNumber('PO', PurchaseOrder::class),
                'supplier_id' => $req->supplier_id,
                'order_date' => now()->toDateString(),
                'created_by' => $user->id,
            ]);
            foreach ($req->load('lines')->lines as $line) {
                PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $line->product_id,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->estimated_price,
                ]);
            }
            $po->load('lines')->recomputeTotal();

            $req->update(['status' => PurchaseRequisition::STATUS_CONVERTED, 'purchase_order_id' => $po->id]);

            return $po->refresh();
        });
    }
}
