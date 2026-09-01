<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Sale;

/**
 * Public, unauthenticated customer view of a shared document. Reached only via
 * the hard-to-guess portal token that was emailed to the customer; it exposes
 * just that one sale, read-only.
 */
class PortalController extends Controller
{
    public function sale(string $token)
    {
        $sale = Sale::where('portal_token', $token)
            ->with(['customer', 'lines.product', 'invoice'])
            ->firstOrFail();

        $company = Company::current();

        return response()->json([
            'number' => $sale->number,
            'status' => $sale->status,
            'sale_date' => $sale->sale_date?->format('Y-m-d'),
            'total_amount' => (string) $sale->total_amount,
            'is_invoice' => (bool) $sale->invoice,
            'invoice_number' => $sale->invoice?->number,
            'invoice_date' => $sale->invoice?->issued_at?->format('Y-m-d'),
            'customer' => [
                'name' => $sale->customer?->name,
                'email' => $sale->customer?->email,
                'address' => $sale->customer?->address,
            ],
            'company' => ['name' => $company?->name ?? 'Intelligent ERP'],
            'lines' => $sale->lines->map(fn ($l) => $l->toApi())->values()->all(),
        ]);
    }
}
