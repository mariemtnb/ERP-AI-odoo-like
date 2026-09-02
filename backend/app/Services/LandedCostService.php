<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\Account;
use App\Models\LandedCost;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\AccountMap;
use Illuminate\Support\Facades\DB;

/**
 * Capitalise a landed cost (freight, duty, insurance) onto a received purchase
 * order. The cost is spread across the received lines — by their received value
 * or their received quantity — and added to each product's inventory value:
 * the AVCO unit cost rises and the ledger posts Dr Inventory / Cr the landed-cost
 * payable.
 */
class LandedCostService
{
    /**
     * @param  array{description:string, amount:float, allocation?:string}  $data
     */
    public static function apply(PurchaseOrder $po, array $data, User $user): LandedCost
    {
        if (! in_array($po->status, [PurchaseOrder::STATUS_PARTIAL, PurchaseOrder::STATUS_RECEIVED], true)) {
            throw new InvalidTransition('Landed costs apply to a received (or partially received) order.');
        }

        $amount = round((float) $data['amount'], 3);
        if ($amount <= 0) {
            throw new InvalidTransition('The landed-cost amount must be greater than zero.');
        }
        $allocation = $data['allocation'] ?? LandedCost::BY_VALUE;
        if (! in_array($allocation, LandedCost::ALLOCATIONS, true)) {
            throw new InvalidTransition('Allocation must be by value or by quantity.');
        }

        // Weight each received line by value or quantity, aggregated per product.
        $po->load('lines');
        $basis = [];   // product_id => basis
        foreach ($po->lines as $line) {
            $received = (float) $line->received_qty;
            if ($received <= 0) {
                continue;
            }
            $weight = $allocation === LandedCost::BY_VALUE ? $received * (float) $line->unit_price : $received;
            $basis[$line->product_id] = ($basis[$line->product_id] ?? 0) + $weight;
        }

        $totalBasis = array_sum($basis);
        if ($totalBasis <= 0) {
            throw new InvalidTransition('Nothing has been received on this order to carry a landed cost.');
        }

        return DB::transaction(function () use ($po, $user, $data, $amount, $allocation, $basis, $totalBasis) {
            $landed = LandedCost::create([
                'purchase_order_id' => $po->id,
                'description' => $data['description'] ?? '',
                'amount' => $amount,
                'allocation' => $allocation,
                'created_by' => $user->id,
            ]);

            // Allocate proportionally; the last product absorbs the rounding
            // remainder so the allocations sum to exactly the landed amount.
            $allocated = 0.0;
            $ids = array_keys($basis);
            foreach ($ids as $i => $productId) {
                $isLast = $i === count($ids) - 1;
                $share = $isLast
                    ? round($amount - $allocated, 3)
                    : round($amount * $basis[$productId] / $totalBasis, 3);
                $allocated = round($allocated + $share, 3);

                $landed->allocations()->create([
                    'product_id' => $productId,
                    'basis' => round($basis[$productId], 3),
                    'amount' => $share,
                ]);
                if ($product = Product::find($productId)) {
                    InventoryValuationService::registerAddedCost($product, $share);
                }
            }

            $entry = AccountingService::post(
                lines: [
                    ['account' => Account::INVENTORY, 'debit' => $amount, 'label' => "Landed cost {$po->number}"],
                    ['account' => AccountMap::code('landed_costs'), 'credit' => $amount, 'label' => "Landed cost {$po->number}"],
                ],
                user: $user,
                memo: 'Landed cost on '.$po->number,
                referenceType: 'landed_cost',
                referenceId: $landed->id,
            );
            $landed->update(['journal_entry_id' => $entry->id]);

            return $landed->load('allocations.product')->refresh();
        });
    }
}
