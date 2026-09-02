<?php

namespace App\Services;

use App\Exceptions\UnbalancedEntry;
use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Double-entry bookkeeping.
 *
 * Every entry must balance: sum(debit) === sum(credit). Business documents
 * post automatically when they reach the state that has accounting meaning
 * (sale confirmed, goods received), mirroring the stock ledger's model —
 * entries are append-only and cancellations post a reversal rather than
 * deleting history.
 */
class AccountingService
{
    /** Tolerance for float comparison on 2-decimal money. */
    private const EPSILON = 0.005;

    /**
     * Create a balanced journal entry.
     *
     * @param  array<int, array{account:string|int, debit?:float|string, credit?:float|string, label?:string}>  $lines
     *         `account` is an account code (string) or id (int).
     * @param  string|null  $journalCode  accounting journal to file the entry
     *         under (VT, BQ, CH, EF…). Optional — entries without one are
     *         reported as miscellaneous, which is how every entry predating
     *         the localization layer behaves.
     */
    public static function post(
        array $lines,
        User $user,
        string $memo = '',
        string $referenceType = 'manual',
        ?int $referenceId = null,
        ?string $date = null,
        ?string $journalCode = null,
    ): JournalEntry {
        if (count($lines) < 2) {
            throw new UnbalancedEntry('A journal entry needs at least two lines.');
        }

        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            $debit += (float) ($line['debit'] ?? 0);
            $credit += (float) ($line['credit'] ?? 0);
        }
        if (abs($debit - $credit) > self::EPSILON) {
            throw new UnbalancedEntry(sprintf(
                'Entry does not balance: debit %s vs credit %s.',
                number_format($debit, 2, '.', ''),
                number_format($credit, 2, '.', '')
            ));
        }
        if ($debit <= 0) {
            throw new UnbalancedEntry('Entry total must be greater than zero.');
        }

        return DB::transaction(function () use ($lines, $user, $memo, $referenceType, $referenceId, $date, $journalCode) {
            $entry = JournalEntry::create([
                'number' => DocumentService::nextNumber('JE', JournalEntry::class),
                'entry_date' => $date ?? now()->toDateString(),
                'journal_id' => Journal::idFor($journalCode),
                'memo' => $memo,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $user->id,
            ]);

            foreach ($lines as $line) {
                $accountId = is_int($line['account'])
                    ? $line['account']
                    : Account::where('code', $line['account'])->value('id');
                if (! $accountId) {
                    throw new UnbalancedEntry("Unknown account: {$line['account']}.");
                }
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $accountId,
                    'business_unit_id' => $line['business_unit'] ?? null,
                    'label' => $line['label'] ?? '',
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                ]);
            }

