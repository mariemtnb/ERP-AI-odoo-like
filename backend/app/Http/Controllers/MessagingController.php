<?php

namespace App\Http\Controllers;

use App\Services\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin messaging: see the configured channels and send a test message. */
class MessagingController extends Controller
{
    /** Which mail/SMS transports are currently in effect. */
    public function channels()
    {
        return response()->json(MessagingService::channels());
    }

    /** Send a test email or SMS to verify the provider is configured. */
    public function test(Request $request)
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['email', 'sms'])],
            'to' => ['required', 'string', 'max:190'],
            'message' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $body = ($data['message'] ?? null) ?: 'This is a test message from your ERP.';

        $sent = $data['channel'] === 'email'
            ? MessagingService::sendEmail($data['to'], 'ERP test email', $body)
            : MessagingService::sendSms($data['to'], $body);

        return response()->json([
            'channel' => $data['channel'],
            'to' => $data['to'],
            'sent' => $sent,
            'channels' => MessagingService::channels(),
        ], $sent ? 200 : 502);
    }
}
