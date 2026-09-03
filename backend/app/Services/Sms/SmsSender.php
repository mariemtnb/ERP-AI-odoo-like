<?php

namespace App\Services\Sms;

/**
 * An SMS channel. Implementations deliver a short text to a phone number. A
 * real provider (Twilio, Orange, Tunisie Télécom…) implements this without
 * anything else changing; the default just logs, so the feature works out of
 * the box and in tests without credentials.
 */
interface SmsSender
{
    /** Provider key (e.g. "log", "twilio"). */
    public function key(): string;

    /** Send one message; returns true on success. Must not throw. */
    public function send(string $to, string $body): bool;
}
