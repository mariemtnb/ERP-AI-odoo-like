<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Services\BudgetService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Budgets and budget-vs-actual (managers/admins). */
class BudgetController extends Controller
{
    public function index()
    {
        return response()->json(
            Budget::orderByDesc('period_start')->get()->map(fn ($b) => $b->toApi())->all()
        );
    }

    public function show(Budget $budget)
    {
        return response()->json($budget->load('lines.account')->toApi());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'status' => ['sometimes', Rule::in(Budget::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string'],
            'lines' => ['sometimes', 'array'],
            'lines.*.account_code' => ['required_with:lines', 'string', 'exists:accounts,code'],
            'lines.*.amount' => ['required_with:lines', 'numeric'],
        ]);

        $budget = Budget::create([
            'name' => $data['name'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'status' => $data['status'] ?? Budget::STATUS_DRAFT,
            'notes' => $data['notes'] ?? '',
            'created_by' => $request->user()->id,
        ]);

        foreach ($data['lines'] ?? [] as $line) {
            $budget->lines()->updateOrCreate(
                ['account_code' => $line['account_code']],
                ['amount' => $line['amount']]
            );
        }

        return response()->json($budget->load('lines.account')->toApi(), 201);
    }

    /** Add or update one budget line (upsert by account). */
    public function upsertLine(Request $request, Budget $budget)
    {
        $data = $request->validate([
            'account_code' => ['required', 'string', 'exists:accounts,code'],
            'amount' => ['required', 'numeric'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $budget->lines()->updateOrCreate(
            ['account_code' => $data['account_code']],
            ['amount' => $data['amount'], 'notes' => $data['notes'] ?? '']
        );

        return response()->json($budget->load('lines.account')->toApi(), 201);
    }

    public function destroy(Budget $budget)
    {
        $budget->delete();

        return response()->json(['detail' => 'Budget deleted.']);
    }

    /** Budget-vs-actual against the ledger over the budget's period. */
    public function vsActual(Budget $budget)
    {
        return response()->json(BudgetService::vsActual($budget));
    }
}
