<?php

namespace App\Services;

use App\Exceptions\InsufficientStock;
use App\Exceptions\InvalidTransition;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Lot / batch tracking with FEFO (first-expired-first-out) consumption.
 *
 * Lots sit on top of the aggregate stock ledger: receiving a lot records a
 * normal stock movement IN, and consuming lots records a movement OUT, so the
 * on-hand quantity the rest of the system reads never drifts from the sum of
 * the lots.
 */
class LotService
{
    public static function receive(
        int $productId,
        ?int $warehouseId,
        string $lotNumber,
        ?string $expiryDate,
        float $quantity,
        User $user,
    ): StockLot {
        if ($quantity <= 0) {
            throw new InvalidTransition('Lot quantity must be positive.');
        }
        $warehouseId ??= Warehouse::defaultWarehouse()->id;

        return DB::transaction(function () use ($productId, $warehouseId, $lotNumber, $expiryDate, $quantity, $user) {
            StockService::recordMovement(
                productId: $productId,
                movementType: StockMovement::TYPE_IN,
                quantity: $quantity,
                user: $user,
                reason: "Lot receipt {$lotNumber}",
                referenceType: 'lot',
                warehouseId: $warehouseId,
            );

            $lot = StockLot::lockForUpdate()->firstOrNew([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'lot_number' => $lotNumber,
            ]);
            $lot->quantity = (float) $lot->quantity + $quantity;
            $lot->expiry_date ??= $expiryDate;
            $lot->created_by ??= $user->id;
            $lot->received_at ??= now();
            $lot->save();

            return $lot->fresh(['product', 'warehouse']);
        });
    }

    /**
     * Consume a quantity of a product FEFO — earliest expiry first, undated
     * lots last. Records a single stock movement OUT for the total.
     *
     * @return array<int,array{lot_number:string,taken:float}>
     */
    public static function consumeFefo(
        int $productId,
        ?int $warehouseId,
        float $quantity,
        User $user,
        string $reason = '',
    ): array {
        if ($quantity <= 0) {
            throw new InvalidTransition('Quantity to consume must be positive.');
        }
        $warehouseId ??= Warehouse::defaultWarehouse()->id;

        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $user, $reason) {
            $lots = StockLot::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('quantity', '>', 0)
                ->orderByRaw('expiry_date asc nulls last')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $available = (float) $lots->sum('quantity');
            if ($available + 0.0001 < $quantity) {
                throw new InsufficientStock(Product::findOrFail($productId), $quantity);
            }

            $remaining = $quantity;
            $taken = [];
            foreach ($lots as $lot) {
                if ($remaining <= 0) {
                    break;
                }
                $take = min((float) $lot->quantity, $remaining);
                $lot->quantity = (float) $lot->quantity - $take;
                $lot->save();
                $remaining -= $take;
                $taken[] = ['lot_number' => $lot->lot_number, 'taken' => round($take, 3)];
            }

            StockService::recordMovement(
                productId: $productId,
                movementType: StockMovement::TYPE_OUT,
                quantity: $quantity,
                user: $user,
                reason: $reason !== '' ? $reason : 'FEFO consumption',
                referenceType: 'lot',
                warehouseId: $warehouseId,
            );

            return $taken;
        });
    }

    /** Lots with stock whose expiry falls within the next $days days. */
    public static function expiring(int $days = 7)
    {
        return StockLot::with(['product', 'warehouse'])
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', now()->toDateString())
            ->whereDate('expiry_date', '<=', now()->addDays($days)->toDateString())
            ->orderBy('expiry_date')
            ->get();
    }

    /** Lots still holding stock past their expiry date. */
    public static function expired()
    {
        return StockLot::with(['product', 'warehouse'])
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now()->toDateString())
            ->orderBy('expiry_date')
            ->get();
    }
}
