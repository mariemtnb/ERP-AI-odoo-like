<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\Company;
use App\Models\OnlinePayment;
use App\Models\Sale;
use App\Services\OnlinePaymentService;

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
            'paid_online' => $sale->isPaidOnline(),
            'lines' => $sale->lines->map(fn ($l) => $l->toApi())->values()->all(),
        ]);
    }

    /** Start an online payment for a shared sale; returns the checkout URL. */
    public function pay(string $token)
    {
        $sale = Sale::where('portal_token', $token)->firstOrFail();
        try {
            [, $checkoutUrl] = OnlinePaymentService::initiate($sale);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json(['checkout_url' => $checkoutUrl]);
    }

    /** Sandbox pay page data: what the customer is about to pay. */
    public function payIntent(string $payToken)
    {
        $payment = OnlinePayment::where('token', $payToken)->with('sale')->firstOrFail();

        return response()->json([
            'amount' => (string) $payment->amount,
            'status' => $payment->status,
            'sale_number' => $payment->sale?->number,
            'sale_token' => $payment->sale?->portal_token,  // to return to the document
        ]);
    }

    /** Confirm a payment (the sandbox's success callback). */
    public function confirmPay(string $payToken)
    {
        $payment = OnlinePayment::where('token', $payToken)->firstOrFail();
        $payment = OnlinePaymentService::confirm($payment, gatewayRef: 'sandbox');

        return response()->json(['status' => $payment->status, 'sale_token' => $payment->sale?->portal_token]);
    }
}
