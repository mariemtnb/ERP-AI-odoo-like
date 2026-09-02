<?php

namespace App\Services\Payments;

use App\Models\OnlinePayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Flouci (flouci.com) — a Tunisian payment gateway.
 *
 * initiate() generates a payment and returns Flouci's hosted link; verify()
 * checks the payment's status with Flouci before we settle. App token and
 * secret come from config/services (env). Amounts are in millimes.
 */
class FlouciGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'flouci';
    }

    private function base(): string
    {
        return rtrim((string) config('services.payments.flouci.base_url', 'https://developers.flouci.com/api'), '/');
    }

    public function initiate(OnlinePayment $payment): string
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $return = "{$frontend}/portal/pay/{$payment->token}";

        $res = Http::acceptJson()->post($this->base().'/generate_payment', [
            'app_token' => (string) config('services.payments.flouci.app_token'),
            'app_secret' => (string) config('services.payments.flouci.app_secret'),
            'amount' => (int) round((float) $payment->amount * 1000), // millimes
            'accept_card' => true,
            'session_timeout_secs' => 1200,
            'success_link' => $return,
            'fail_link' => $return,
            'developer_tracking_id' => (string) $payment->token,
        ]);

        $res->throw();
        $payment->update(['gateway_ref' => $res->json('result.payment_id')]);

        return (string) $res->json('result.link');
    }

    public function verify(OnlinePayment $payment): bool
    {
        if (! $payment->gateway_ref) {
            return false;
        }

        $res = Http::withHeaders([
            'apppublic' => (string) config('services.payments.flouci.app_token'),
            'appsecret' => (string) config('services.payments.flouci.app_secret'),
        ])->acceptJson()->get($this->base()."/verify_payment/{$payment->gateway_ref}");

        if ($res->failed()) {
            Log::warning('Flouci verify failed', ['ref' => $payment->gateway_ref, 'status' => $res->status()]);

            return false;
        }

        return $res->json('result.status') === 'SUCCESS';
    }
}
