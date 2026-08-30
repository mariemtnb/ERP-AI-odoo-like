<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\Account;
use App\Models\CreditNote;
use App\Models\CreditNoteLine;
use App\Models\Journal;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sales returns. A credit note credits some or all of a confirmed sale.
 * It never lets you return more than was sold (across every prior credit note),
 * optionally restocks the goods, and posts the reversing accounting entry.
 */
class CreditNoteService
{
    /**
     * Quantity still returnable per sale line: sold minus already credited.
     *
     * @return array<int,float> product_id => remaining quantity
     */
    public static function returnable(Sale $sale): array
    {
        $sold = [];
        foreach ($sale->lines as $line) {
            $sold[$line->product_id] = ($sold[$line->product_id] ?? 0) + (float) $line->quantity;
        }

        $credited = CreditNoteLine::whereIn(
            'credit_note_id', CreditNote::where('sale_id', $sale->id)->select('id')
        )->get();
        foreach ($credited as $cl) {
            $sold[$cl->product_id] = ($sold[$cl->product_id] ?? 0) - (float) $cl->quantity;
        }

        return array_map(fn ($q) => round(max(0, $q), 3), $sold);
    }

    /**
     * @param  array<int,array{product:int,quantity:int|float,unit_price:int|float}>  $lines
     */
    public static function createFromSale(
        Sale $sale,
        array $lines,
        bool $restock,
        string $reason,
        User $user,
    ): CreditNote {
        if ($sale->status !== Sale::STATUS_CONFIRMED) {
            throw new InvalidTransition("Only confirmed sales can be credited (status: {$sale->status}).");
        }
        if ($lines === []) {
            throw new InvalidTransition('A credit note needs at least one line.');
        }

        $remaining = self::returnable($sale);
        foreach ($lines as $line) {
            $pid = (int) $line['product'];
            $qty = (float) $line['quantity'];
            if ($qty <= 0) {
                throw new InvalidTransition('Return quantity must be positive.');
            }
            if ($qty > ($remaining[$pid] ?? 0) + 0.0001) {
                throw new InvalidTransition(
                    "Cannot return {$qty} of product {$pid}: only ".($remaining[$pid] ?? 0)." left on the sale."
                );
            }
        }

        return DB::transaction(function () use ($sale, $lines, $restock, $reason, $user) {
            $total = 0.0;
            $cost = 0.0;
            $note = CreditNote::create([
                'number' => DocumentService::nextNumber('CN', CreditNote::class),
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'reason' => $reason,
                'restocked' => $restock,
                'created_by' => $user->id,
            ]);

            foreach ($lines as $line) {
                $lineTotal = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
                $total += $lineTotal;
                CreditNoteLine::create([
                    'credit_note_id' => $note->id,
                    'product_id' => $line['product'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $lineTotal,
                ]);

                if ($restock) {
                    StockService::recordMovement(
                        productId: $line['product'],
                        movementType: StockMovement::TYPE_IN,
                        quantity: $line['quantity'],
                        user: $user,
                        reason: "Return {$note->number}",
                        referenceType: 'credit_note',
                        referenceId: $note->id,
                    );
                }
            }

            $note->update(['total_amount' => round($total, 2)]);
            self::postAccounting($note->fresh('lines'), $restock, $user);

            return $note->load(['lines.product', 'sale', 'customer', 'creator']);
        });
    }

    /**
     * Mirror of postSaleConfirmed, for the returned amount:
     *   Dr Sales revenue / Cr Accounts receivable   (returned selling value)
     *   Dr Inventory     / Cr Cost of goods sold     (returned cost, if restocked)
     */
    private static function postAccounting(CreditNote $note, bool $restock, User $user): void
    {
        $revenue = round((float) $note->total_amount, 2);
        if ($revenue <= 0) {
            return;
        }

        $lines = [
            ['account' => Account::REVENUE, 'debit' => $revenue, 'label' => "Return {$note->number}"],
            ['account' => Account::RECEIVABLE, 'credit' => $revenue, 'label' => "Return {$note->number}"],
        ];

        if ($restock) {
            $cost = 0.0;
            foreach ($note->lines as $line) {
                $cost += (float) $line->quantity * (float) ($line->product->cost_price ?? 0);
            }
            $cost = round($cost, 2);
            if ($cost > 0) {
                $lines[] = ['account' => Account::INVENTORY, 'debit' => $cost, 'label' => "Return {$note->number}"];
                $lines[] = ['account' => Account::COGS, 'credit' => $cost, 'label' => "Return {$note->number}"];
            }
        }

        AccountingService::post(
            lines: $lines,
            user: $user,
            memo: "Credit note {$note->number} for {$note->sale?->number}",
            referenceType: 'credit_note',
            referenceId: $note->id,
            journalCode: Journal::SALES,
        );
    }
}
