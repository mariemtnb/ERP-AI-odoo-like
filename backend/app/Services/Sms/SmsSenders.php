<?php

namespace App\Services\Sms;

/**
 * Resolves the configured SMS channel. Defaults to the log channel, so the
 * feature works out of the box; set services.sms.provider to 'twilio' (with
 * credentials) to deliver real messages.
 */
class SmsSenders
{
    public static function current(): SmsSender
    {
        return match ((string) config('services.sms.provider', 'log')) {
            'twilio' => new TwilioSmsSender(),
            default => new LogSmsSender(),
        };
    }
}
