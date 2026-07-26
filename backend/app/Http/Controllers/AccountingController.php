<?php

namespace App\Http\Controllers;

use App\Exceptions\UnbalancedEntry;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PaymentInstrument;
use App\Models\PurchaseOrder;
use App\Models\Sale;
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
        $query = JournalEntry::with(['lines.account', 'creator', 'journal'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if ($ref = $request->query('reference_type')) {
            $query->where('reference_type', $ref);
        }
        // Accountants read the books one journal at a time (ventes, banque…).
        if ($journal = $request->query('journal')) {
            $query->whereHas('journal', fn ($q) => $q->where('code', $journal));
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
        return response()->json($entry->load(['lines.account', 'creator', 'journal'])->toApi());
    }

    /**
     * Why does this entry exist?
     *
     * Returns the entry alongside the business event that caused it and a
     * plain-language reading of each line. The AI assistant uses this to
     * answer "why was this posted?" from recorded facts instead of guessing
     * at accounting rules.
     */
    public function explainEntry(JournalEntry $entry)
    {
        $entry->load(['lines.account', 'creator', 'journal']);

        $source = match ($entry->reference_type) {
            'sale' => Sale::with('customer')->find($entry->reference_id),
            'purchase' => PurchaseOrder::with('supplier')->find($entry->reference_id),
            'instrument' => PaymentInstrument::with(['customer', 'supplier'])->find($entry->reference_id),
            default => null,
        };

        $lines = $entry->lines->map(function (JournalEntryLine $line) {
            $account = $line->account;
            $isDebit = (float) $line->debit > 0;
            $amount = $isDebit ? $line->debit : $line->credit;
            $direction = $account && $account->isDebitNormal()
                ? ($isDebit ? 'increases' : 'decreases')
                : ($isDebit ? 'decreases' : 'increases');

            return [
                'account_code' => $account?->code,
                'account_name' => $account?->name,
                'account_type' => $account?->type,
                'side' => $isDebit ? 'debit' : 'credit',
                'amount' => (string) $amount,
                'effect' => sprintf(
                    '%s %s (%s) by %s',
                    $isDebit ? 'Debit' : 'Credit',
                    $account?->name ?? 'account',
                    $direction,
                    (string) $amount
                ),
            ];
        })->values()->all();

        $trigger = match ($entry->reference_type) {
            'sale' => 'A sale was confirmed or cancelled.',
            'purchase' => 'Goods were received against a purchase order.',
            'instrument' => 'A cheque or commercial paper changed state in its lifecycle.',
            'reconciliation' => 'A bank statement line was reconciled as an adjustment.',
            'payment' => 'A payment was recorded.',
            'manual' => 'Someone posted this entry by hand.',
            default => 'Recorded by the system.',
        };

        return response()->json([
            'entry' => $entry->toApi(),
            'journal' => $entry->journal?->toApi(),
            'trigger' => $trigger,
            'reference' => $source ? [
                'type' => $entry->reference_type,
                'id' => $entry->reference_id,
                'number' => $source->number ?? null,
                'status' => $source->status ?? null,
            ] : null,
            'lines' => $lines,
            'balanced' => abs($entry->lines->sum('debit') - $entry->lines->sum('credit')) < 0.005,
        ]);
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
