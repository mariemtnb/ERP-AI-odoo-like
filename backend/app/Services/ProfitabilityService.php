<?php

namespace App\Services;

use App\Support\AccountMap;
use Illuminate\Support\Facades\DB;

/**
 * The owner's money view: profit, where the money went, and which products
 * actually made money.
 *
 * Everything is read from data that already exists — the double-entry ledger
 * and the confirmed sales — so these figures always agree with the books.
 * Nothing here posts or changes anything.
 */
class ProfitabilityService
{
    /**
     * Profit and expenses over a period.
     *
     * @return array<string,mixed>
     */
    public static function summary(?string $from = null, ?string $to = null): array
    {
        // Totals per account over the period, from the journal.
        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->when($from, fn ($q) => $q->whereDate('e.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('e.entry_date', '<=', $to))
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->select(
                'a.code', 'a.name', 'a.type',
                DB::raw('SUM(l.debit) as debit'),
                DB::raw('SUM(l.credit) as credit'),
            )
            ->get();

        $cogsCode = AccountMap::codeOrNull('cogs');
        $salaryCode = AccountMap::codeOrNull('salary_expense');

        $revenue = 0.0;
        $cogs = 0.0;
        $salaries = 0.0;
        $otherExpenses = 0.0;
        $expenseRows = [];

        foreach ($rows as $r) {
            $debit = (float) $r->debit;
            $credit = (float) $r->credit;

            if ($r->type === 'income') {
                $revenue += $credit - $debit;   // income grows on the credit side
            } elseif ($r->type === 'expense') {
                $amount = $debit - $credit;      // expense grows on the debit side
                if ($r->code === $cogsCode) {
                    $cogs += $amount;
                } elseif ($r->code === $salaryCode) {
                    $salaries += $amount;
                } else {
                    $otherExpenses += $amount;
                }
                if (abs($amount) > 0.0005) {
                    $expenseRows[] = [
                        'code' => $r->code,
                        'name' => $r->name,
                        'amount' => round($amount, 3),
                    ];
                }
            }
        }

        $revenue = round($revenue, 3);
        $cogs = round($cogs, 3);
        $salaries = round($salaries, 3);
        $otherExpenses = round($otherExpenses, 3);
        $totalExpenses = round($cogs + $salaries + $otherExpenses, 3);
        $grossProfit = round($revenue - $cogs, 3);
        $netProfit = round($revenue - $totalExpenses, 3);

        usort($expenseRows, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return [
            'title' => 'Profit summary',
            'date_from' => $from,
            'date_to' => $to,
            'revenue' => $revenue,
            'cost_of_goods_sold' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin_pct' => $revenue > 0 ? round($grossProfit / $revenue * 100, 1) : 0,
            'salaries' => $salaries,
            'other_expenses' => $otherExpenses,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'net_margin_pct' => $revenue > 0 ? round($netProfit / $revenue * 100, 1) : 0,
            'expense_breakdown' => $expenseRows,
        ];
    }

    /**
     * Which products made the most profit over the period.
     *
     * Margin = (selling price − current cost price) × quantity, on confirmed
     * sales. Cost is the product's current cost, so it is an estimate rather
     * than the exact cost at sale time — good enough to see what earns.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function bestProducts(?string $from = null, ?string $to = null, int $limit = 10): array
    {
        $rows = DB::table('sale_lines as sl')
            ->join('sales as s', 's.id', '=', 'sl.sale_id')
            ->join('products as p', 'p.id', '=', 'sl.product_id')
            ->where('s.status', 'confirmed')
            ->when($from, fn ($q) => $q->whereDate('s.sale_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('s.sale_date', '<=', $to))
            ->groupBy('p.id', 'p.sku', 'p.name', 'p.cost_price')
            ->select(
                'p.sku', 'p.name', 'p.cost_price',
                DB::raw('SUM(sl.quantity) as qty'),
                DB::raw('SUM(sl.quantity * sl.unit_price) as revenue'),
                DB::raw('SUM(sl.quantity * (sl.unit_price - p.cost_price)) as margin'),
            )
            ->orderByDesc(DB::raw('SUM(sl.quantity * (sl.unit_price - p.cost_price))'))
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'sku' => $r->sku,
            'name' => $r->name,
            'quantity_sold' => round((float) $r->qty, 3),
            'revenue' => round((float) $r->revenue, 3),
            'margin' => round((float) $r->margin, 3),
            'margin_pct' => (float) $r->revenue > 0
                ? round((float) $r->margin / (float) $r->revenue * 100, 1)
                : 0,
        ])->values()->all();
    }

    /** Everything the owner dashboard needs, in one call. */
    public static function ownerView(?string $from = null, ?string $to = null): array
    {
        return [
            'summary' => self::summary($from, $to),
            'best_products' => self::bestProducts($from, $to, 10),
        ];
    }
}
