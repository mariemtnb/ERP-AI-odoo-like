<?php

namespace App\Services\EInvoicing;

/**
 * Resolves the configured e-invoicing provider. Defaults to the sandbox, so the
 * feature works out of the box; set services.einvoice.provider to 'ttn' (and
 * fill in the credentials) to submit to the real platform.
 */
class EInvoiceProviders
{
    public static function current(): EInvoiceProvider
    {
        return match ((string) config('services.einvoice.provider', 'mock')) {
            'ttn' => new TtnProvider(),
            default => new MockEInvoiceProvider(),
        };
    }
}
