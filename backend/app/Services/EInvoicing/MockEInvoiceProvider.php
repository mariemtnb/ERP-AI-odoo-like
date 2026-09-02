<?php

namespace App\Services\EInvoicing;

use App\Models\EInvoice;

/**
 * Built-in sandbox provider. It submits nothing to a real platform: it checks
 * the document has the fiscal identifiers a real submission would require and
 * returns a deterministic reference, so the whole e-invoicing flow can be used
 * and tested without TTN credentials. Swap the configured provider for the TTN
 * adapter in production.
 */
class MockEInvoiceProvider implements EInvoiceProvider
{
    public function key(): string
    {
        return 'mock';
    }

    public function submit(EInvoice $eInvoice): SubmissionResult
    {
        // Mirror the one hard requirement of a real submission: the seller must
        // be identified. Reject rather than silently accept an unidentifiable
        // document, so the failure surfaces in the sandbox too.
        if (! preg_match('/<MessageSenderIdentifier[^>]*>[^<\s][^<]*<\/MessageSenderIdentifier>/', $eInvoice->xml)) {
            return SubmissionResult::rejected('Seller fiscal identifier (matricule fiscal) is missing.');
        }

        return SubmissionResult::accepted('SANDBOX-'.strtoupper(substr(md5($eInvoice->xml), 0, 12)));
    }
}
