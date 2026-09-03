<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Twilio SMS channel. Posts to the Messages endpoint with the account SID and
 * auth token from configuration (services.sms.twilio). Any transport error is
 * turned into a false return rather than an exception, so a flaky provider
 * never breaks the caller.
 */
class TwilioSmsSender implements SmsSender
{
    public function key(): string
    {
        return 'twilio';
    }

    public function send(string $to, string $body): bool
    {
        $cfg = config('services.sms.twilio');
        $sid = $cfg['sid'] ?? null;
        $token = $cfg['token'] ?? null;
        $from = $cfg['from'] ?? null;
        if (! $sid || ! $token || ! $from) {
            Log::warning('Twilio SMS not configured');

            return false;
        }

        try {
            $res = Http::withBasicAuth($sid, $token)->asForm()->timeout(15)->post(
                "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json",
                ['To' => $to, 'From' => $from, 'Body' => $body]
            );

            return $res->successful();
        } catch (\Throwable $e) {
            Log::warning('Twilio SMS failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
