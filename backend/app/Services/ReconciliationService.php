<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\CompanyProfile;
use App\Models\Installment;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\PaymentInstrument;
use App\Models\ReconciliationMatch;
use App\Models\User;
use App\Support\AccountMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bank reconciliation: import statement lines, match them against what the
 * ERP already knows, and report what is left.
 *
 * Matching never posts by itself — a match asserts "this bank line IS that
 * payment", and the payment was already posted when it was recorded. Only
 * explicit adjustments (bank fees, interest, unknown lines) post an entry,
 * which is what keeps reconciliation from double-counting.
 */
class ReconciliationService
{
    /** Amounts within this tolerance are treated as equal. */
    private const EPSILON = 0.005;

    /** Days either side of the bank date to consider a candidate. */
    private const DATE_WINDOW = 7;

    // ---------------- import ----------------

    /**
     * Parse a delimited statement into rows. Accepts the CSV exports Tunisian
     * banks produce (comma or semicolon, comma or dot decimals, either a
     * signed amount column or separate debit/credit columns).
     *
     * @return array<int, array<string,mixed>>
     */
    public static function parseCsv(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];
        if (count($lines) < 2) {
            throw new InvalidTransition('The file has no data rows.');
        }

        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(
            fn ($h) => strtolower(trim($h, " \t\"'")),
            str_getcsv($lines[0], $delimiter, '"', '\\')
        );

        // Column aliases — FR and EN headers, since statements come in both.
        $find = function (array $names) use ($header) {
            foreach ($names as $name) {
                $index = array_search($name, $header, true);
                if ($index !== false) {
                    return $index;
                }
            }

            return null;
        };

        $cols = [
            'date' => $find(['date', 'date operation', "date d'operation", 'operation_date', 'date_operation']),
            'value_date' => $find(['date valeur', 'value date', 'value_date', 'date_valeur']),
            'label' => $find(['libelle', 'libellé', 'label', 'description', 'designation']),
            'reference' => $find(['reference', 'référence', 'ref', 'piece', 'numero']),
            'amount' => $find(['montant', 'amount', 'mouvement']),
            'debit' => $find(['debit', 'débit']),
            'credit' => $find(['credit', 'crédit']),
            'balance' => $find(['solde', 'balance', 'running_balance']),
        ];

        if ($cols['date'] === null) {
            throw new InvalidTransition('No date column found. Expected one of: date, date operation, operation_date.');
        }
        if ($cols['amount'] === null && $cols['debit'] === null && $cols['credit'] === null) {
            throw new InvalidTransition('No amount column found. Expected "montant"/"amount", or "debit" and "credit".');
        }

