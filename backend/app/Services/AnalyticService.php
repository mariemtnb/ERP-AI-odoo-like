<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\Account;
use App\Models\BusinessUnit;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;

/**
 * Analytic (cost-centre) reporting. Income and expense ledger lines carry an
 * optional business unit; this rolls them up per dimension so a manager can see
 * the P&L of a cost or profit centre, with everything untagged shown as
 * "unallocated".
 */
class AnalyticService
{
    /** Assign (or clear, with null) the business unit on a posted line. */
    public static function tag(JournalEntryLine $line, ?int $businessUnitId): JournalEntryLine
    {
        if ($businessUnitId !== null) {
            $unit = BusinessUnit::find($businessUnitId);
            if (! $unit) {
                throw new InvalidTransition('Unknown business unit.');
            }
            if (! $unit->is_active) {
                throw new InvalidTransition('That business unit is archived.');
            }
        }

        $line->update(['business_unit_id' => $businessUnitId]);

        return $line->refresh();
    }

    /** P&L per business unit over a period. */
    public static function pnl(?string $from = null, ?string $to = null): array
    {
        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'l.business_unit_id')
            ->whereIn('a.type', [Account::TYPE_INCOME, Account::TYPE_EXPENSE])
            ->when($from, fn ($q) => $q->where('e.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('e.entry_date', '<=', $to))
            ->groupBy('l.business_unit_id', 'bu.code', 'bu.name', 'a.type')
            ->get([
                'l.business_unit_id',
                'bu.code as bu_code',
                'bu.name as bu_name',
                'a.type',
                DB::raw('SUM(l.debit) as debit'),
                DB::raw('SUM(l.credit) as credit'),
            ]);

        $units = [];
        foreach ($rows as $r) {
            $key = $r->business_unit_id ?? 0;
            $units[$key] ??= [
                'business_unit_id' => $r->business_unit_id,
                'code' => $r->bu_code,
                'name' => $r->bu_name ?? 'Unallocated',
                'income' => 0.0,
                'expense' => 0.0,
            ];
            $debit = (float) $r->debit;
            $credit = (float) $r->credit;
            if ($r->type === Account::TYPE_INCOME) {
                $units[$key]['income'] += $credit - $debit;
            } else {
                $units[$key]['expense'] += $debit - $credit;
            }
        }

        $out = [];
        foreach ($units as $u) {
            $income = round($u['income'], 2);
            $expense = round($u['expense'], 2);
            $out[] = [
                'business_unit_id' => $u['business_unit_id'],
                'code' => $u['code'],
                'name' => $u['name'],
                'income' => $income,
                'expense' => $expense,
                'net' => round($income - $expense, 2),
            ];
        }

        // Allocated units first (by code), the unallocated bucket last.
        usort($out, fn ($a, $b) => [$a['business_unit_id'] === null ? 1 : 0, $a['code'] ?? '']
            <=> [$b['business_unit_id'] === null ? 1 : 0, $b['code'] ?? '']);

        return [
            'title' => 'Analytic P&L by business unit',
            'date_from' => $from,
            'date_to' => $to,
            'rows' => $out,
            'total_income' => round(array_sum(array_column($out, 'income')), 2),
            'total_expense' => round(array_sum(array_column($out, 'expense')), 2),
            'total_net' => round(array_sum(array_column($out, 'net')), 2),
        ];
    }
}
