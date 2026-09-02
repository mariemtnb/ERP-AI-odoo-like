<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\EInvoice;
use App\Models\Sale;
use App\Services\EInvoiceService;

/**
 * Tunisian electronic invoicing (TTN). Generate a TEIF document from an
 * invoiced sale, review it, and submit it to the platform.
 */
class EInvoiceController extends Controller
{
    /** Build (or rebuild) the e-invoice for a sale. */
    public function generate(Sale $sale)
    {
        try {
            $eInvoice = EInvoiceService::generate($sale);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($eInvoice->toApi(), 201);
    }

    /** Submit a generated e-invoice to TTN. */
    public function submit(EInvoice $eInvoice)
    {
        $eInvoice = EInvoiceService::submit($eInvoice);

        $status = $eInvoice->status === EInvoice::STATUS_REJECTED ? 422 : 200;

        return response()->json($eInvoice->toApi(), $status);
    }

    /** The e-invoice for a sale, if one exists. */
    public function showForSale(Sale $sale)
    {
        $eInvoice = EInvoice::where('sale_id', $sale->id)->firstOrFail();

        return response()->json($eInvoice->toApi(withXml: true));
    }

    /** One e-invoice, including its TEIF XML. */
    public function show(EInvoice $eInvoice)
    {
        return response()->json($eInvoice->toApi(withXml: true));
    }
}
