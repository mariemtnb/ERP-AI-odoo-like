<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\PosOrder;
use App\Models\PosOrderLine;
use App\Models\PosPayment;
use App\Models\PosSession;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Point of Sale lifecycle. Stock only ever moves through StockService, exactly
 * like sales and purchases, so a POS sale and a back-office sale hit the same
 * ledger with the same locking and atomicity.
 */
class PosService
{
    private const METHODS = [
        PosPayment::METHOD_CASH, PosPayment::METHOD_CARD, PosPayment::METHOD_CHEQUE,
    ];

    /** The cashier's currently open session, if any. */
    public static function openSessionFor(User $cashier): ?PosSession
    {
        return PosSession::where('user_id', $cashier->id)
            ->where('status', PosSession::STATUS_OPEN)
            ->latest('id')
            ->first();
    }

    /** Open a till. A cashier may only hold one open session at a time. */
    public static function openSession(User $cashier, float $openingFloat): PosSession
    {
        if (self::openSessionFor($cashier)) {
            throw new InvalidTransition('You already have an open till. Close it before opening another.');
        }
        if ($openingFloat < 0) {
            throw new InvalidTransition('Opening float cannot be negative.');
        }

        return PosSession::create([
            'user_id' => $cashier->id,
            'opening_float' => round($openingFloat, 2),
            'opened_at' => now(),
        ]);
    }

    /**
     * Ring up an order: create it, decrement stock for each line, and record
     * the tender. Everything is atomic — an insufficient-stock line rolls the
     * whole sale back, so the till never records a half-sold basket.
     *
     * @param  array<int,array{product:int,quantity:int|float,unit_price:int|float}>  $lines
     * @param  array<int,array{method:string,amount:int|float}>  $payments
     */
    public static function checkout(
        PosSession $session,
        array $lines,
        array $payments,
        ?int $customerId,
        User $user,
    ): PosOrder {
        if (! $session->isOpen()) {
            throw new InvalidTransition('This till is closed. Open a new session to keep selling.');
        }
        if ($lines === []) {
            throw new InvalidTransition('Cannot check out an empty basket.');
        }

        // Normalise every line: resolve the price from the pricelist when the
        // till didn't send one, apply any per-line discount, and cache the
        // line total so the sum and the stored rows agree.
        $customer = $customerId ? \App\Models\Customer::find($customerId) : null;
        $total = 0.0;
        foreach ($lines as $i => $line) {
            $product = \App\Models\Product::find($line['product']);
            $price = $line['unit_price'] ?? \App\Services\PricingService::priceFor(
                $product, (float) $line['quantity'], $customer
            );
            $discount = (float) ($line['discount_pct'] ?? 0);
            $lineTotal = round((float) $line['quantity'] * (float) $price * (1 - $discount / 100), 2);

            $lines[$i]['unit_price'] = $price;
            $lines[$i]['discount_pct'] = $discount;
            $lines[$i]['_line_total'] = $lineTotal;
            $total += $lineTotal;
        }
        $total = round($total, 2);

        $paid = 0.0;
        foreach ($payments as $p) {
            if (! in_array($p['method'], self::METHODS, true)) {
                throw new InvalidTransition("Unknown payment method: {$p['method']}.");
            }
            $paid += (float) $p['amount'];
        }
        $paid = round($paid, 2);

        if ($paid + 0.001 < $total) {
            throw new InvalidTransition(
                "Payment {$paid} does not cover the total {$total}."
            );
        }

        return DB::transaction(function () use ($session, $lines, $payments, $customerId, $user, $total, $paid) {
            $order = PosOrder::create([
                'number' => DocumentService::nextNumber('POS', PosOrder::class),
                'session_id' => $session->id,
                'customer_id' => $customerId,
                'total_amount' => $total,
                'paid_amount' => $paid,
                'change_due' => round($paid - $total, 2),
                'created_by' => $user->id,
            ]);

            foreach ($lines as $line) {
                PosOrderLine::create([
                    'pos_order_id' => $order->id,
                    'product_id' => $line['product'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_pct' => $line['discount_pct'] ?? 0,
                    'line_total' => $line['_line_total'],
                ]);
                // Throws InsufficientStock, rolling back the whole basket.
                StockService::recordMovement(
                    productId: $line['product'],
                    movementType: StockMovement::TYPE_OUT,
                    quantity: $line['quantity'],
                    user: $user,
                    reason: "POS sale {$order->number}",
                    referenceType: 'pos',
                    referenceId: $order->id,
                );
            }

            foreach ($payments as $p) {
                PosPayment::create([
                    'pos_order_id' => $order->id,
                    'method' => $p['method'],
                    'amount' => round((float) $p['amount'], 2),
                ]);
            }

            // Recognise the sale in the ledger: cash in, revenue, and cost of
            // goods sold relieving inventory at the moving-average cost — the
            // same books discipline the confirmed-sale flow uses.
            AccountingService::postPosSale($order->load('lines.product'), $user);

            return $order->load(['lines.product', 'payments', 'customer', 'creator']);
        });
    }

    /**
     * Close the till. expected_cash = opening float + all cash tendered this
     * session (card/cheque never enter the drawer). The variance against the
     * counted cash is what a manager reviews.
     */
    public static function closeSession(PosSession $session, float $countedCash): PosSession
    {
        if (! $session->isOpen()) {
            throw new InvalidTransition('This session is already closed.');
        }

        $cashTaken = (float) PosPayment::whereIn(
            'pos_order_id', $session->orders()->select('id')
        )->where('method', PosPayment::METHOD_CASH)->sum('amount');

        $session->update([
            'status' => PosSession::STATUS_CLOSED,
            'expected_cash' => round((float) $session->opening_float + $cashTaken, 2),
            'closing_counted' => round($countedCash, 2),
            'closed_at' => now(),
        ]);

        return $session;
    }
}
