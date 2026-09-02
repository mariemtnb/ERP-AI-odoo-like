<?php

namespace App\Services\EInvoicing;

use App\Models\EInvoice;

/**
 * An e-invoicing provider. An implementation submits a generated TEIF document
 * to the tax platform (TTN) and reports the outcome. The real TTN adapter
 * implements this — signing the XML and calling the El Fatoora API — without
 * anything else changing.
 */
interface EInvoiceProvider
{
    /** Provider key stored on the record (e.g. "mock", "ttn"). */
    public function key(): string;

    /**
     * Submit the e-invoice's XML to the platform.
     *
     * @return SubmissionResult the reference assigned by TTN and whether it was
     *                          accepted or rejected (with a reason).
     */
    public function submit(EInvoice $eInvoice): SubmissionResult;
}
