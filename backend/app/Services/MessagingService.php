<?php

namespace App\Services;

use App\Mail\PlainMail;
use App\Services\Sms\SmsSender;
use App\Services\Sms\SmsSenders;
use Illuminate\Support\Facades\Mail;

/**
 * One place to send outbound messages, so callers never touch a transport
 * directly. Email goes through Laravel's configured mailer (SMTP in
 * production, the log/array driver otherwise); SMS goes through the configured
 * SmsSender. Both return whether delivery was accepted and never throw.
 */
class MessagingService
{
    /** Send a plain email; returns false (and reports) if the mailer errors. */
    public static function sendEmail(string $to, string $subject, string $body): bool
    {
        try {
            Mail::to($to)->send(new PlainMail($subject, $body));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /** Send an SMS through the configured channel. */
    public static function sendSms(string $to, string $body, ?SmsSender $sender = null): bool
    {
        return ($sender ?? SmsSenders::current())->send($to, $body);
    }

    /** The transports currently in effect — for the admin messaging screen. */
    public static function channels(): array
    {
        return [
            'mail_mailer' => (string) config('mail.default'),
            'mail_from' => (string) config('mail.from.address'),
            'sms_provider' => (string) config('services.sms.provider', 'log'),
        ];
    }
}
