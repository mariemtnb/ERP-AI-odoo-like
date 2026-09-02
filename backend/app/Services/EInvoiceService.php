<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\EInvoice;
use App\Models\Sale;
use App\Services\EInvoicing\EInvoiceProvider;
use App\Services\EInvoicing\EInvoiceProviders;
use App\Services\EInvoicing\TeifDocument;
use Illuminate\Support\Facades\DB;

/**
 * Turns an invoiced sale into a Tunisian electronic invoice and submits it to
 * TTN. Two steps, so a document can be reviewed before it is sent:
 *
 *   generate() — build (or rebuild) the TEIF XML for the sale.
 *   submit()   — send it to the platform and record accept/reject.
 *
 * An accepted e-invoice is final: it can be neither regenerated nor resubmitted.
 */
class EInvoiceService
{
    /**
     * Build the TEIF document for a sale. The sale must be invoiced first — the
     * e-invoice references the legal invoice number. Idempotent while not yet
     * accepted: regenerating refreshes the XML and returns the status to
     * "generated" (e.g. after a rejection the user fixes data and retries).
     */
    public static function generate(Sale $sale): EInvoice
    {
        if (! $sale->invoice) {
            throw new InvalidTransition('Generate the invoice before its e-invoice.');
        }

        $existing = EInvoice::where('sale_id', $sale->id)->first();
        if ($existing?->isFinal()) {
            throw new InvalidTransition('This e-invoice is already accepted and cannot be regenerated.');
        }

        $xml = TeifDocument::forSale($sale);

        return EInvoice::updateOrCreate(
            ['sale_id' => $sale->id],
            [
                'invoice_id' => $sale->invoice->id,
                'xml' => $xml,
                'status' => EInvoice::STATUS_GENERATED,
                'error' => null,
                'ttn_ref' => null,
                'submitted_at' => null,
                'accepted_at' => null,
            ]
        )->refresh();
    }

    /**
     * Submit a generated (or previously rejected) e-invoice to the platform.
     * Idempotent for an already-accepted document: it is returned unchanged.
     */
    public static function submit(EInvoice $eInvoice, ?EInvoiceProvider $provider = null): EInvoice
    {
        if ($eInvoice->isFinal()) {
            return $eInvoice;
        }

        $provider ??= EInvoiceProviders::current();

        return DB::transaction(function () use ($eInvoice, $provider) {
            $eInvoice->forceFill([
                'provider' => $provider->key(),
                'status' => EInvoice::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ])->save();

            $result = $provider->submit($eInvoice);

            if ($result->accepted) {
                $eInvoice->forceFill([
                    'status' => EInvoice::STATUS_ACCEPTED,
                    'ttn_ref' => $result->reference,
                    'accepted_at' => now(),
                    'error' => null,
                ])->save();
            } else {
                $eInvoice->forceFill([
                    'status' => EInvoice::STATUS_REJECTED,
                    'ttn_ref' => $result->reference,
                    'error' => $result->error,
                ])->save();
            }

            return $eInvoice->refresh();
        });
    }
}
