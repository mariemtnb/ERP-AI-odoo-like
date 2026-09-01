<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\OnlinePayment;
use App\Models\Sale;
use App\Services\Payments\PaymentGateways;
use App\Support\AccountMap;
use Illuminate\Support\Facades\DB;

/**
 * Drives an online payment for a shared sale: create the pending attempt, hand
 * the customer to the gateway, and settle it on confirmation by posting the
 * money to the ledger (Dr bank / Cr receivable).
 */
class OnlinePaymentService
{
    /** Has this sale already been settled online? */
    public static function isPaid(Sale $sale): bool
    {
        return $sale->onlinePayments()->where('status', OnlinePayment::PAID)->exists();
    }

    /** Begin a payment; returns [payment, checkoutUrl]. */
    public static function initiate(Sale $sale): array
    {
        if ((float) $sale->total_amount <= 0) {
            throw new InvalidTransition('There is nothing to pay on this document.');
        }
        if (self::isPaid($sale)) {
            throw new InvalidTransition('This document has already been paid.');
        }

        $gateway = PaymentGateways::current();
        $payment = OnlinePayment::create([
            'sale_id' => $sale->id,
            'token' => bin2hex(random_bytes(24)),
            'amount' => $sale->total_amount,
            'provider' => $gateway->key(),
            'status' => OnlinePayment::PENDING,
        ]);

        return [$payment, $gateway->initiate($payment)];
    }

    /**
     * Confirm a payment (the gateway's success callback, or the sandbox's own
     * confirm). Idempotent: confirming an already-paid attempt does nothing.
     */
    public static function confirm(OnlinePayment $payment, ?string $gatewayRef = null): OnlinePayment
    {
        if ($payment->isPaid()) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $gatewayRef) {
            $sale = $payment->sale;
            // The staff member who owns the sale is the actor on the entry —
            // there is no logged-in user on a customer's payment callback.
            $actor = $sale->creator ?? \App\Models\User::query()->orderBy('id')->first();

            $entry = AccountingService::post(
                lines: [
                    ['account' => AccountMap::code('bank'), 'debit' => $payment->amount, 'label' => "Online payment {$sale->number}"],
                    ['account' => AccountMap::code('receivable'), 'credit' => $payment->amount, 'label' => "Online payment {$sale->number}"],
                ],
                user: $actor,
                memo: "Online payment for {$sale->number}",
                referenceType: 'online_payment',
                referenceId: $payment->id,
            );

            $payment->update([
                'status' => OnlinePayment::PAID,
                'gateway_ref' => $gatewayRef,
                'journal_entry_id' => $entry?->id,
                'paid_at' => now(),
            ]);

            return $payment->refresh();
        });
    }
}
