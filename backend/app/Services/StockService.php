<?php

namespace App\Services;

use App\Exceptions\InsufficientStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stock service layer — the ONLY place stock quantities change.
 * Same semantics as the previous Django service: row lock, ledger row and
 * cached quantity updated in one transaction, oversell rejected atomically.
 */
class StockService
{
    public static function recordMovement(
        int $productId,
        string $movementType,
        string|float $quantity,
        User $user,
        string $reason = '',
        string $referenceType = 'manual',
        ?int $referenceId = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $productId, $movementType, $quantity, $user, $reason, $referenceType, $referenceId
        ) {
            $product = Product::lockForUpdate()->findOrFail($productId);
            $qty = (float) $quantity;

            $delta = match ($movementType) {
                StockMovement::TYPE_IN => $qty,
                StockMovement::TYPE_OUT => -$qty,
                StockMovement::TYPE_ADJUSTMENT => $qty,
                default => throw new InvalidArgumentException("Unknown movement type: {$movementType}"),
            };

            if ($movementType !== StockMovement::TYPE_ADJUSTMENT && $qty <= 0) {
                throw new InvalidArgumentException('Quantity must be positive for in/out movements.');
            }

            $newQuantity = (float) $product->quantity_in_stock + $delta;
            if ($newQuantity < 0) {
                throw new InsufficientStock($product, abs($delta));
            }

            $movement = StockMovement::create([
                'product_id' => $product->id,
                'movement_type' => $movementType,
                'quantity' => $qty,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $user->id,
            ]);

            $product->quantity_in_stock = $newQuantity;
            $product->save();

            return $movement;
        });
    }
}
