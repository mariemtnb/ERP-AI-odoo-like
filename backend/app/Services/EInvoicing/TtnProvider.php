<?php

namespace App\Services\EInvoicing;

use App\Models\EInvoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real TTN «El Fatoora» adapter.
 *
 * Posts the (signed) TEIF document to the TTN endpoint and reads back the
 * assigned reference. Credentials and the base URL come from configuration
 * (services.einvoice.ttn). Signing with the company's electronic certificate
 * is expected to happen before this point; wire the signer in the service and
 * this adapter transmits whatever XML the record carries.
 *
 * Kept deliberately thin and defensive: any transport or protocol error is
 * turned into a rejection with a readable reason rather than an exception, so a
 * flaky platform never leaves an e-invoice in an ambiguous state.
 */
class TtnProvider implements EInvoiceProvider
{
    public function key(): string
    {
        return 'ttn';
    }

    public function submit(EInvoice $eInvoice): SubmissionResult
    {
        $cfg = config('services.einvoice.ttn');
        $base = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        if ($base === '' || empty($cfg['username']) || empty($cfg['api_key'])) {
            return SubmissionResult::rejected('TTN credentials are not configured.');
        }

        try {
            $res = Http::withHeaders([
                'Accept' => 'application/json',
                'X-Api-Key' => (string) $cfg['api_key'],
            ])->withBasicAuth((string) $cfg['username'], (string) ($cfg['password'] ?? ''))
                ->timeout(20)
                ->attach('teif', $eInvoice->xml, 'invoice.xml')
                ->post("{$base}/invoices");

            if (! $res->successful()) {
                return SubmissionResult::rejected('TTN rejected the invoice: HTTP '.$res->status().' '.$res->body());
            }

            $ref = $res->json('reference') ?? $res->json('id');
            if (! $ref) {
                return SubmissionResult::rejected('TTN accepted the invoice but returned no reference.');
            }

            return SubmissionResult::accepted((string) $ref);
        } catch (\Throwable $e) {
            Log::warning('TTN submission failed', ['e_invoice' => $eInvoice->id, 'error' => $e->getMessage()]);

            return SubmissionResult::rejected('Could not reach TTN: '.$e->getMessage());
        }
    }
}
