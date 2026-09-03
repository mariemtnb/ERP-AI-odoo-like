<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Support\DrfPagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** CRM: lead pipeline, activity log, conversion to customer. */
class LeadController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::with('assignee')->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('company', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%"));
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (Lead $l) => $l->toApi())
        );
    }

    private function rules(bool $create): array
    {
        $required = $create ? 'required' : 'sometimes';

        // Laravel's ConvertEmptyStringsToNull turns "" into null — accept it.
        return [
            'name' => [$required, 'string', 'max:200'],
            'company' => ['sometimes', 'nullable', 'string', 'max:200'],
            'email' => ['sometimes', 'nullable', 'email'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'source' => ['sometimes', 'nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(Lead::STATUSES)],
            'stage_id' => ['sometimes', 'nullable', 'integer', 'exists:crm_stages,id'],
            'expected_revenue' => ['sometimes', 'numeric', 'min:0'],
            'probability' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }

    private static function coerceNulls(array $data): array
    {
        foreach (['company', 'email', 'phone', 'source', 'notes'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === null) {
                $data[$field] = '';
            }
        }

        return $data;
    }

    public function store(Request $request)
    {
        $data = self::coerceNulls($request->validate($this->rules(true)));
        $data['created_by'] = $request->user()->id;
        $data['assigned_to'] = $data['assigned_to'] ?? $request->user()->id;

        return response()->json(Lead::create($data)->load('assignee')->toApi(), 201);
    }

    public function show(Lead $lead)
    {
        return response()->json($lead->load(['assignee', 'activities.creator'])->toApi(true));
    }

    public function update(Request $request, Lead $lead)
    {
        $data = self::coerceNulls($request->validate($this->rules(false)));
        $lead->update($data);

        return response()->json($lead->load('assignee')->toApi());
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return response()->json(null, 204);
    }

    public function addActivity(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['call', 'email', 'meeting', 'note'])],
            'summary' => ['required', 'string', 'max:2000'],
        ]);

        $activity = LeadActivity::create([
            'lead_id' => $lead->id,
            'type' => $data['type'],
            'summary' => $data['summary'],
            'created_by' => $request->user()->id,
            'created_at' => now(),
        ]);

        return response()->json($activity->load('creator')->toApi(), 201);
    }

    /** Won lead → real customer (idempotent). */
    public function convert(Request $request, Lead $lead)
    {
        if ($lead->customer_id) {
            return response()->json(
                ['detail' => 'Lead already converted.', 'customer_id' => $lead->customer_id],
                409
            );
        }

        $customer = DB::transaction(function () use ($lead, $request) {
            $customer = Customer::create([
                'name' => $lead->company !== '' ? "{$lead->name} ({$lead->company})" : $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'notes' => "Converted from lead #{$lead->id}" . ($lead->notes !== '' ? " — {$lead->notes}" : ''),
            ]);
            $lead->update(['status' => 'won', 'customer_id' => $customer->id]);
            LeadActivity::create([
                'lead_id' => $lead->id,
                'type' => 'note',
                'summary' => "Converted to customer #{$customer->id}",
                'created_by' => $request->user()->id,
                'created_at' => now(),
            ]);

            return $customer;
        });

        return response()->json([
            'customer_id' => $customer->id,
            'lead' => $lead->load('assignee')->toApi(),
        ], 201);
    }
}
