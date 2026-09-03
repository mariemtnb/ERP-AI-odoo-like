<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\AccountMap;
use Illuminate\Support\Facades\DB;

/**
 * Closing a fiscal year. Posts a closing journal that zeroes every income and
 * expense account for the year and rolls the net result into retained earnings,
 * then marks the year closed so nothing can be backdated into it. Balance-sheet
 * accounts carry forward on their own (the ledger is continuous), so only the
 * profit-and-loss needs an entry.
 */
class FiscalYearService
{
    public static function close(FiscalYear $year, User $user): FiscalYear
    {
        if ($year->status !== FiscalYear::OPEN) {
            throw new InvalidTransition('Only an open fiscal year can be closed.');
        }
        if ($year->closing_entry_id) {
            throw new InvalidTransition('This year already has a closing entry.');
        }

        $from = $year->starts_on->format('Y-m-d');
        $to = $year->ends_on->format('Y-m-d');
        $rows = AccountingService::trialBalance($from, $to)['rows'];

        $lines = [];
        $income = 0.0;
        $expense = 0.0;
        foreach ($rows as $r) {
            $balance = round((float) $r['balance'], 2);
            if ($balance == 0.0) {
                continue;
            }
            if ($r['type'] === Account::TYPE_INCOME) {
                // Income is credit-normal; debit it to bring it to zero.
                $lines[] = ['account' => $r['code'], 'debit' => $balance, 'label' => 'Year-end close'];
                $income += $balance;
            } elseif ($r['type'] === Account::TYPE_EXPENSE) {
                $lines[] = ['account' => $r['code'], 'credit' => $balance, 'label' => 'Year-end close'];
                $expense += $balance;
            }
        }

        $result = round($income - $expense, 2);

        return DB::transaction(function () use ($year, $user, $lines, $result, $to) {
            $entry = null;
            if (! empty($lines) || $result != 0.0) {
                // Plug the net result into retained earnings so the entry balances.
                if ($result > 0) {
                    $lines[] = ['account' => AccountMap::code('retained_earnings'), 'credit' => $result, 'label' => 'Result for the year'];
                } elseif ($result < 0) {
                    $lines[] = ['account' => AccountMap::code('retained_earnings'), 'debit' => -$result, 'label' => 'Result for the year'];
                }

                if (count($lines) >= 2) {
                    $entry = AccountingService::post(
                        lines: $lines,
                        user: $user,
                        memo: "Closing entry {$year->name}",
                        referenceType: 'fiscal_close',
                        referenceId: $year->id,
                        date: $to,
                        journalCode: Journal::MISC,
                    );
                }
            }

            $year->update([
                'status' => FiscalYear::CLOSED,
                'closed_at' => now(),
                'closed_by' => $user->id,
                'closing_entry_id' => $entry?->id,
            ]);

            return $year->refresh();
        });
    }
}
