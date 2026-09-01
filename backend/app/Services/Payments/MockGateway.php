<?php

namespace App\Services\Payments;

use App\Models\OnlinePayment;

/**
 * Built-in sandbox gateway. It moves no real money: the "checkout" is a page
 * on our own portal where the customer confirms, which then marks the payment
 * paid. This lets the whole online-payment flow be used and tested without any
 * provider credentials. Swap the configured provider for a real one in prod.
 */
class MockGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'mock';
    }

    public function initiate(OnlinePayment $payment): string
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return "{$frontend}/portal/pay/{$payment->token}";
    }
}
