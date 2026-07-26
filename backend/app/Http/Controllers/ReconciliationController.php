<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Exceptions\UnbalancedEntry;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\ReconciliationMatch;
use App\Services\ReconciliationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Bank reconciliation: suggestions, matching, and the statement report. */
class ReconciliationController extends Controller
{
    /** Ranked candidates for one statement line. */
    public function suggestions(BankTransaction $bankTransaction)
    {
        return response()->json([
            'transaction' => $bankTransaction->load('bankAccount')->toApi(withMatches: true),
            'suggestions' => ReconciliationService::suggestions($bankTransaction),
        ]);
    }

    public function match(Request $request, BankTransaction $bankTransaction)
    {
        $data = $request->validate([
            'matchable_type' => ['required', Rule::in(ReconciliationMatch::TYPES)],
            'matchable_id' => ['required_unless:matchable_type,adjustment', 'nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Clearing an instrument on match is the useful default; opt out
            // when back-filling historical statements.
            'apply_side_effects' => ['sometimes', 'boolean'],
        ]);

        try {
            $match = ReconciliationService::match(
                tx: $bankTransaction,
                type: $data['matchable_type'],
                id: $data['matchable_id'] ?? null,
                amount: (float) $data['amount'],
                user: $request->user(),
                note: $data['note'] ?? '',
                applySideEffects: (bool) ($data['apply_side_effects'] ?? true),
            );
        } catch (InvalidTransition|UnbalancedEntry $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json([
            'match' => $match->toApi(),
            'transaction' => $bankTransaction->refresh()->load('matches')->toApi(withMatches: true),
        ], 201);
    }

    public function unmatch(Request $request, ReconciliationMatch $match)
    {
        $transaction = ReconciliationService::unmatch($match, $request->user());

        return response()->json($transaction->load('matches')->toApi(withMatches: true));
    }

    public function dispute(Request $request, BankTransaction $bankTransaction)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        return response()->json(
            ReconciliationService::dispute($bankTransaction, $data['reason'], $request->user())->toApi()
        );
    }

    /** Reconciliation statement, as JSON or PDF for the accountant. */
    public function report(Request $request)
    {
        $request->validate([
            'bank_account' => ['required', 'integer', 'exists:bank_accounts,id'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
        ]);

        $account = BankAccount::with(['bank', 'glAccount'])->findOrFail($request->query('bank_account'));
        $data = ReconciliationService::report(
            $account,
            $request->query('from'),
            $request->query('to'),
        );

        if ($request->query('export') === 'pdf') {
            return Pdf::loadView('reports.bank-reconciliation', $data)
                ->download("reconciliation_{$account->id}.pdf");
        }

        return response()->json($data);
    }

    /** Cross-account pending figures for the dashboard. */
    public function pending()
    {
        return response()->json(ReconciliationService::pendingSummary());
    }
}
