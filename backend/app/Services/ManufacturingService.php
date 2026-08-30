<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\BillOfMaterials;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

/**
 * Light manufacturing. A bill of materials defines the components for a batch;
 * a work order produces a quantity of the finished product. Completing a work
 * order consumes the scaled components and produces the finished goods, both
 * through StockService — so component shortages roll the whole run back and the
 * stock ledger always balances.
 */
class ManufacturingService
{
    /**
     * Components required to build $quantity finished units, scaled from the
     * BOM's batch size.
     *
     * @return array<int,array{component:int,name:?string,sku:?string,required:float,in_stock:float}>
     */
    public static function requirements(BillOfMaterials $bom, float $quantity): array
    {
        $factor = $quantity / (float) $bom->output_quantity;

        return $bom->components->map(function ($c) use ($factor) {
            return [
                'component' => $c->component_product_id,
                'name' => $c->component?->name,
                'sku' => $c->component?->sku,
                'required' => round((float) $c->quantity * $factor, 3),
                'in_stock' => (float) ($c->component?->quantity_in_stock ?? 0),
            ];
        })->all();
    }

    public static function createWorkOrder(BillOfMaterials $bom, float $quantity, User $user): WorkOrder
    {
        if ($quantity <= 0) {
            throw new InvalidTransition('Work-order quantity must be positive.');
        }
        if (! $bom->is_active) {
            throw new InvalidTransition('This bill of materials is inactive.');
        }
        if (! $bom->components()->exists()) {
            throw new InvalidTransition('The bill of materials has no components.');
        }

        return WorkOrder::create([
            'number' => DocumentService::nextNumber('WO', WorkOrder::class),
            'bom_id' => $bom->id,
            'product_id' => $bom->product_id,
            'quantity' => $quantity,
            'created_by' => $user->id,
        ]);
    }

    public static function start(WorkOrder $wo): WorkOrder
    {
        if ($wo->status !== WorkOrder::STATUS_DRAFT) {
            throw new InvalidTransition("Only draft work orders can be started (status: {$wo->status}).");
        }
        $wo->update(['status' => WorkOrder::STATUS_IN_PROGRESS, 'started_at' => now()]);

        return $wo;
    }

    /**
     * Complete a work order: consume components, produce the finished goods.
     * Atomic — an out-of-stock component throws InsufficientStock and nothing
     * is moved.
     */
    public static function complete(WorkOrder $wo, User $user): WorkOrder
    {
        if (! in_array($wo->status, [WorkOrder::STATUS_DRAFT, WorkOrder::STATUS_IN_PROGRESS], true)) {
            throw new InvalidTransition("Only draft or in-progress work orders can be completed (status: {$wo->status}).");
        }

        $bom = $wo->bom()->with('components')->firstOrFail();
        $requirements = self::requirements($bom, (float) $wo->quantity);

        return DB::transaction(function () use ($wo, $requirements, $user) {
            foreach ($requirements as $req) {
                // throws InsufficientStock, rolling everything back
                StockService::recordMovement(
                    productId: $req['component'],
                    movementType: StockMovement::TYPE_OUT,
                    quantity: $req['required'],
                    user: $user,
                    reason: "Work order {$wo->number} consumption",
                    referenceType: 'work_order',
                    referenceId: $wo->id,
                );
            }

            StockService::recordMovement(
                productId: $wo->product_id,
                movementType: StockMovement::TYPE_IN,
                quantity: (float) $wo->quantity,
                user: $user,
                reason: "Work order {$wo->number} output",
                referenceType: 'work_order',
                referenceId: $wo->id,
            );

            $wo->update(['status' => WorkOrder::STATUS_DONE, 'completed_at' => now()]);

            return $wo;
        });
    }

    public static function cancel(WorkOrder $wo): WorkOrder
    {
        if ($wo->status === WorkOrder::STATUS_DONE) {
            throw new InvalidTransition('A completed work order cannot be cancelled.');
        }
        if ($wo->status === WorkOrder::STATUS_CANCELLED) {
            throw new InvalidTransition('Work order is already cancelled.');
        }
        $wo->update(['status' => WorkOrder::STATUS_CANCELLED]);

        return $wo;
    }
}
