<?php

namespace App\Services\Payments;

/**
 * Resolves the configured payment gateway. Defaults to the sandbox, so the
 * feature works out of the box; set services.payments.provider (and add the
 * matching implementation) to go live with a real provider.
 */
class PaymentGateways
{
    public static function current(): PaymentGateway
    {
        return match ((string) config('services.payments.provider', 'mock')) {
            'konnect' => new KonnectGateway(),
            'flouci' => new FlouciGateway(),
            default => new MockGateway(),
        };
    }
}
