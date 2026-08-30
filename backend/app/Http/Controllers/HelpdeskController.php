<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\Ticket;
use App\Services\HelpdeskService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;

class HelpdeskController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::with(['customer', 'assignee', 'creator'])->orderByDesc('updated_at')->orderByDesc('id');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        return response()->json(DrfPagination::paginate($query, $request, fn (Ticket $t) => $t->toApi()));
    }

    public function show(Ticket $ticket)
    {
        return response()->json($ticket->load(['customer', 'assignee', 'creator'])->toApi() + [
            'messages' => $ticket->messages()->with('user')->orderBy('id')->get()->map(fn ($m) => $m->toApi())->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'customer' => ['nullable', 'integer', 'exists:customers,id'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'message' => ['nullable', 'string', 'max:4000'],
        ]);

        try {
            $ticket = HelpdeskService::create(
                $data['subject'], $data['customer'] ?? null, $data['priority'] ?? 'normal',
                $request->user(), $data['message'] ?? null
            );
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($ticket->toApi(), 201);
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);

        try {
            $message = HelpdeskService::addMessage($ticket, $request->user(), $data['body']);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($message->toApi(), 201);
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $data = $request->validate(['user' => ['nullable', 'integer', 'exists:users,id']]);
        HelpdeskService::assign($ticket, $data['user'] ?? null);

        return response()->json($ticket->fresh()->toApi());
    }

    public function transition(Request $request, Ticket $ticket, string $status)
    {
        try {
            HelpdeskService::transition($ticket, $status);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($ticket->fresh()->toApi());
    }
}
