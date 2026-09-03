<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Default SMS channel: writes the message to the log instead of sending it, so
 * the whole flow works with no provider. Swap the configured provider for a
 * real one in production.
 */
class LogSmsSender implements SmsSender
{
    public function key(): string
    {
        return 'log';
    }

    public function send(string $to, string $body): bool
    {
        Log::info('SMS (log channel — not delivered)', ['to' => $to, 'body' => $body]);

        return true;
    }
}
