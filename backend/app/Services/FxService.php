<?php

namespace App\Services;

use App\Models\Payment;

/**
 * Realized foreign-exchange gain/loss on settling a foreign-currency
 * receivable or payable.
 *
 * A foreign invoice is booked to receivable/payable at the rate on its own
 * date (the "book rate"). When it is settled later, the treasury moves the
 * base value at the settlement-date rate. The two base values differ, and the
 * difference is a realized FX gain or loss:
 *
 *   base booked   = foreign amount × book rate      (relieves receivable/payable)
 *   base settled  = foreign amount × settlement rate (hits the bank/cash)
 *
 * Gain is signed positive. For an inbound receipt a stronger currency at
 * settlement means we banked more base than the receivable carried — a gain.
 * For an outbound payment the sense flips: paying fewer base for the same debt
 * is the gain.
 */
class FxService
{
    /**
     * @return array{base_booked: float, base_settled: float, gain: float}
     *         gain > 0 is a gain, gain < 0 a loss, both in base currency.
     */
    public static function realized(
        string $direction,
        float $foreignAmount,
        float $bookRate,
        float $settlementRate,
        int $decimals = 3,
    ): array {
        $baseBooked = round($foreignAmount * $bookRate, $decimals);
        $baseSettled = round($foreignAmount * $settlementRate, $decimals);

        $gain = $direction === Payment::DIRECTION_IN
            ? $baseSettled - $baseBooked   // received more base than booked ⇒ gain
            : $baseBooked - $baseSettled;  // paid less base than booked ⇒ gain

        return [
            'base_booked' => $baseBooked,
            'base_settled' => $baseSettled,
            'gain' => round($gain, $decimals),
        ];
    }
}
