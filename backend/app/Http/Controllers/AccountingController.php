<?php

namespace App\Http\Controllers;

use App\Exceptions\UnbalancedEntry;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use App\Support\DrfPagination;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Chart of accounts, journal, and the two core financial statements. */
class AccountingController extends Controller
{
    private static function range(Request $request): array
    {
        return [$request->query('from'), $request->query('to')];
    }

    // ---------- chart of accounts ----------

    public function accounts(Request $request)
    {
        $query = Account::query()->orderBy('code');
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return response()->json([
            'results' => $query->get()->map(fn (Account $a) => $a->toApi())->values()->all(),
        ]);
    }

    public function storeAccount(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:accounts,code'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(Account::TYPES)],
        ]);

        return response()->json(Account::create($data)->toApi(), 201);
    }

    // ---------- journal ----------

    public function entries(Request $request)
    {
        $query = JournalEntry::with(['lines.account', 'creator'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if ($ref = $request->query('reference_type')) {
            $query->where('reference_type', $ref);
        }
        [$from, $to] = self::range($request);
        if ($from) {
            $query->where('entry_date', '>=', $from);
        }
        if ($to) {
            $query->where('entry_date', '<=', $to);
        }
        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q
                ->where('number', 'ilike', "%{$search}%")
                ->orWhere('memo', 'ilike', "%{$search}%"));
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (JournalEntry $e) => $e->toApi())
        );
    }

    public function showEntry(JournalEntry $entry)
    {
        return response()->json($entry->load(['lines.account', 'creator'])->toApi());
    }

    /** Manual journal entry — managers and admins only (see routes). */
    public function storeEntry(Request $request)
    {
        $data = $request->validate([
            'entry_date' => ['sometimes', 'date'],
            'memo' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account' => ['required'],
            'lines.*.debit' => ['sometimes', 'numeric', 'min:0'],
            'lines.*.credit' => ['sometimes', 'numeric', 'min:0'],
            'lines.*.label' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        try {
            $entry = AccountingService::post(
                lines: $data['lines'],
                user: $request->user(),
                memo: $data['memo'] ?? '',
                referenceType: 'manual',
                date: $data['entry_date'] ?? null,
            );
        } catch (UnbalancedEntry $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json($entry->load('creator')->toApi(), 201);
    }

    // ---------- statements ----------

    public function trialBalance(Request $request)
    {
        [$from, $to] = self::range($request);
        $data = AccountingService::trialBalance($from, $to);

        if ($request->query('export') === 'pdf') {
            return Pdf::loadView('reports.trial-balance', $data)->download('trial_balance.pdf');
        }

        return response()->json($data);
    }

    public function incomeStatement(Request $request)
    {
        [$from, $to] = self::range($request);
        $data = AccountingService::incomeStatement($from, $to);

        if ($request->query('export') === 'pdf') {
            return Pdf::loadView('reports.income-statement', $data)->download('income_statement.pdf');
        }

        return response()->json($data);
    }
}
