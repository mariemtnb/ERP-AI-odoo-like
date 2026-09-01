<?php

namespace App\Services\Payments;

use App\Models\OnlinePayment;

/**
 * A payment provider. Implementations turn a pending OnlinePayment into a
 * hosted checkout the customer is sent to, and later tell us it succeeded
 * (through a return URL or a webhook). A real Tunisian gateway (Konnect,
 * Flouci, Paymee…) implements this without changing anything else.
 */
interface PaymentGateway
{
    /** The provider key stored on the payment (e.g. "mock", "konnect"). */
    public function key(): string;

    /** Start a checkout and return the URL to send the customer to. */
    public function initiate(OnlinePayment $payment): string;
}
