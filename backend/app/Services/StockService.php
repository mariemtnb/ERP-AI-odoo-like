<?php

namespace App\Services;

use App\Exceptions\InsufficientStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stock service layer — the ONLY place stock quantities change.
 * Warehouse-aware: every movement belongs to a warehouse; the per-warehouse
 * row and the product's global cache are updated in the same transaction,
 * both under row locks. Oversell is checked per warehouse.
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
        ?int $warehouseId = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $productId, $movementType, $quantity, $user, $reason,
            $referenceType, $referenceId, $warehouseId
        ) {
            $warehouseId ??= Warehouse::defaultWarehouse()->id;
            $product = Product::lockForUpdate()->findOrFail($productId);
            $stock = WarehouseStock::lockForUpdate()->firstOrCreate(
                ['warehouse_id' => $warehouseId, 'product_id' => $productId],
                ['quantity' => 0],
            );
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

            $newWarehouseQty = (float) $stock->quantity + $delta;
            if ($newWarehouseQty < 0) {
                throw new InsufficientStock($product, abs($delta));
            }

            $movement = StockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'movement_type' => $movementType,
                'quantity' => $qty,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $user->id,
            ]);

            $stock->quantity = $newWarehouseQty;
            $stock->save();

            $product->quantity_in_stock = (float) $product->quantity_in_stock + $delta;
            $product->save();

            return $movement;
        });
    }

    /** Move stock between warehouses: OUT at source + IN at destination. */
    public static function transfer(
        int $productId,
        int $fromWarehouseId,
        int $toWarehouseId,
        string|float $quantity,
        User $user,
        string $reason = '',
    ): array {
        if ($fromWarehouseId === $toWarehouseId) {
            throw new InvalidArgumentException('Source and destination warehouses must differ.');
        }

        return DB::transaction(function () use ($productId, $fromWarehouseId, $toWarehouseId, $quantity, $user, $reason) {
            $reason = $reason !== '' ? $reason : 'Inter-warehouse transfer';
            $out = self::recordMovement(
                productId: $productId, movementType: StockMovement::TYPE_OUT,
                quantity: $quantity, user: $user, reason: $reason,
                referenceType: 'transfer', warehouseId: $fromWarehouseId,
            );
            $in = self::recordMovement(
                productId: $productId, movementType: StockMovement::TYPE_IN,
                quantity: $quantity, user: $user, reason: $reason,
                referenceType: 'transfer', referenceId: $out->id,
                warehouseId: $toWarehouseId,
            );

            return [$out, $in];
        });
    }
}
