<?php

namespace App\Http\Controllers;

use App\Models\RecordActivity;
use App\Models\RecordMessage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Comments and scheduled activities on any record. The record is named by a
 * type from a fixed whitelist plus its id, so the same endpoints serve every
 * screen. Any signed-in user may read and post — the screens themselves decide
 * who sees what.
 */
class ChatterController extends Controller
{
    /** Record types that can carry chatter. */
    private const TYPES = [
        'sales', 'purchases', 'customers', 'suppliers', 'products',
        'tickets', 'leads', 'projects', 'vendor_bills', 'employees',
    ];

    private function assertType(string $type): void
    {
        abort_unless(in_array($type, self::TYPES, true), 404);
    }

    /** The whole timeline for one record: messages and activities. */
    public function index(string $type, int $id)
    {
        $this->assertType($type);

        return response()->json([
            'messages' => RecordMessage::with('author')
                ->where('subject_type', $type)->where('subject_id', $id)
                ->orderByDesc('id')->get()->map(fn (RecordMessage $m) => $m->toApi())->all(),
            'activities' => RecordActivity::with('assignee')
                ->where('subject_type', $type)->where('subject_id', $id)
                ->orderBy('done')->orderBy('due_date')->get()->map(fn (RecordActivity $a) => $a->toApi())->all(),
        ]);
    }

    public function storeMessage(Request $request, string $type, int $id)
    {
        $this->assertType($type);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $message = RecordMessage::create([
            'subject_type' => $type, 'subject_id' => $id,
            'user_id' => $request->user()->id, 'body' => $data['body'],
        ]);

        return response()->json($message->load('author')->toApi(), 201);
    }

    public function storeActivity(Request $request, string $type, int $id)
    {
        $this->assertType($type);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $activity = RecordActivity::create([
            'subject_type' => $type, 'subject_id' => $id,
            'title' => $data['title'],
            'due_date' => $data['due_date'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? $request->user()->id,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($activity->load('assignee')->toApi(), 201);
    }

    /** Mark an activity done (or re-open it). */
    public function toggleActivity(Request $request, RecordActivity $activity)
    {
        $data = $request->validate(['done' => ['sometimes', 'boolean']]);
        $done = $data['done'] ?? true;

        $activity->update(['done' => $done, 'done_at' => $done ? now() : null]);

        return response()->json($activity->load('assignee')->toApi());
    }

    /** The signed-in user's own open activities, soonest first — a to-do list. */
    public function mine(Request $request)
    {
        $activities = RecordActivity::with('assignee')
            ->where('assigned_to', $request->user()->id)
            ->where('done', false)
            ->orderByRaw('due_date is null, due_date')
            ->limit(100)->get()
            ->map(fn (RecordActivity $a) => $a->toApi(withSubject: true))->all();

        return response()->json(['results' => $activities]);
    }
}
