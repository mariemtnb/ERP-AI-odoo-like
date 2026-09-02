<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorBill;
use App\Models\VendorBillLine;
use Illuminate\Support\Facades\DB;

/**
 * Vendor bills and their 3-way match.
 *
 * The match compares the supplier's invoice against two other documents:
 *   1. the purchase order  — what we agreed to buy, and at what price
 *   2. the goods receipt   — what actually arrived (here, the ordered
 *                            quantities once the PO is marked received)
 *
 * A line is clean only when it is on the PO, priced as ordered, and not billed
 * for more than was received. Any drift flags the whole bill as an exception.
 */
class VendorBillService
{
    private const TOL = 0.005;

    public static function record(
        Supplier $supplier,
        ?PurchaseOrder $po,
        array $lines,
        string $billDate,
        User $user,
        string $supplierRef = '',
    ): VendorBill {
        if ($lines === []) {
            throw new InvalidTransition('A bill needs at least one line.');
        }

        return DB::transaction(function () use ($supplier, $po, $lines, $billDate, $user, $supplierRef) {
            $bill = VendorBill::create([
                'number' => DocumentService::nextNumber('VB', VendorBill::class),
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $po?->id,
                'bill_date' => $billDate,
                'supplier_ref' => $supplierRef,
                'created_by' => $user->id,
            ]);

            foreach ($lines as $line) {
                VendorBillLine::create([
                    'vendor_bill_id' => $bill->id,
                    'product_id' => $line['product'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                ]);
            }

            $bill->load('lines')->recomputeTotal();
            $bill->update(['status' => self::evaluate($bill)]);

            return $bill->refresh();
        });
    }

    /** matched when every line is clean and there is a PO to match against. */
    public static function evaluate(VendorBill $bill): string
    {
        foreach (self::matchReport($bill) as $line) {
            if ($line['flags'] !== []) {
                return VendorBill::STATUS_EXCEPTION;
            }
        }

        return $bill->purchase_order_id
            ? VendorBill::STATUS_MATCHED
            : VendorBill::STATUS_EXCEPTION; // no PO → cannot be 3-way matched
    }

    /**
     * Per-line comparison of billed vs ordered vs received, with the reasons a
     * line is flagged. Drives both the status and the UI.
     *
     * @return array<int,array{product:int,product_name:?string,billed_qty:float,billed_price:float,ordered_qty:float,ordered_price:?float,received_qty:float,flags:array<int,string>}>
     */
    public static function matchReport(VendorBill $bill): array
    {
        $bill->loadMissing('lines.product', 'purchaseOrder.lines');
        $po = $bill->purchaseOrder;

        $report = [];
        foreach ($bill->lines as $line) {
            $poLine = $po?->lines->firstWhere('product_id', $line->product_id);
            $orderedQty = $poLine ? (float) $poLine->quantity : 0.0;
            $orderedPrice = $poLine ? (float) $poLine->unit_price : null;
            // Match against what actually arrived — supports partial receipts.
            $receivedQty = $poLine ? (float) $poLine->received_qty : 0.0;

            $flags = [];
            if (! $poLine) {
                $flags[] = 'not_on_po';
            } else {
                if ((float) $line->quantity > $receivedQty + self::TOL) {
                    $flags[] = 'over_billed';
                }
                if (abs((float) $line->unit_price - $orderedPrice) > self::TOL) {
                    $flags[] = 'price_mismatch';
                }
            }

            $report[] = [
                'product' => $line->product_id,
                'product_name' => $line->product?->name,
                'billed_qty' => (float) $line->quantity,
                'billed_price' => (float) $line->unit_price,
                'ordered_qty' => $orderedQty,
                'ordered_price' => $orderedPrice,
                'received_qty' => $receivedQty,
                'flags' => $flags,
            ];
        }

        return $report;
    }

    /** A manager overrides an exception, clearing the bill for payment. */
    public static function approve(VendorBill $bill, User $user): VendorBill
    {
        if ($bill->status !== VendorBill::STATUS_EXCEPTION) {
            throw new InvalidTransition('Only a bill with exceptions needs approval.');
        }

        $bill->update([
            'status' => VendorBill::STATUS_APPROVED,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return $bill->refresh();
    }
}
