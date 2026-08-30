<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Rfq;
use App\Models\RfqBid;
use App\Models\RfqBidLine;
use App\Models\RfqLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Requests for quotation and supplier bids. A bid prices every RFQ line; the
 * bid total is the sum of quantity × unit price. Awarding a bid turns it into a
 * draft purchase order and rejects the competing bids.
 */
class RfqService
{
    /**
     * @param  array<int,array{product:int,quantity:int|float}>  $lines
     */
    public static function createRfq(string $title, ?string $dueDate, array $lines, User $user): Rfq
    {
        if ($lines === []) {
            throw new InvalidTransition('An RFQ needs at least one line.');
        }

        return DB::transaction(function () use ($title, $dueDate, $lines, $user) {
            $rfq = Rfq::create([
                'number' => DocumentService::nextNumber('RFQ', Rfq::class),
                'title' => $title,
                'due_date' => $dueDate,
                'created_by' => $user->id,
            ]);
            foreach ($lines as $line) {
                RfqLine::create([
                    'rfq_id' => $rfq->id,
                    'product_id' => $line['product'],
                    'quantity' => $line['quantity'],
                ]);
            }

            return $rfq->load('lines');
        });
    }

    /**
     * @param  array<int,int|float>  $prices  rfq_line_id => unit_price
     */
    public static function submitBid(Rfq $rfq, int $supplierId, array $prices, string $note, User $user): RfqBid
    {
        if ($rfq->status !== Rfq::STATUS_OPEN) {
            throw new InvalidTransition('Bids can only be submitted on an open RFQ.');
        }
        if (RfqBid::where('rfq_id', $rfq->id)->where('supplier_id', $supplierId)->exists()) {
            throw new InvalidTransition('This supplier has already bid on the RFQ.');
        }

        $lines = $rfq->lines()->get()->keyBy('id');
        foreach ($lines as $id => $line) {
            if (! array_key_exists($id, $prices)) {
                throw new InvalidTransition("Missing price for RFQ line {$id}.");
            }
        }

        return DB::transaction(function () use ($rfq, $supplierId, $prices, $note, $user, $lines) {
            $total = 0.0;
            $bid = RfqBid::create([
                'rfq_id' => $rfq->id,
                'supplier_id' => $supplierId,
                'note' => $note,
                'created_by' => $user->id,
            ]);
            foreach ($prices as $rfqLineId => $unitPrice) {
                if (! isset($lines[$rfqLineId])) {
                    continue;
                }
                $total += (float) $lines[$rfqLineId]->quantity * (float) $unitPrice;
                RfqBidLine::create([
                    'bid_id' => $bid->id,
                    'rfq_line_id' => $rfqLineId,
                    'unit_price' => $unitPrice,
                ]);
            }
            $bid->update(['total_amount' => round($total, 2)]);

            return $bid->load('lines');
        });
    }

    /** Bids ranked cheapest first, with the lowest flagged. */
    public static function compare(Rfq $rfq): array
    {
        $bids = $rfq->bids()->with('supplier')->orderBy('total_amount')->get();
        $lowest = $bids->first();

        return $bids->map(fn (RfqBid $b) => $b->toApi() + [
            'is_lowest' => $lowest && $b->id === $lowest->id,
        ])->values()->all();
    }

    /** Award a bid: reject the rest and raise a draft PO for the winner. */
    public static function award(Rfq $rfq, RfqBid $bid, User $user): PurchaseOrder
    {
        if ($rfq->status !== Rfq::STATUS_OPEN) {
            throw new InvalidTransition('Only an open RFQ can be awarded.');
        }
        if ($bid->rfq_id !== $rfq->id) {
            throw new InvalidTransition('That bid does not belong to this RFQ.');
        }

        return DB::transaction(function () use ($rfq, $bid, $user) {
            $rfqLines = $rfq->lines()->get()->keyBy('id');
            $bidLines = $bid->lines()->get();

            $po = PurchaseOrder::create([
                'number' => DocumentService::nextNumber('PO', PurchaseOrder::class),
                'supplier_id' => $bid->supplier_id,
                'order_date' => now()->toDateString(),
                'created_by' => $user->id,
            ]);
            foreach ($bidLines as $bl) {
                $rfqLine = $rfqLines[$bl->rfq_line_id] ?? null;
                if (! $rfqLine) {
                    continue;
                }
                PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $rfqLine->product_id,
                    'quantity' => $rfqLine->quantity,
                    'unit_price' => $bl->unit_price,
                ]);
            }
            $po->load('lines')->recomputeTotal();

            $bid->update(['status' => RfqBid::STATUS_AWARDED]);
            RfqBid::where('rfq_id', $rfq->id)->where('id', '!=', $bid->id)
                ->update(['status' => RfqBid::STATUS_REJECTED]);
            $rfq->update(['status' => Rfq::STATUS_AWARDED]);

            return $po->refresh();
        });
    }
}
