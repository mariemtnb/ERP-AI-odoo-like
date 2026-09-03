<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CompanyProfile;
use App\Models\OnlinePayment;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\VendorBill;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Statutory financial statements read straight from the ledger and the open
 * documents: a balance sheet, a per-account general ledger, and aged
 * receivable/payable balances. Nothing here posts — it only reads, so every
 * figure agrees with the books by construction.
 */
class FinancialReportService
{
    /** Balance sheet as at a date: assets = liabilities + equity + result. */
    public static function balanceSheet(?string $asOf = null): array
    {
        $asOf ??= now()->toDateString();
        $rows = AccountingService::trialBalance(null, $asOf)['rows'];

        $section = fn (string $type) => array_values(array_filter($rows, fn ($r) => $r['type'] === $type));
        $sum = fn (array $rs) => round(array_sum(array_column($rs, 'balance')), 2);

        $assets = $section(Account::TYPE_ASSET);
        $liabilities = $section(Account::TYPE_LIABILITY);
        $equity = $section(Account::TYPE_EQUITY);
        $income = $sum($section(Account::TYPE_INCOME));
        $expense = $sum($section(Account::TYPE_EXPENSE));
        $result = round($income - $expense, 2);   // current-period result, folded into equity

        $totalAssets = $sum($assets);
        $totalEquity = round($sum($equity) + $result, 2);
        $totalLiabilities = $sum($liabilities);

        return [
            'title' => 'Balance sheet',
            'as_of' => $asOf,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'result_for_period' => $result,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'total_liabilities_and_equity' => round($totalLiabilities + $totalEquity, 2),
            // Double-entry guarantees this is ~0; surfaced so the report can prove it.
            'balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01,
        ];
    }

