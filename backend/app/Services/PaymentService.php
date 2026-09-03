<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Currency;
use App\Models\Installment;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentInstrument;
use App\Models\User;
use App\Support\AccountMap;
use Illuminate\Support\Facades\DB;

/**
 * Recording money actually moving: cash receipts, bank transfers, deposits,
 * withdrawals, advances and installment settlements.
 *
 * Cheques and traites are NOT settled here — they have their own lifecycle in
 * InstrumentService and only hit the treasury when they clear. Passing
 * method=cheque/traite therefore records the payment against an existing
 * instrument rather than posting a second time.
 */
class PaymentService
{
    /**
     * Cheques and traites are promises, not movements: their money is
     * recognised by InstrumentService when they clear, so a payment recorded
     * with one of these methods posts nothing and moves no balance here.
     */
    private static function isInstrumentMethod(string $method): bool
    {
        return in_array($method, [Payment::METHOD_CHEQUE, Payment::METHOD_TRAITE], true);
    }

    /** Treasury account for a payment: the bank account's own GL, or cash. */
    private static function treasuryCode(string $method, ?int $bankAccountId): string
    {
        if ($method === Payment::METHOD_CASH) {
            return AccountMap::code('cash');
        }

        $account = $bankAccountId ? BankAccount::find($bankAccountId) : null;

        return $account?->glAccount?->code ?? AccountMap::code('bank');
    }

    private static function journalFor(string $method): string
    {
        return match ($method) {
            Payment::METHOD_CASH => Journal::CASH,
            Payment::METHOD_CHEQUE => Journal::CHEQUE,
            Payment::METHOD_TRAITE => Journal::COMMERCIAL_PAPER,
            default => Journal::BANK,
        };
    }