        $rows = [];
        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $delimiter, '"', '\\');
            $get = fn (?int $i) => $i !== null && isset($cells[$i]) ? trim($cells[$i]) : '';

            $date = self::parseDate($get($cols['date']));
            if (! $date) {
                continue;   // skip totals/footer rows rather than failing the import
            }

            if ($cols['amount'] !== null && $get($cols['amount']) !== '') {
                $amount = self::parseAmount($get($cols['amount']));
            } else {
                $debit = self::parseAmount($get($cols['debit']));
                $credit = self::parseAmount($get($cols['credit']));
                $amount = $credit - abs($debit);
            }
            if (abs($amount) < self::EPSILON) {
                continue;
            }

            $rows[] = [
                'operation_date' => $date,
                'value_date' => self::parseDate($get($cols['value_date'])),
                'label' => mb_substr($get($cols['label']), 0, 255),
                'reference' => mb_substr($get($cols['reference']), 0, 80),
                'amount' => $amount,
                'running_balance' => $cols['balance'] !== null && $get($cols['balance']) !== ''
                    ? self::parseAmount($get($cols['balance']))
                    : null,
            ];
        }

        if (! $rows) {
            throw new InvalidTransition('No usable rows found in the file.');
        }

        return $rows;
    }

    /** Tunisian statements commonly use d/m/Y; ISO is accepted too. */
    private static function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d', 'Y/m/d', 'd/m/y'] as $format) {
            $date = Carbon::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** "1 234,500" / "1,234.500" / "(120,00)" → float. */
    private static function parseAmount(string $value): float
    {
        $value = trim($value);
        if ($value === '') {
            return 0.0;
        }

        $negative = str_starts_with($value, '(') && str_ends_with($value, ')');
        $value = trim($value, '()');
        $value = preg_replace('/[^\d,.\-]/u', '', $value) ?? '';

        // Whichever separator comes last is the decimal one.
        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');
        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        $amount = (float) $value;

        return $negative ? -abs($amount) : $amount;
    }

    /**
     * Store parsed rows. A line identical to one from an EARLIER import is
     * skipped, so re-importing an overlapping statement is safe.
     *
     * The dedupe deliberately ignores rows from the current batch: a statement
     * can legitimately carry two identical lines (two equal cash deposits on
     * the same day with no reference), and dropping the second would lose real
     * money from the reconciliation.
     *
     * @param  array<int, array<string,mixed>>  $rows
     * @return array{imported:int, skipped:int, batch:string}
     */
    public static function import(
        BankAccount $account,
        array $rows,
        User $user,
        string $source = 'csv',
    ): array {
        $batch = strtoupper(substr(md5(uniqid('', true)), 0, 12));
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($account, $rows, $user, $source, $batch, &$imported, &$skipped) {
            foreach ($rows as $row) {
                $exists = BankTransaction::where('bank_account_id', $account->id)
                    ->where('import_batch', '!=', $batch)
                    ->whereDate('operation_date', $row['operation_date'])
                    ->where('amount', round((float) $row['amount'], 3))
                    ->where('reference', $row['reference'] ?? '')
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                BankTransaction::create([
                    'bank_account_id' => $account->id,
                    'operation_date' => $row['operation_date'],
                    'value_date' => $row['value_date'] ?? null,
                    'label' => $row['label'] ?? '',
                    'reference' => $row['reference'] ?? '',
                    'amount' => round((float) $row['amount'], 3),
                    'running_balance' => isset($row['running_balance'])
                        ? round((float) $row['running_balance'], 3)
                        : null,
                    'import_batch' => $batch,
                    'source' => $source,
                    'created_by' => $user->id,
                ]);
                $imported++;
            }
        });

        return ['imported' => $imported, 'skipped' => $skipped, 'batch' => $batch];
    }

    // ---------------- matching ----------------

    /**
     * Candidate ERP objects for a bank line, best first.
     *
     * Scoring is deliberately simple and explainable — exact amount, then
     * date proximity, then a reference/name hit. The user (or the AI agent)
     * confirms; nothing auto-matches behind their back.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function suggestions(BankTransaction $tx, int $limit = 8): array
    {
        $amount = abs((float) $tx->amount);
        $isCredit = $tx->isCredit();
        $from = $tx->operation_date->copy()->subDays(self::DATE_WINDOW)->toDateString();
        $to = $tx->operation_date->copy()->addDays(self::DATE_WINDOW)->toDateString();
        $haystack = mb_strtolower($tx->label . ' ' . $tx->reference);

        $candidates = [];

        $score = function (float $candidateAmount, ?string $date, string $text) use ($amount, $tx, $haystack) {
            $score = 0;
            if (abs($candidateAmount - $amount) < self::EPSILON) {
                $score += 60;
            } elseif ($amount > 0 && abs($candidateAmount - $amount) / $amount < 0.02) {
                $score += 30;
            }
            if ($date) {
                $days = abs(Carbon::parse($date)->diffInDays($tx->operation_date));
                $score += max(0, 25 - $days * 3);
            }
            $text = mb_strtolower(trim($text));
            if ($text !== '' && $haystack !== '' && str_contains($haystack, $text)) {
                $score += 15;
            }

            return $score;
        };

        // Payments recorded but not yet reconciled.
        $payments = Payment::with(['customer', 'supplier'])
            ->where('direction', $isCredit ? Payment::DIRECTION_IN : Payment::DIRECTION_OUT)
            ->whereBetween('payment_date', [$from, $to])
            ->whereNotIn('id', self::matchedIds(ReconciliationMatch::TYPE_PAYMENT))
            ->limit(50)
            ->get();

        foreach ($payments as $payment) {
            $candidates[] = [
                'type' => ReconciliationMatch::TYPE_PAYMENT,
                'id' => $payment->id,
                'label' => "{$payment->number} — " . ($payment->customer?->name ?? $payment->supplier?->name ?? $payment->method),
                'amount' => (string) $payment->amount,
                'date' => $payment->payment_date?->format('Y-m-d'),
                'score' => $score(
                    (float) $payment->amount,
                    $payment->payment_date?->toDateString(),
                    $payment->reference ?: ($payment->customer?->name ?? '')
                ),
            ];
        }

        // Instruments awaiting clearance — the classic Tunisian bank line.
        $instruments = PaymentInstrument::with(['customer', 'supplier'])
            ->whereIn('status', [
                PaymentInstrument::STATUS_DEPOSITED,
                PaymentInstrument::STATUS_PENDING,
                PaymentInstrument::STATUS_ISSUED,
            ])
            ->where('direction', $isCredit ? PaymentInstrument::DIRECTION_IN : PaymentInstrument::DIRECTION_OUT)
            ->whereNotIn('id', self::matchedIds(ReconciliationMatch::TYPE_INSTRUMENT))
            ->limit(50)
            ->get();

        foreach ($instruments as $instrument) {
            $candidates[] = [
                'type' => ReconciliationMatch::TYPE_INSTRUMENT,
                'id' => $instrument->id,
                'label' => "{$instrument->number} ({$instrument->kind} {$instrument->instrument_reference}) — {$instrument->counterpartyLabel()}",
                'amount' => (string) $instrument->amount,
                'date' => $instrument->due_date?->format('Y-m-d'),
                'score' => $score(
                    (float) $instrument->amount,
                    ($instrument->deposited_at ?? $instrument->due_date)?->toDateString(),
                    $instrument->instrument_reference
                ) + 10,   // a deposited instrument is the likeliest explanation
            ];
        }

        // Installments due around this date.
        $installments = Installment::with('plan.customer')
            ->whereNotIn('status', [Installment::STATUS_PAID, Installment::STATUS_CANCELLED])
            ->whereBetween('due_date', [$from, $to])
            ->whereNotIn('id', self::matchedIds(ReconciliationMatch::TYPE_INSTALLMENT))
            ->limit(50)
            ->get();

        foreach ($installments as $installment) {
            $candidates[] = [
                'type' => ReconciliationMatch::TYPE_INSTALLMENT,
                'id' => $installment->id,
                'label' => "{$installment->plan?->number} #{$installment->sequence} — " . ($installment->plan?->customer?->name ?? ''),
                'amount' => number_format($installment->remainingAmount(), 3, '.', ''),
                'date' => $installment->due_date?->format('Y-m-d'),
                'score' => $score(
                    $installment->remainingAmount(),
                    $installment->due_date?->toDateString(),
                    $installment->plan?->customer?->name ?? ''
                ),
            ];
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice(array_filter($candidates, fn ($c) => $c['score'] > 0), 0, $limit);
    }

    /** @return array<int,int> ids already fully matched, to keep them out of suggestions */
    private static function matchedIds(string $type): array
    {
        return ReconciliationMatch::where('matchable_type', $type)
            ->pluck('matchable_id')
            ->filter()
            ->all();
    }

    /**
     * Assert that a bank line corresponds to an ERP object.
     *
     * The optional side effects are the useful part: matching a deposited
     * cheque to its credit line is exactly the moment it cleared, so we offer
     * to run that transition rather than making the user do it twice.
     */
    public static function match(
        BankTransaction $tx,
        string $type,
        ?int $id,
        float $amount,
        User $user,
        string $note = '',
        bool $applySideEffects = true,
    ): ReconciliationMatch {
        $amount = round($amount, 3);
        if ($amount <= 0) {
            throw new InvalidTransition('The matched amount must be greater than zero.');
        }
        if ($amount - $tx->remainingAmount() > self::EPSILON) {
            throw new InvalidTransition(sprintf(
                'Only %s is left unmatched on this line.',
                number_format($tx->remainingAmount(), 3, '.', '')
            ));
        }

        return DB::transaction(function () use ($tx, $type, $id, $amount, $user, $note, $applySideEffects) {
            $entry = null;

            if ($applySideEffects) {
                if ($type === ReconciliationMatch::TYPE_INSTRUMENT && $id) {
                    $instrument = PaymentInstrument::find($id);
                    // The bank credited/debited it: that is what "cleared" means.
                    if ($instrument && in_array($instrument->status, [
                        PaymentInstrument::STATUS_DEPOSITED,
                        PaymentInstrument::STATUS_PENDING,
                        PaymentInstrument::STATUS_ISSUED,
                    ], true)) {
                        InstrumentService::clear(
                            $instrument,
                            $user,
                            $tx->value_date?->toDateString() ?? $tx->operation_date->toDateString(),
                        );
                    }
                }

                if ($type === ReconciliationMatch::TYPE_ADJUSTMENT) {
                    $entry = self::postAdjustment($tx, $amount, $user, $note);
                }
            }

            $match = ReconciliationMatch::create([
                'bank_transaction_id' => $tx->id,
                'matchable_type' => $type,
                'matchable_id' => $id,
                'amount' => $amount,
                'journal_entry_id' => $entry?->id,
                'note' => $note,
                'created_by' => $user->id,
                'created_at' => now(),
            ]);

            self::refreshStatus($tx);

            return $match;
        });
    }

    /**
     * A line with no counterpart in the ERP — bank charges, interest, an
     * unidentified transfer. Posts against the mapped fees account for
     * outflows and the suspense account otherwise, so the books still balance
     * and the accountant can reclassify later.
     */
    private static function postAdjustment(BankTransaction $tx, float $amount, User $user, string $note)
    {
        $bank = $tx->bankAccount?->glAccount?->code ?? AccountMap::code('bank');
        $isCredit = $tx->isCredit();
        $counter = $isCredit ? AccountMap::code('suspense') : AccountMap::code('bank_fees');

        return AccountingService::post(
            lines: $isCredit
                ? [
                    ['account' => $bank, 'debit' => $amount, 'label' => $note ?: $tx->label],
                    ['account' => $counter, 'credit' => $amount, 'label' => $note ?: $tx->label],
                ]
                : [
                    ['account' => $counter, 'debit' => $amount, 'label' => $note ?: $tx->label],
                    ['account' => $bank, 'credit' => $amount, 'label' => $note ?: $tx->label],
                ],
            user: $user,
            memo: 'Reconciliation adjustment: ' . ($note ?: $tx->label),
            referenceType: 'reconciliation',
            referenceId: $tx->id,
            date: $tx->operation_date->toDateString(),
            journalCode: Journal::BANK,
        );
    }

    public static function unmatch(ReconciliationMatch $match, User $user): BankTransaction
    {
        return DB::transaction(function () use ($match) {
            $tx = $match->bankTransaction;
            // The posting a match produced stays: the ledger is append-only.
            // Removing the assertion is enough to reopen the line.
            $match->delete();
            self::refreshStatus($tx);

            return $tx->refresh();
        });
    }

    public static function dispute(BankTransaction $tx, string $reason, User $user): BankTransaction
    {
        $tx->update([
            'status' => BankTransaction::STATUS_DISPUTED,
            'notes' => trim($tx->notes . "\nDisputed: {$reason}"),
        ]);

        return $tx->refresh();
    }

    /** Recompute matched totals and the resulting status. */
    public static function refreshStatus(BankTransaction $tx): BankTransaction
    {
        $matched = round((float) $tx->matches()->sum('amount'), 3);
        $total = abs((float) $tx->amount);

        $status = match (true) {
            $matched < self::EPSILON => BankTransaction::STATUS_UNMATCHED,
            abs($matched - $total) < self::EPSILON => BankTransaction::STATUS_MATCHED,
            default => BankTransaction::STATUS_PARTIAL,
        };

        // A disputed line stays disputed until it is explicitly matched in full.
        if ($tx->status === BankTransaction::STATUS_DISPUTED && $status !== BankTransaction::STATUS_MATCHED) {
            $status = BankTransaction::STATUS_DISPUTED;
        }

        $tx->update(['matched_amount' => $matched, 'status' => $status]);

        return $tx->refresh();
    }

    // ---------------- report ----------------

    /**
     * Reconciliation statement for one bank account: the ledger balance, the
     * statement balance, and the items explaining the gap.
     */
    public static function report(BankAccount $account, ?string $from = null, ?string $to = null): array
    {
        $decimals = (int) CompanyProfile::current()->currency_decimals;

        $transactions = BankTransaction::where('bank_account_id', $account->id)
            ->when($from, fn ($q) => $q->whereDate('operation_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('operation_date', '<=', $to))
            ->orderBy('operation_date')
            ->orderBy('id')
            ->get();

        $group = fn (string $status) => $transactions->where('status', $status);

        $statementTotal = round((float) $transactions->sum('amount'), $decimals);
        $unmatched = $group(BankTransaction::STATUS_UNMATCHED);
        $partial = $group(BankTransaction::STATUS_PARTIAL);
        $disputed = $group(BankTransaction::STATUS_DISPUTED);
        $matched = $group(BankTransaction::STATUS_MATCHED);

        // Instruments deposited but not yet on any statement line.
        $inTransit = PaymentInstrument::whereIn('status', [
            PaymentInstrument::STATUS_DEPOSITED, PaymentInstrument::STATUS_PENDING,
        ])->where('bank_account_id', $account->id)->get();

        $ledgerBalance = round(
            (float) $account->opening_balance + $statementTotal,
            $decimals
        );

        return [
            'title' => 'Bank reconciliation',
            'bank_account' => $account->toApi(),
            'date_from' => $from,
            'date_to' => $to,
            'opening_balance' => (string) $account->opening_balance,
            'statement_movement' => number_format($statementTotal, $decimals, '.', ''),
            'statement_balance' => number_format($ledgerBalance, $decimals, '.', ''),
            'book_balance' => (string) $account->current_balance,
            'difference' => number_format(
                round((float) $account->current_balance - $ledgerBalance, $decimals),
                $decimals, '.', ''
            ),
            'counts' => [
                'total' => $transactions->count(),
                'matched' => $matched->count(),
                'partially_matched' => $partial->count(),
                'unmatched' => $unmatched->count(),
                'disputed' => $disputed->count(),
            ],
            'amounts' => [
                'matched' => round((float) $matched->sum('matched_amount'), $decimals),
                'unmatched' => round($unmatched->sum(fn ($t) => abs((float) $t->amount)), $decimals),
                'partially_matched' => round($partial->sum(fn ($t) => $t->remainingAmount()), $decimals),
                'disputed' => round($disputed->sum(fn ($t) => abs((float) $t->amount)), $decimals),
            ],
            'instruments_in_transit' => [
                'count' => $inTransit->count(),
                'amount' => round((float) $inTransit->sum('amount'), $decimals),
                'items' => $inTransit->map(fn (PaymentInstrument $i) => [
                    'number' => $i->number,
                    'kind' => $i->kind,
                    'amount' => (string) $i->amount,
                    'due_date' => $i->due_date?->format('Y-m-d'),
                    'status' => $i->status,
                ])->values()->all(),
            ],
            'open_items' => $unmatched->concat($partial)->concat($disputed)
                ->map(fn (BankTransaction $t) => $t->toApi())->values()->all(),
        ];
    }

    /** Cross-account figures for the dashboard. */
    public static function pendingSummary(): array
    {
        $open = BankTransaction::whereIn('status', [
            BankTransaction::STATUS_UNMATCHED,
            BankTransaction::STATUS_PARTIAL,
            BankTransaction::STATUS_DISPUTED,
        ])->get();

        return [
            'pending_count' => $open->count(),
            'pending_amount' => round($open->sum(fn ($t) => $t->remainingAmount()), 3),
            'disputed_count' => $open->where('status', BankTransaction::STATUS_DISPUTED)->count(),
        ];
    }
}