    /** General ledger for one account: opening balance, movements, running balance. */
    public static function generalLedger(string $accountCode, ?string $from = null, ?string $to = null): array
    {
        $account = Account::where('code', $accountCode)->firstOrFail();
        $debitNormal = $account->isDebitNormal();
        $sign = fn (float $d, float $c) => $debitNormal ? $d - $c : $c - $d;

        $opening = 0.0;
        if ($from) {
            $before = DB::table('journal_entry_lines as l')
                ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
                ->where('l.account_id', $account->id)
                ->where('e.entry_date', '<', $from)
                ->selectRaw('COALESCE(SUM(l.debit),0) as d, COALESCE(SUM(l.credit),0) as c')
                ->first();
            $opening = $sign((float) $before->d, (float) $before->c);
        }

        $lines = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.account_id', $account->id)
            ->when($from, fn ($q) => $q->where('e.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('e.entry_date', '<=', $to))
            ->orderBy('e.entry_date')->orderBy('e.id')->orderBy('l.id')
            ->get(['e.entry_date', 'e.number', 'l.label', 'l.debit', 'l.credit']);

        $running = round($opening, 2);
        $rows = [];
        foreach ($lines as $l) {
            $running = round($running + $sign((float) $l->debit, (float) $l->credit), 2);
            $rows[] = [
                'date' => $l->entry_date,
                'entry_number' => $l->number,
                'label' => $l->label,
                'debit' => round((float) $l->debit, 2),
                'credit' => round((float) $l->credit, 2),
                'balance' => $running,
            ];
        }

        return [
            'title' => 'General ledger',
            'account_code' => $account->code,
            'account_name' => $account->name,
            'date_from' => $from,
            'date_to' => $to,
            'opening_balance' => round($opening, 2),
            'rows' => $rows,
            'closing_balance' => $running,
        ];
    }

    /** Aged receivables per customer: open invoice balances bucketed by age. */
    public static function agedReceivables(?string $asOf = null): array
    {
        $asOf = $asOf ? Carbon::parse($asOf)->startOfDay() : Carbon::today();
        $terms = self::termDays();

        $sales = Sale::where('status', Sale::STATUS_CONFIRMED)->has('invoice')
            ->with(['invoice', 'customer'])->get();

        $byPartner = [];
        foreach ($sales as $sale) {
            $outstanding = self::saleOutstanding($sale);
            if ($outstanding <= 0.0005) {
                continue;
            }
            $base = $sale->invoice?->issued_at ?? $sale->sale_date;
            $due = $base ? Carbon::parse($base)->addDays($terms) : $asOf;
            self::accumulate($byPartner, $sale->customer_id, $sale->customer?->name, $outstanding, self::ageBucket($due, $asOf));
        }

        return self::agingPayload('Aged receivables', $asOf, $byPartner);
    }

    /** Aged payables per supplier: unpaid vendor bills bucketed by age. */
    public static function agedPayables(?string $asOf = null): array
    {
        $asOf = $asOf ? Carbon::parse($asOf)->startOfDay() : Carbon::today();
        $terms = self::termDays();

        // Vendor bills have no partial-payment tracking yet, so an unpaid bill
        // (matched or approved, not paid) is outstanding for its whole amount.
        $bills = VendorBill::whereIn('status', [VendorBill::STATUS_MATCHED, VendorBill::STATUS_APPROVED])
            ->with('supplier')->get();

        $byPartner = [];
        foreach ($bills as $bill) {
            $outstanding = round((float) $bill->total_amount, 3);
            if ($outstanding <= 0.0005) {
                continue;
            }
            $due = $bill->bill_date ? Carbon::parse($bill->bill_date)->addDays($terms) : $asOf;
            self::accumulate($byPartner, $bill->supplier_id, $bill->supplier?->name, $outstanding, self::ageBucket($due, $asOf));
        }

        return self::agingPayload('Aged payables', $asOf, $byPartner);
    }

    // ---------------- helpers ----------------

    private static function termDays(): int
    {
        $p = CompanyProfile::current();

        return (int) $p->default_payment_terms_days + (int) $p->late_payment_grace_days;
    }

    private static function saleOutstanding(Sale $sale): float
    {
        $paid = (float) Payment::where('reference_type', 'sale')->where('reference_id', $sale->id)
            ->where('direction', Payment::DIRECTION_IN)->sum('amount');
        $paid += (float) OnlinePayment::where('sale_id', $sale->id)->where('status', OnlinePayment::PAID)->sum('amount');

        return round((float) $sale->total_amount - $paid, 3);
    }

    /** Which bucket a due date falls into relative to the reporting date. */
    private static function ageBucket(Carbon $due, Carbon $asOf): string
    {
        if ($due->gte($asOf)) {
            return 'not_due';
        }
        $days = (int) $due->diffInDays($asOf);

        return match (true) {
            $days <= 30 => 'd1_30',
            $days <= 60 => 'd31_60',
            $days <= 90 => 'd61_90',
            default => 'd90_plus',
        };
    }

    private static function accumulate(array &$byPartner, ?int $id, ?string $name, float $amount, string $bucket): void
    {
        $key = $id ?? 0;
        $byPartner[$key] ??= [
            'partner_id' => $id, 'partner_name' => $name ?? '-',
            'not_due' => 0.0, 'd1_30' => 0.0, 'd31_60' => 0.0, 'd61_90' => 0.0, 'd90_plus' => 0.0, 'total' => 0.0,
        ];
        $byPartner[$key][$bucket] += $amount;
        $byPartner[$key]['total'] += $amount;
    }

    private static function agingPayload(string $title, Carbon $asOf, array $byPartner): array
    {
        $buckets = ['not_due', 'd1_30', 'd31_60', 'd61_90', 'd90_plus', 'total'];
        $rows = array_map(function ($r) use ($buckets) {
            foreach ($buckets as $b) {
                $r[$b] = round($r[$b], 2);
            }

            return $r;
        }, array_values($byPartner));
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        $totals = array_fill_keys($buckets, 0.0);
        foreach ($rows as $r) {
            foreach ($buckets as $b) {
                $totals[$b] = round($totals[$b] + $r[$b], 2);
            }
        }

        return ['title' => $title, 'as_of' => $asOf->toDateString(), 'rows' => $rows, 'totals' => $totals];
    }
}