    /**
     * Record a payment and post it.
     *
     * @param  array<string,mixed>  $data
     *         direction, method, amount, payment_date, customer_id|supplier_id,
     *         bank_account_id, installment_id, instrument_id, reference_type,
     *         reference_id, is_advance, reference, notes
     */
    public static function record(array $data, User $user): Payment
    {
        $decimals = (int) CompanyProfile::current()->currency_decimals;
        $amount = round((float) $data['amount'], $decimals);
        $direction = $data['direction'];
        $method = $data['method'];

        if ($amount <= 0) {
            throw new InvalidTransition('The payment amount must be greater than zero.');
        }
        if (in_array($method, [Payment::METHOD_TRANSFER, Payment::METHOD_CARD, Payment::METHOD_DEPOSIT, Payment::METHOD_WITHDRAWAL], true)
            && empty($data['bank_account_id'])) {
            throw new InvalidTransition('This payment method needs a bank account.');
        }

        return DB::transaction(function () use ($data, $user, $amount, $direction, $method, $decimals) {
            $installment = ! empty($data['installment_id'])
                ? Installment::with('plan')->find($data['installment_id'])
                : null;

            $entry = self::postFor($data, $user, $amount, $direction, $method);

            $payment = Payment::create([
                'number' => DocumentService::nextNumber('PAY', Payment::class),
                'direction' => $direction,
                'method' => $method,
                'amount' => $amount,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'customer_id' => $data['customer_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'instrument_id' => $data['instrument_id'] ?? null,
                'installment_id' => $installment?->id,
                'reference_type' => $data['reference_type'] ?? '',
                'reference_id' => $data['reference_id'] ?? null,
                'is_advance' => (bool) ($data['is_advance'] ?? false),
                'journal_entry_id' => $entry?->id,
                'reference' => $data['reference'] ?? '',
                'notes' => $data['notes'] ?? '',
                'created_by' => $user->id,
            ]);

            // Keep the schedule in step with the money — but only when the
            // money actually moved. A cheque or traite is a promise: the
            // instalment is credited when that instrument clears, by
            // InstallmentService::settleFromInstrument. Crediting here too
            // would count the same payment twice.
            if ($installment && ! self::isInstrumentMethod($method)) {
                InstallmentService::applyPayment(
                    $installment,
                    $amount,
                    $method,
                    $payment->payment_date?->toDateString(),
                );
            }

            self::touchBankBalance($payment, $decimals);

            return $payment->refresh();
        });
    }

    /**
     * The posting behind a payment.
     *
     * inbound  cash/transfer/card : Dr Treasury / Cr Receivable (or Customer advances)
     * outbound cash/transfer/card : Dr Payable (or Supplier advances) / Cr Treasury
     * bank_deposit                : Dr Bank / Cr Cash        (cash walked to the bank)
     * bank_withdrawal             : Dr Cash / Cr Bank
     * cheque/traite               : nothing — InstrumentService owns those postings
     */
    private static function postFor(
        array $data,
        User $user,
        float $amount,
        string $direction,
        string $method,
    ): ?JournalEntry {
        // The instrument's own lifecycle owns this money's postings.
        if (self::isInstrumentMethod($method)) {
            return null;
        }

        $date = $data['payment_date'] ?? null;
        $memo = $data['notes'] ?? '';
        $refType = $data['reference_type'] ?? 'manual';
        $refId = $data['reference_id'] ?? null;
        $isAdvance = (bool) ($data['is_advance'] ?? false);

        // Cash ↔ bank movements never touch a third party.
        if ($method === Payment::METHOD_DEPOSIT || $method === Payment::METHOD_WITHDRAWAL) {
            $bank = self::treasuryCode(Payment::METHOD_TRANSFER, $data['bank_account_id'] ?? null);
            $cash = AccountMap::code('cash');
            $toBank = $method === Payment::METHOD_DEPOSIT;

            return AccountingService::post(
                lines: [
                    ['account' => $toBank ? $bank : $cash, 'debit' => $amount, 'label' => $toBank ? 'Cash deposit' : 'Cash withdrawal'],
                    ['account' => $toBank ? $cash : $bank, 'credit' => $amount, 'label' => $toBank ? 'Cash deposit' : 'Cash withdrawal'],
                ],
                user: $user,
                memo: $memo !== '' ? $memo : ($toBank ? 'Cash deposited to bank' : 'Cash withdrawn from bank'),
                referenceType: 'payment',
                referenceId: null,
                date: $date,
                journalCode: Journal::BANK,
            );
        }

        $treasury = self::treasuryCode($method, $data['bank_account_id'] ?? null);
        $inbound = $direction === Payment::DIRECTION_IN;

        // An advance is money received before any invoice exists: it is a debt
        // to the customer, not a settled receivable.
        $counterKey = $inbound
            ? ($isAdvance ? 'customer_advances' : 'receivable')
            : ($isAdvance ? 'supplier_advances' : 'payable');

        $lines = $inbound
            ? [
                ['account' => $treasury, 'debit' => $amount, 'label' => 'Payment received'],
                ['account' => AccountMap::code($counterKey), 'credit' => $amount, 'label' => 'Payment received'],
            ]
            : [
                ['account' => AccountMap::code($counterKey), 'debit' => $amount, 'label' => 'Payment made'],
                ['account' => $treasury, 'credit' => $amount, 'label' => 'Payment made'],
            ];

        return AccountingService::post(
            lines: $lines,
            user: $user,
            memo: $memo !== '' ? $memo : ($inbound ? 'Payment received' : 'Payment made'),
            referenceType: $refType,
            referenceId: $refId,
            date: $date,
            journalCode: $isAdvance ? Journal::ADVANCE : self::journalFor($method),
        );
    }

    /**
     * Keep the bank account's cached balance in step. Like the product stock
     * cache, this is a convenience figure — the ledger stays the source of
     * truth and reconciliation is what proves the real balance.
     */
    private static function touchBankBalance(Payment $payment, int $decimals): void
    {
        // Nothing reached the bank yet for a cheque or traite.
        if (! $payment->bank_account_id || self::isInstrumentMethod($payment->method)) {
            return;
        }
        $account = BankAccount::find($payment->bank_account_id);
        if (! $account) {
            return;
        }

        $signed = match ($payment->method) {
            Payment::METHOD_DEPOSIT => (float) $payment->amount,
            Payment::METHOD_WITHDRAWAL => -(float) $payment->amount,
            default => $payment->direction === Payment::DIRECTION_IN
                ? (float) $payment->amount
                : -(float) $payment->amount,
        };

        $account->update([
            'current_balance' => round((float) $account->current_balance + $signed, $decimals),
        ]);
    }

    /**
     * Settle one installment, optionally through a cheque/traite.
     *
     * When an instrument is supplied the money is not in the bank yet — the
     * installment is only credited once that instrument clears, which is why
     * this returns the instrument rather than a payment in that case.
     */
    public static function settleInstallment(
        Installment $installment,
        float $amount,
        string $method,
        User $user,
        ?int $bankAccountId = null,
        ?int $instrumentId = null,
        ?string $date = null,
        string $reference = '',
    ): Payment {
        if ($installment->status === Installment::STATUS_CANCELLED) {
            throw new InvalidTransition('This installment was cancelled.');
        }

        $plan = $installment->plan;
        $inbound = $plan->reference_type === 'sale';

        // Link the instrument to this installment so clearing settles it.
        if ($instrumentId) {
            $instrument = PaymentInstrument::findOrFail($instrumentId);
            $instrument->update([
                'reference_type' => 'installment',
                'reference_id' => $installment->id,
            ]);
        }

        return self::record([
            'direction' => $inbound ? Payment::DIRECTION_IN : Payment::DIRECTION_OUT,
            'method' => $method,
            'amount' => $amount,
            'payment_date' => $date,
            'customer_id' => $plan->customer_id,
            'supplier_id' => $plan->supplier_id,
            'bank_account_id' => $bankAccountId,
            'instrument_id' => $instrumentId,
            'installment_id' => $installment->id,
            'reference_type' => $plan->reference_type,
            'reference_id' => $plan->reference_id,
            'reference' => $reference,
            'notes' => "Installment {$plan->number} #{$installment->sequence}",
        ], $user);
    }

    /**
     * Settle a foreign-currency receivable or payable and post the realized FX
     * gain/loss.
     *
     * The debt was booked to receivable/payable at `book_rate`; the treasury
     * moves the base value at `settlement_rate`. The gap is a realized gain or
     * loss (FxService). Additive to record(): base-currency money never comes
     * through here.
     *
     * @param  array<string,mixed>  $data direction, method, currency_code,
     *         foreign_amount, book_rate, settlement_rate, customer_id|supplier_id,
     *         bank_account_id, payment_date, reference, notes, reference_type, reference_id
     */
    public static function recordForeignSettlement(array $data, User $user): Payment
    {
        $decimals = (int) CompanyProfile::current()->currency_decimals;
        $direction = $data['direction'];
        $method = $data['method'];
        $code = strtoupper((string) ($data['currency_code'] ?? ''));
        $foreign = round((float) ($data['foreign_amount'] ?? 0), 2);
        $bookRate = (float) ($data['book_rate'] ?? 0);
        $settleRate = (float) ($data['settlement_rate'] ?? 0);

        if (! in_array($method, [Payment::METHOD_CASH, Payment::METHOD_TRANSFER, Payment::METHOD_CARD], true)) {
            throw new InvalidTransition('Foreign settlements use cash, bank transfer or card.');
        }
        $currency = Currency::find($code);
        if (! $currency) {
            throw new InvalidTransition("Unknown currency: {$code}.");
        }
        if ($currency->is_base) {
            throw new InvalidTransition('Use an ordinary payment for the base currency.');
        }
        if ($foreign <= 0) {
            throw new InvalidTransition('The foreign amount must be greater than zero.');
        }
        if ($bookRate <= 0 || $settleRate <= 0) {
            throw new InvalidTransition('Both the book rate and the settlement rate must be positive.');
        }

        $fx = FxService::realized($direction, $foreign, $bookRate, $settleRate, $decimals);
        $inbound = $direction === Payment::DIRECTION_IN;
        $treasury = self::treasuryCode($method, $data['bank_account_id'] ?? null);
        $counter = AccountMap::code($inbound ? 'receivable' : 'payable');

        return DB::transaction(function () use ($data, $user, $decimals, $direction, $method, $code, $foreign, $bookRate, $settleRate, $fx, $inbound, $treasury, $counter) {
            $baseSettled = round($fx['base_settled'], $decimals);
            $baseBooked = round($fx['base_booked'], $decimals);
            $gain = round($fx['gain'], $decimals);
            $memo = ($data['notes'] ?? '') !== '' ? $data['notes'] : ($inbound ? 'Foreign receipt' : 'Foreign payment');

            $lines = $inbound
                ? [
                    ['account' => $treasury, 'debit' => $baseSettled, 'label' => $memo],
                    ['account' => $counter, 'credit' => $baseBooked, 'label' => $memo],
                ]
                : [
                    ['account' => $counter, 'debit' => $baseBooked, 'label' => $memo],
                    ['account' => $treasury, 'credit' => $baseSettled, 'label' => $memo],
                ];

            // A gain leaves debits short of credits (or the reverse); the FX line
            // squares the entry. The direction-aware sign is handled in FxService,
            // so here a positive gain is always an extra credit.
            if ($gain > 0) {
                $lines[] = ['account' => AccountMap::code('fx_gain'), 'credit' => $gain, 'label' => 'Realized FX gain'];
            } elseif ($gain < 0) {
                $lines[] = ['account' => AccountMap::code('fx_loss'), 'debit' => -$gain, 'label' => 'Realized FX loss'];
            }

            $entry = AccountingService::post(
                lines: $lines,
                user: $user,
                memo: $memo,
                referenceType: $data['reference_type'] ?? 'manual',
                referenceId: $data['reference_id'] ?? null,
                date: $data['payment_date'] ?? null,
                journalCode: self::journalFor($method),
            );

            $payment = Payment::create([
                'number' => DocumentService::nextNumber('PAY', Payment::class),
                'direction' => $direction,
                'method' => $method,
                'amount' => $baseSettled,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'currency_code' => $code,
                'foreign_amount' => $foreign,
                'book_rate' => $bookRate,
                'settlement_rate' => $settleRate,
                'fx_gain_loss' => $gain,
                'customer_id' => $data['customer_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'reference_type' => $data['reference_type'] ?? '',
                'reference_id' => $data['reference_id'] ?? null,
                'journal_entry_id' => $entry->id,
                'reference' => $data['reference'] ?? '',
                'notes' => $data['notes'] ?? '',
                'created_by' => $user->id,
            ]);

            self::touchBankBalance($payment, $decimals);

            return $payment->refresh();
        });
    }

    /**
     * Pay a supplier net of withholding tax (retenue à la source).
     *
     * The payable is relieved in full; the treasury pays the net; the retenue
     * is credited to the withholding-tax payable, owed to the state. Additive
     * to record(): an ordinary payment never comes through here.
     *
     * @param  array<string,mixed>  $data direction is forced outbound; method,
     *         gross_amount, withholding_rate, supplier_id, bank_account_id,
     *         payment_date, reference, notes
     */
    public static function recordSupplierWithholding(array $data, User $user): Payment
    {
        $decimals = (int) CompanyProfile::current()->currency_decimals;
        $method = $data['method'];
        $gross = round((float) ($data['gross_amount'] ?? 0), $decimals);
        $rate = (float) ($data['withholding_rate'] ?? CompanyProfile::current()->withholding_rate);

        if (! in_array($method, [Payment::METHOD_CASH, Payment::METHOD_TRANSFER, Payment::METHOD_CARD], true)) {
            throw new InvalidTransition('Withholding applies to cash, bank transfer or card payments.');
        }
        if ($gross <= 0) {
            throw new InvalidTransition('The gross amount must be greater than zero.');
        }
        if ($rate <= 0 || $rate >= 100) {
            throw new InvalidTransition('The withholding rate must be between 0 and 100.');
        }

        $withheld = round($gross * $rate / 100, $decimals);
        $net = round($gross - $withheld, $decimals);
        $treasury = self::treasuryCode($method, $data['bank_account_id'] ?? null);

        return DB::transaction(function () use ($data, $user, $decimals, $method, $gross, $withheld, $net, $treasury) {
            $memo = ($data['notes'] ?? '') !== '' ? $data['notes'] : 'Supplier payment (withholding)';

            $entry = AccountingService::post(
                lines: [
                    ['account' => AccountMap::code('payable'), 'debit' => $gross, 'label' => $memo],
                    ['account' => $treasury, 'credit' => $net, 'label' => $memo],
                    ['account' => AccountMap::code('withholding_payable'), 'credit' => $withheld, 'label' => 'Retenue à la source'],
                ],
                user: $user,
                memo: $memo,
                referenceType: $data['reference_type'] ?? 'purchase',
                referenceId: $data['reference_id'] ?? null,
                date: $data['payment_date'] ?? null,
                journalCode: self::journalFor($method),
            );

            $payment = Payment::create([
                'number' => DocumentService::nextNumber('PAY', Payment::class),
                'direction' => Payment::DIRECTION_OUT,
                'method' => $method,
                'amount' => $net,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'withholding_amount' => $withheld,
                'supplier_id' => $data['supplier_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'reference_type' => $data['reference_type'] ?? '',
                'reference_id' => $data['reference_id'] ?? null,
                'journal_entry_id' => $entry->id,
                'reference' => $data['reference'] ?? '',
                'notes' => $data['notes'] ?? '',
                'created_by' => $user->id,
            ]);

            self::touchBankBalance($payment, $decimals);

            return $payment->refresh();
        });
    }

    /** Cash collected and bank collections over a period — dashboard figures. */
    public static function collectionSummary(?string $from = null, ?string $to = null): array
    {
        $base = Payment::where('direction', Payment::DIRECTION_IN)
            ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('payment_date', '<=', $to));

        $cash = (clone $base)->where('method', Payment::METHOD_CASH)->sum('amount');
        $bank = (clone $base)->whereIn('method', [
            Payment::METHOD_TRANSFER, Payment::METHOD_CARD, Payment::METHOD_DEPOSIT,
        ])->sum('amount');

        return [
            'cash_collected' => round((float) $cash, 3),
            'bank_collected' => round((float) $bank, 3),
            'total_collected' => round((float) $cash + (float) $bank, 3),
        ];
    }
}
