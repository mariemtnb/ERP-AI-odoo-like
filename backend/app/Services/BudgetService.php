<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Budget;

/**
 * Budget-vs-actual. The plan lives in budget lines; the actuals are the posted
 * ledger movement over the budget's own period, read from the trial balance —
 * so the comparison can never drift from the books.
 */
class BudgetService
{
    /**
     * Compare a budget against the ledger over its period.
     *
     * Variance is planned − actual. Whether that is favourable depends on the
     * account: for income, spending more than planned income is good (actual ≥
     * budget); for an expense, staying under is good (actual ≤ budget).
     */
    public static function vsActual(Budget $budget): array
    {
        $budget->loadMissing('lines.account');

        $actuals = [];
        foreach (AccountingService::trialBalance(
            $budget->period_start?->format('Y-m-d'),
            $budget->period_end?->format('Y-m-d'),
        )['rows'] as $row) {
            $actuals[$row['code']] = $row;
        }

        $rows = [];
        $totalBudget = 0.0;
        $totalActual = 0.0;

        foreach ($budget->lines as $line) {
            $code = $line->account_code;
            $type = $line->account?->type ?? ($actuals[$code]['type'] ?? Account::TYPE_EXPENSE);
            $planned = round((float) $line->amount, 3);
            $actual = round((float) ($actuals[$code]['balance'] ?? 0), 3);
            $variance = round($planned - $actual, 3);

            $isIncome = $type === Account::TYPE_INCOME;
            $favourable = $isIncome ? $actual >= $planned : $actual <= $planned;

            $rows[] = [
                'account_code' => $code,
                'account_name' => $line->account?->name,
                'account_type' => $type,
                'budget' => $planned,
                'actual' => $actual,
                'variance' => $variance,               // planned − actual
                'variance_pct' => $planned != 0.0 ? round($variance / $planned * 100, 1) : null,
                'favourable' => $favourable,
            ];
            $totalBudget += $planned;
            $totalActual += $actual;
        }

        return [
            'budget_id' => $budget->id,
            'name' => $budget->name,
            'period_start' => $budget->period_start?->format('Y-m-d'),
            'period_end' => $budget->period_end?->format('Y-m-d'),
            'rows' => $rows,
            'total_budget' => round($totalBudget, 3),
            'total_actual' => round($totalActual, 3),
            'total_variance' => round($totalBudget - $totalActual, 3),
        ];
    }
}