            return $entry->load('lines.account');
        });
    }

    /**
     * Sale confirmed — recognise the revenue and relieve inventory:
     *   Dr Accounts receivable / Cr Sales revenue   (selling price)
     *   Dr Cost of goods sold  / Cr Inventory       (cost price)
     */
    public static function postSaleConfirmed(Sale $sale, User $user): ?JournalEntry
    {
        $revenue = round((float) $sale->total_amount, 2);
        if ($revenue <= 0) {
            return null;
        }

        $cogs = 0.0;
        foreach ($sale->lines as $line) {
            // Value the goods sold at the moving-average cost, not a frozen
            // standard price, so Inventory relieves what the stock really cost.
            $cogs += (float) $line->quantity * ($line->product
                ? InventoryValuationService::unitCost($line->product) : 0.0);
        }
        $cogs = round($cogs, 2);

        $lines = [
            ['account' => Account::RECEIVABLE, 'debit' => $revenue, 'label' => "Sale {$sale->number}"],
            ['account' => Account::REVENUE, 'credit' => $revenue, 'label' => "Sale {$sale->number}"],
        ];
        if ($cogs > 0) {
            $lines[] = ['account' => Account::COGS, 'debit' => $cogs, 'label' => "COGS {$sale->number}"];
            $lines[] = ['account' => Account::INVENTORY, 'credit' => $cogs, 'label' => "COGS {$sale->number}"];
        }

        return self::post(
            lines: $lines,
            user: $user,
            memo: "Sale {$sale->number}",
            referenceType: 'sale',
            referenceId: $sale->id,
            date: $sale->sale_date?->toDateString(),
            journalCode: Journal::SALES,
        );
    }

    /**
     * A point-of-sale ticket — cash in, revenue recognised, inventory relieved:
     *   Dr Cash / Cr Sales revenue        (ticket total, paid at the till)
     *   Dr Cost of goods sold / Cr Inventory   (moving-average cost)
     */
    public static function postPosSale(\App\Models\PosOrder $order, User $user): ?JournalEntry
    {
        $revenue = round((float) $order->total_amount, 2);
        if ($revenue <= 0) {
            return null;
        }

        $cogs = 0.0;
        foreach ($order->lines as $line) {
            $cogs += (float) $line->quantity * ($line->product
                ? InventoryValuationService::unitCost($line->product) : 0.0);
        }
        $cogs = round($cogs, 2);

        $lines = [
            ['account' => Account::CASH, 'debit' => $revenue, 'label' => "POS {$order->number}"],
            ['account' => Account::REVENUE, 'credit' => $revenue, 'label' => "POS {$order->number}"],
        ];
        if ($cogs > 0) {
            $lines[] = ['account' => Account::COGS, 'debit' => $cogs, 'label' => "COGS {$order->number}"];
            $lines[] = ['account' => Account::INVENTORY, 'credit' => $cogs, 'label' => "COGS {$order->number}"];
        }

        return self::post(
            lines: $lines,
            user: $user,
            memo: "POS sale {$order->number}",
            referenceType: 'pos',
            referenceId: $order->id,
            journalCode: Journal::CASH,
        );
    }

    /**
     * Goods received — the stock is now an asset owed to the supplier:
     *   Dr Inventory / Cr Accounts payable
     */
    public static function postPurchaseReceived(PurchaseOrder $po, User $user, ?float $amount = null): ?JournalEntry
    {
        // Post the value received now (partial receipts), or the whole order.
        $total = round($amount ?? (float) $po->total_amount, 2);
        if ($total <= 0) {
            return null;
        }

        return self::post(
            lines: [
                ['account' => Account::INVENTORY, 'debit' => $total, 'label' => "Receipt {$po->number}"],
                ['account' => Account::PAYABLE, 'credit' => $total, 'label' => "Receipt {$po->number}"],
            ],
            user: $user,
            memo: "Goods receipt {$po->number}",
            referenceType: 'purchase',
            referenceId: $po->id,
            date: $po->received_date?->toDateString(),
            journalCode: Journal::PURCHASE,
        );
    }

    /**
     * Cancelling a confirmed sale posts a mirror-image entry rather than
     * deleting the original — the ledger stays append-only and auditable.
     */
    public static function reverseSale(Sale $sale, User $user): ?JournalEntry
    {
        $original = JournalEntry::with('lines')
            ->where('reference_type', 'sale')
            ->where('reference_id', $sale->id)
            ->orderBy('id')
            ->first();
        if (! $original) {
            return null;
        }

        $lines = $original->lines->map(fn (JournalEntryLine $l) => [
            'account' => (int) $l->account_id,
            'debit' => (float) $l->credit,   // swapped
            'credit' => (float) $l->debit,
            'label' => "Reversal {$sale->number}",
        ])->all();

        return self::post(
            lines: $lines,
            user: $user,
            memo: "Cancellation of {$sale->number}",
            referenceType: 'sale',
            referenceId: $sale->id,
            journalCode: Journal::SALES,
        );
    }

    // ---------------- reports ----------------

    /** Trial balance: per-account debit/credit totals and net balance. */
    public static function trialBalance(?string $from = null, ?string $to = null): array
    {
        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->when($from, fn ($q) => $q->where('e.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('e.entry_date', '<=', $to))
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->orderBy('a.code')
            ->get([
                'a.code', 'a.name', 'a.type',
                DB::raw('SUM(l.debit) as debit'),
                DB::raw('SUM(l.credit) as credit'),
            ]);

        $out = $rows->map(function ($r) {
            $debit = (float) $r->debit;
            $credit = (float) $r->credit;
            $debitNormal = in_array($r->type, [Account::TYPE_ASSET, Account::TYPE_EXPENSE], true);

            return [
                'code' => $r->code,
                'name' => $r->name,
                'type' => $r->type,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'balance' => round($debitNormal ? $debit - $credit : $credit - $debit, 2),
            ];
        })->values()->all();

        return [
            'title' => 'Trial balance',
            'date_from' => $from,
            'date_to' => $to,
            'rows' => $out,
            'total_debit' => round(array_sum(array_column($out, 'debit')), 2),
            'total_credit' => round(array_sum(array_column($out, 'credit')), 2),
        ];
    }

    /** Income statement: revenue − expenses over the period. */
    public static function incomeStatement(?string $from = null, ?string $to = null): array
    {
        $tb = self::trialBalance($from, $to);
        $income = array_values(array_filter($tb['rows'], fn ($r) => $r['type'] === Account::TYPE_INCOME));
        $expense = array_values(array_filter($tb['rows'], fn ($r) => $r['type'] === Account::TYPE_EXPENSE));
        $totalIncome = round(array_sum(array_column($income, 'balance')), 2);
        $totalExpense = round(array_sum(array_column($expense, 'balance')), 2);

        return [
            'title' => 'Income statement',
            'date_from' => $from,
            'date_to' => $to,
            'income' => $income,
            'expenses' => $expense,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpense,
            'net_profit' => round($totalIncome - $totalExpense, 2),
        ];
    }
}
