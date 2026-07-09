<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Purchase order & sale lifecycles. Stock only moves through StockService,
 * with the same transitions and atomicity as the previous Django backend.
 */
class DocumentService
{
    public static function nextNumber(string $prefix, string $model): string
    {
        $year = now()->year;
        $count = $model::where('number', 'like', "{$prefix}-{$year}-%")->count();

        return sprintf('%s-%d-%04d', $prefix, $year, $count + 1);
    }

    // ---------- purchase orders ----------

    public static function confirmPurchase(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_DRAFT) {
            throw new InvalidTransition("Only draft orders can be confirmed (status: {$po->status}).");
        }
        if (! $po->lines()->exists()) {
            throw new InvalidTransition('Cannot confirm an order without lines.');
        }
        $po->update(['status' => PurchaseOrder::STATUS_CONFIRMED]);

        return $po;
    }

    public static function receivePurchase(PurchaseOrder $po, User $user): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_CONFIRMED) {
            throw new InvalidTransition("Only confirmed orders can be received (status: {$po->status}).");
        }

        return DB::transaction(function () use ($po, $user) {
            foreach ($po->lines as $line) {
                StockService::recordMovement(
                    productId: $line->product_id,
                    movementType: StockMovement::TYPE_IN,
                    quantity: $line->quantity,
                    user: $user,
                    reason: "Goods receipt {$po->number}",
                    referenceType: 'purchase',
                    referenceId: $po->id,
                );
            }
            $po->update([
                'status' => PurchaseOrder::STATUS_RECEIVED,
                'received_date' => now()->toDateString(),
            ]);

            return $po;
        });
    }

    public static function cancelPurchase(PurchaseOrder $po): PurchaseOrder
    {
        if (! in_array($po->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_CONFIRMED], true)) {
            throw new InvalidTransition("Cannot cancel a {$po->status} order.");
        }
        $po->update(['status' => PurchaseOrder::STATUS_CANCELLED]);

        return $po;
    }

    // ---------- sales ----------

    public static function confirmSale(Sale $sale, User $user): Sale
    {
        if ($sale->status !== Sale::STATUS_DRAFT) {
            throw new InvalidTransition("Only draft sales can be confirmed (status: {$sale->status}).");
        }
        if (! $sale->lines()->exists()) {
            throw new InvalidTransition('Cannot confirm a sale without lines.');
        }

        return DB::transaction(function () use ($sale, $user) {
            foreach ($sale->lines as $line) {
                StockService::recordMovement(
                    productId: $line->product_id,
                    movementType: StockMovement::TYPE_OUT,
                    quantity: $line->quantity,
                    user: $user,
                    reason: "Sale {$sale->number}",
                    referenceType: 'sale',
                    referenceId: $sale->id,
                );
            }
            $sale->update(['status' => Sale::STATUS_CONFIRMED]);

            return $sale;
        });
    }

    public static function cancelSale(Sale $sale, User $user): Sale
    {
        if ($sale->status === Sale::STATUS_CANCELLED) {
            throw new InvalidTransition('Sale is already cancelled.');
        }

        return DB::transaction(function () use ($sale, $user) {
            if ($sale->status === Sale::STATUS_CONFIRMED) {
                foreach ($sale->lines as $line) {
                    StockService::recordMovement(
                        productId: $line->product_id,
                        movementType: StockMovement::TYPE_IN,
                        quantity: $line->quantity,
                        user: $user,
                        reason: "Cancellation of {$sale->number}",
                        referenceType: 'sale',
                        referenceId: $sale->id,
                    );
                }
            }
            $sale->update(['status' => Sale::STATUS_CANCELLED]);

            return $sale;
        });
    }

    public static function generateInvoice(Sale $sale): Invoice
    {
        if ($sale->status !== Sale::STATUS_CONFIRMED) {
            throw new InvalidTransition('Invoices can only be generated for confirmed sales.');
        }
        if ($sale->invoice) {
            return $sale->invoice;
        }

        return Invoice::create([
            'number' => self::nextNumber('INV', Invoice::class),
            'sale_id' => $sale->id,
        ])->refresh();
    }
}
