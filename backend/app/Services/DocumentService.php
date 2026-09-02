<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\Invoice;
use App\Models\NumberingSequence;
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
    /** Prefix → configured sequence key. */
    private const SEQUENCE_KEYS = [
        'SO' => 'sale',
        'PO' => 'purchase',
        'INV' => 'invoice',
        'JE' => 'journal_entry',
        'CHQ' => 'cheque',
        'EFF' => 'traite',
        'PAY' => 'payment',
        'PLAN' => 'installment_plan',
    ];

    /**
     * Next document number.
     *
     * Delegates to the configured numbering sequence, which reserves the
     * number under a row lock. The old count()-based scheme it replaces had
     * two real defects: concurrent requests could read the same count and mint
     * duplicates, and deleting a record made the next one reuse a number that
     * had already been issued. NumberingSequence falls back to the legacy
     * behaviour when no sequence row exists, so nothing breaks if one is
     * missing.
     */
    public static function nextNumber(string $prefix, string $model): string
    {
        $key = self::SEQUENCE_KEYS[$prefix] ?? strtolower($prefix);

        return NumberingSequence::next($key, $prefix, $model);
    }

    // ---------- purchase orders ----------

    /** Orders at or above this amount need an admin's approval. */
    public static function approvalThreshold(): float
    {
        return (float) env('PURCHASE_APPROVAL_THRESHOLD', 1000);
    }

    public static function confirmPurchase(PurchaseOrder $po, User $user): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_DRAFT) {
            throw new InvalidTransition("Only draft orders can be confirmed (status: {$po->status}).");
        }
        if (! $po->lines()->exists()) {
            throw new InvalidTransition('Cannot confirm an order without lines.');
        }

        // Hierarchical validation: large orders confirmed by non-admins wait
        // for an admin. Admins' own confirmations are auto-approved.
        if ((float) $po->total_amount >= self::approvalThreshold() && ! $user->isAdmin()) {
            $po->update(['status' => PurchaseOrder::STATUS_PENDING_APPROVAL]);
        } else {
            $po->update([
                'status' => PurchaseOrder::STATUS_CONFIRMED,
                'approved_by' => $user->isAdmin() ? $user->id : null,
                'approved_at' => $user->isAdmin() ? now() : null,
            ]);
        }

        return $po;
    }

    public static function approvePurchase(PurchaseOrder $po, User $admin): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_PENDING_APPROVAL) {
            throw new InvalidTransition("Only orders pending approval can be approved (status: {$po->status}).");
        }
        $po->update([
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        return $po;
    }

    public static function rejectPurchase(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_PENDING_APPROVAL) {
            throw new InvalidTransition("Only orders pending approval can be rejected (status: {$po->status}).");
        }
        $po->update(['status' => PurchaseOrder::STATUS_DRAFT]);

        return $po;
    }

    /**
     * Receive goods, in full or in part.
     *
     * `$receiveLines` maps a purchase-order-line id to the quantity arriving
     * now; anything omitted receives nothing. Pass null to receive all that is
     * still outstanding (the simple "receive the lot" case). The order becomes
     * "received" once every line is complete, otherwise "partial".
     *
     * @param  array<int,int|float>|null  $receiveLines  [line_id => qty]
     */
    public static function receivePurchase(PurchaseOrder $po, User $user, ?array $receiveLines = null): PurchaseOrder
    {
        if (! in_array($po->status, [PurchaseOrder::STATUS_CONFIRMED, PurchaseOrder::STATUS_PARTIAL], true)) {
            throw new InvalidTransition("Only confirmed or partially received orders can be received (status: {$po->status}).");
        }

        return DB::transaction(function () use ($po, $user, $receiveLines) {
            $po->load('lines');
            $receivedValue = 0.0;

            foreach ($po->lines as $line) {
                $remaining = $line->remaining();
                if ($remaining <= 0) {
                    continue;
                }
                // Take the requested amount (capped at what's outstanding), or
                // the whole remainder when no per-line amounts were given.
                $requested = $receiveLines !== null ? (float) ($receiveLines[$line->id] ?? 0) : $remaining;
                $qty = min(max(0.0, $requested), $remaining);
                if ($qty <= 0) {
                    continue;
                }

                // Blend this receipt's cost into the average BEFORE stock rises.
                if ($product = \App\Models\Product::find($line->product_id)) {
                    \App\Services\InventoryValuationService::registerReceipt($product, $qty, (float) $line->unit_price);
                }
                StockService::recordMovement(
                    productId: $line->product_id,
                    movementType: StockMovement::TYPE_IN,
                    quantity: $qty,
                    user: $user,
                    reason: "Goods receipt {$po->number}",
                    referenceType: 'purchase',
                    referenceId: $po->id,
                );
                $line->update(['received_qty' => round((float) $line->received_qty + $qty, 3)]);
                $receivedValue += round($qty * (float) $line->unit_price, 2);
            }

            if ($receivedValue <= 0) {
                throw new InvalidTransition('Nothing was received — enter a quantity for at least one line.');
            }

            $po->load('lines');
            $fullyReceived = $po->lines->every(fn ($l) => $l->remaining() <= 0.0005);
            $po->update([
                'status' => $fullyReceived ? PurchaseOrder::STATUS_RECEIVED : PurchaseOrder::STATUS_PARTIAL,
                'received_date' => $fullyReceived ? now()->toDateString() : $po->received_date,
            ]);

            // Post only the value that arrived now — Dr Inventory / Cr AP —
            // in the same transaction as the stock movement.
            AccountingService::postPurchaseReceived($po->refresh(), $user, round($receivedValue, 2));

            return $po;
        });
    }

    public static function cancelPurchase(PurchaseOrder $po): PurchaseOrder
    {
        $cancellable = [
            PurchaseOrder::STATUS_DRAFT,
            PurchaseOrder::STATUS_PENDING_APPROVAL,
            PurchaseOrder::STATUS_CONFIRMED,
        ];
        if (! in_array($po->status, $cancellable, true)) {
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

            // Dr Receivable / Cr Revenue, plus Dr COGS / Cr Inventory.
            AccountingService::postSaleConfirmed($sale->load('lines.product'), $user);

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
            // A cancelled sale posts a mirror-image entry; the ledger is
            // append-only, exactly like the stock ledger.
            if ($sale->status === Sale::STATUS_CONFIRMED) {
                AccountingService::reverseSale($sale, $user);
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
