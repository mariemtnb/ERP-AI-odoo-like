<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\DrfPagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Chat proxy to the FastAPI AI service. The user's JWT is forwarded so the
 * agent's tool calls hit this API under the user's own permissions.
 */
class AssistantController extends Controller
{
    public function conversations(Request $request)
    {
        $query = Conversation::where('user_id', $request->user()->id)
            ->with('messages')->orderByDesc('created_at');

        return response()->json(
            DrfPagination::paginate($query, $request, fn (Conversation $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'created_at' => $c->created_at?->toISOString(),
                'messages' => $c->messages->map(fn (Message $m) => $m->toApi())->values()->all(),
            ])
        );
    }

    public function destroyConversation(Request $request, int $id)
    {
        Conversation::where('user_id', $request->user()->id)->findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    public function chat(Request $request)
    {
        $approve = $request->input('approve');
        $messageText = trim((string) $request->input('message', ''));
        $conversationId = $request->input('conversation_id');

        if (is_null($approve) && $messageText === '') {
            return response()->json(['detail' => 'message is required'], 400);
        }

        if ($conversationId) {
            $conversation = Conversation::where('user_id', $request->user()->id)
                ->find($conversationId);
            if (! $conversation) {
                return response()->json(['detail' => 'Conversation not found.'], 404);
            }
        } else {
            $conversation = Conversation::create([
                'user_id' => $request->user()->id,
                'title' => mb_substr($messageText, 0, 120),
            ]);
        }

        $token = str_replace('Bearer ', '', (string) $request->header('Authorization'));
        $thread = "conv-{$conversation->id}";
        $aiUrl = env('AI_SERVICE_URL', 'http://ai-service:8001');
        $timeout = (int) env('AI_TIMEOUT', 600);

        if (is_null($approve)) {
            Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $messageText,
            ]);
            $endpoint = '/chat';
            $payload = [
                'thread_id' => $thread,
                'message' => $messageText,
                // Auto mode: the assistant approves its own write actions. Off
                // by default — the UI opts in per message. Every action still
                // runs with this user's JWT and is audit-logged.
                'auto_approve' => $request->boolean('auto_approve'),
            ];
        } else {
            Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $approve ? '✔ Approved' : '✘ Rejected',
            ]);
            $endpoint = '/resume';
            $payload = ['thread_id' => $thread, 'approved' => (bool) $approve];
        }

        try {
            $response = Http::withToken($token)->timeout($timeout)
                ->post($aiUrl.$endpoint, $payload)->throw();
            $data = $response->json();
        } catch (\Throwable $e) {
            return response()->json(['detail' => "AI service unavailable: {$e->getMessage()}"], 502);
        }

        if (($data['type'] ?? '') === 'confirmation_required') {
            $reply = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => '',
                'pending_action' => $data['action'],
            ]);
        } else {
            $reply = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $data['reply'] ?? '',
                'tool_calls' => $data['tool_calls'] ?? null,
            ]);
            foreach ($data['tool_calls'] ?? [] as $call) {
                AuditLog::create([
                    'user_id' => $request->user()->id,
                    'actor' => 'agent',
                    'action' => $call['name'],
                    'payload' => $call['args'],
                ]);
            }
        }

        return response()->json([
            'conversation_id' => $conversation->id,
            'type' => $data['type'],
            'message' => $reply->toApi(),
        ]);
    }
}
