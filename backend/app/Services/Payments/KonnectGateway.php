<?php

namespace App\Services\Payments;

use App\Models\OnlinePayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Konnect (konnect.network) — a Tunisian payment gateway.
 *
 * initiate() asks Konnect to create a payment and returns its hosted pay URL;
 * verify() asks Konnect whether that payment actually completed before we post
 * anything. Credentials come from config/services (env), so no key is in code.
 * Amounts are sent in millimes (TND × 1000).
 */
class KonnectGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'konnect';
    }

    private function base(): string
    {
        return rtrim((string) config('services.payments.konnect.base_url', 'https://api.konnect.network/api/v2'), '/');
    }

    public function initiate(OnlinePayment $payment): string
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $return = "{$frontend}/portal/pay/{$payment->token}";

        $res = Http::withHeaders(['x-api-key' => (string) config('services.payments.konnect.api_key')])
            ->acceptJson()
            ->post($this->base().'/payments/init-payment', [
                'receiverWalletId' => (string) config('services.payments.konnect.wallet_id'),
                'amount' => (int) round((float) $payment->amount * 1000), // millimes
                'token' => 'TND',
                'type' => 'immediate',
                'description' => 'Sale '.$payment->sale?->number,
                'acceptedPaymentMethods' => ['wallet', 'bank_card', 'e-DINAR'],
                'lifespan' => 30,
                'successUrl' => $return,
                'failUrl' => $return,
                'theme' => 'light',
            ]);

        $res->throw();
        // Keep the provider reference so we can verify it later.
        $payment->update(['gateway_ref' => $res->json('paymentRef')]);

        return (string) $res->json('payUrl');
    }

    public function verify(OnlinePayment $payment): bool
    {
        if (! $payment->gateway_ref) {
            return false;
        }

        $res = Http::withHeaders(['x-api-key' => (string) config('services.payments.konnect.api_key')])
            ->acceptJson()
            ->get($this->base()."/payments/{$payment->gateway_ref}");

        if ($res->failed()) {
            Log::warning('Konnect verify failed', ['ref' => $payment->gateway_ref, 'status' => $res->status()]);

            return false;
        }

        return $res->json('payment.status') === 'completed';
    }
}
