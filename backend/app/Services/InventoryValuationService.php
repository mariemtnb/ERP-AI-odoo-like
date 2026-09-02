<?php

namespace App\Services;

use App\Models\Product;

/**
 * Weighted-average (AVCO) inventory costing.
 *
 * A receipt blends its cost into the running average in proportion to what is
 * already on hand; sales relieve inventory at that average. So the Inventory
 * ledger account tracks quantity-on-hand × average-cost, and cost of goods
 * sold reflects what stock actually cost — not a frozen standard price.
 */
class InventoryValuationService
{
    /** The cost to value a unit of this product at (average, or standard until first receipt). */
    public static function unitCost(Product $product): float
    {
        $avg = (float) $product->avg_cost;

        return $avg > 0 ? $avg : (float) $product->cost_price;
    }

    /**
     * Blend an incoming quantity at `$unitCostIn` into the average.
     * Call BEFORE the stock is incremented, so on-hand is the pre-receipt qty.
     */
    public static function registerReceipt(Product $product, float $qtyIn, float $unitCostIn): void
    {
        if ($qtyIn <= 0) {
            return;
        }

        $onHand = max(0.0, (float) $product->quantity_in_stock);
        $oldAvg = self::unitCost($product);

        $newAvg = ($onHand + $qtyIn) > 0
            ? (($onHand * $oldAvg) + ($qtyIn * $unitCostIn)) / ($onHand + $qtyIn)
            : $unitCostIn;

        $product->update(['avg_cost' => round($newAvg, 4)]);
    }
}
