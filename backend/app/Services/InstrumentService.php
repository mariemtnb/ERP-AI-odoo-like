<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\Attachment;
use App\Models\BankAccount;
use App\Models\InstrumentEvent;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\PaymentInstrument;
use App\Models\User;
use App\Support\AccountMap;
use Illuminate\Support\Facades\DB;

/**
 * Lifecycle of cheques and effets de commerce (traites / kembyelet).
 *
 * State machine, one place. Every transition posts its own balanced journal
 * entry and appends an immutable InstrumentEvent, so the books and the
 * instrument's history can never drift apart — the same discipline the stock
 * ledger uses.
 *
 * Accounts come from AccountMap (semantic keys), never literal codes.
 */
class InstrumentService
{
    /**
     * Allowed transitions per direction. Anything not listed is refused.
     *
     * incoming: we received the instrument from a customer.
     * outgoing: we issued it to a supplier.
     *
     * @var array<string, array<string, string[]>>
     */
    public const TRANSITIONS = [
        PaymentInstrument::DIRECTION_IN => [
            PaymentInstrument::STATUS_DRAFT => [PaymentInstrument::STATUS_RECEIVED, PaymentInstrument::STATUS_CANCELLED],
            PaymentInstrument::STATUS_RECEIVED => [PaymentInstrument::STATUS_DEPOSITED, PaymentInstrument::STATUS_CANCELLED],
            PaymentInstrument::STATUS_DEPOSITED => [PaymentInstrument::STATUS_PENDING, PaymentInstrument::STATUS_CLEARED, PaymentInstrument::STATUS_BOUNCED],
            PaymentInstrument::STATUS_PENDING => [PaymentInstrument::STATUS_CLEARED, PaymentInstrument::STATUS_BOUNCED],
            PaymentInstrument::STATUS_BOUNCED => [PaymentInstrument::STATUS_DEPOSITED, PaymentInstrument::STATUS_SETTLED],
            PaymentInstrument::STATUS_CLEARED => [],
            PaymentInstrument::STATUS_SETTLED => [],
            PaymentInstrument::STATUS_CANCELLED => [],
        ],
        PaymentInstrument::DIRECTION_OUT => [
            PaymentInstrument::STATUS_DRAFT => [PaymentInstrument::STATUS_ISSUED, PaymentInstrument::STATUS_CANCELLED],
            PaymentInstrument::STATUS_ISSUED => [PaymentInstrument::STATUS_CLEARED, PaymentInstrument::STATUS_BOUNCED, PaymentInstrument::STATUS_CANCELLED],
            PaymentInstrument::STATUS_BOUNCED => [PaymentInstrument::STATUS_ISSUED, PaymentInstrument::STATUS_SETTLED],
            PaymentInstrument::STATUS_CLEARED => [],
            PaymentInstrument::STATUS_SETTLED => [],
            PaymentInstrument::STATUS_CANCELLED => [],
        ],
    ];

    /**
     * Account-mapping key for the instrument's "in our hands / owed" account.
     * Cheques and traites live in different accounts by Tunisian practice —
     * which account exactly is configurable, this only picks the key.
     */
    public static function accountKey(PaymentInstrument $i, string $stage = 'holding'): string
    {
        $cheque = $i->kind === PaymentInstrument::KIND_CHEQUE;

        if ($i->isIncoming()) {
            return match ($stage) {
                'collection' => $cheque ? 'cheques_in_collection' : 'notes_in_collection',
                default => $cheque ? 'cheques_receivable' : 'notes_receivable',
            };
        }

        return $cheque ? 'cheques_payable' : 'notes_payable';
    }

    private static function journalCode(PaymentInstrument $i): string
    {
        return $i->kind === PaymentInstrument::KIND_CHEQUE
            ? Journal::CHEQUE
            : Journal::COMMERCIAL_PAPER;
    }

    /** Counterparty control account: customers are receivables, suppliers payables. */
    private static function partyKey(PaymentInstrument $i): string
    {
        return $i->isIncoming() ? 'receivable' : 'payable';
    }

    /** GL account of the bank the instrument settles through. */
    private static function bankCode(PaymentInstrument $i): string
    {
        $account = $i->bank_account_id ? BankAccount::find($i->bank_account_id) : null;

        return $account?->glAccount?->code ?? AccountMap::code('bank');
    }

    private static function assertTransition(PaymentInstrument $i, string $to): void
    {
        $allowed = self::TRANSITIONS[$i->direction][$i->status] ?? [];
        if (! in_array($to, $allowed, true)) {
            throw new InvalidTransition(
                "Cannot move a {$i->kind} from '{$i->status}' to '{$to}'."
                . ($allowed ? ' Allowed: ' . implode(', ', $allowed) . '.' : ' It is in a final state.')
            );
        }
    }

    private static function record(
        PaymentInstrument $i,
        string $event,
        string $from,
        string $to,
        User $user,
        ?JournalEntry $entry = null,
        string $notes = '',
        ?float $amount = null,
    ): InstrumentEvent {
        return InstrumentEvent::create([
            'instrument_id' => $i->id,
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'amount' => $amount ?? $i->amount,
            'journal_entry_id' => $entry?->id,
            'notes' => $notes,
            'created_by' => $user->id,
            'created_at' => now(),
        ]);
    }

    // ---------------- creation ----------------

    /**
     * Register an instrument. An incoming one recognised against a sale posts
     * immediately (the receivable is replaced by a cheque in hand); a draft
     * posts nothing until it is received/issued.
     *
     * @param  array<string,mixed>  $data
     */
    public static function create(array $data, User $user): PaymentInstrument
    {
        // Not a column: it decides whether we stop at draft or recognise the
        // instrument straight away.
        $autoActivate = (bool) ($data['auto_activate'] ?? true);
        unset($data['auto_activate']);

        return DB::transaction(function () use ($data, $user, $autoActivate) {
            $prefix = $data['kind'] === PaymentInstrument::KIND_CHEQUE ? 'CHQ' : 'EFF';

            $instrument = PaymentInstrument::create($data + [
                'number' => DocumentService::nextNumber($prefix, PaymentInstrument::class),
                'created_by' => $user->id,
                'status' => PaymentInstrument::STATUS_DRAFT,
            ]);

            self::record($instrument, 'created', '', PaymentInstrument::STATUS_DRAFT, $user);

            // Callers that already know the instrument is in hand (received
            // from a customer / handed to a supplier) skip the draft state.
            if ($autoActivate) {
                $instrument = $instrument->isIncoming()
                    ? self::receive($instrument, $user)
                    : self::issue($instrument, $user);
            }

            return $instrument;
        });
    }

    // ---------------- incoming ----------------

    /**
     * Cheque/traite received from a customer against an invoice:
     *   Dr Cheques (or Notes) receivable / Cr Accounts receivable
     * The debt does not disappear — it changes form. That is precisely why a
     * bounce can put it back.
     */
    public static function receive(PaymentInstrument $i, User $user): PaymentInstrument
    {
        self::assertTransition($i, PaymentInstrument::STATUS_RECEIVED);
        $from = $i->status;

        return DB::transaction(function () use ($i, $user, $from) {
            $entry = AccountingService::post(
                lines: [
                    ['account' => AccountMap::code(self::accountKey($i)), 'debit' => $i->amount, 'label' => "{$i->kind} {$i->number}"],
                    ['account' => AccountMap::code(self::partyKey($i)), 'credit' => $i->amount, 'label' => $i->counterpartyLabel()],
                ],
                user: $user,
                memo: "Received {$i->kind} {$i->instrument_reference} from {$i->counterpartyLabel()}",
                referenceType: 'instrument',
                referenceId: $i->id,
                date: $i->issue_date?->toDateString(),
                journalCode: self::journalCode($i),
            );

            $i->update(['status' => PaymentInstrument::STATUS_RECEIVED]);
            self::record($i, 'received', $from, PaymentInstrument::STATUS_RECEIVED, $user, $entry);

            return $i->refresh();
        });
    }

    /**
     * Handed to the bank for collection (remise à l'encaissement):
     *   Dr Instruments in collection / Cr Instruments in hand
     */
    public static function deposit(
        PaymentInstrument $i,
        User $user,
        ?int $bankAccountId = null,
        ?string $date = null,
    ): PaymentInstrument {
        self::assertTransition($i, PaymentInstrument::STATUS_DEPOSITED);
        $from = $i->status;

        return DB::transaction(function () use ($i, $user, $bankAccountId, $date, $from) {
            if ($bankAccountId) {
                $i->update(['bank_account_id' => $bankAccountId]);
            }
            if (! $i->bank_account_id) {
                throw new InvalidTransition('Choose the bank account to deposit into.');
            }

            $entry = AccountingService::post(
                lines: [
                    ['account' => AccountMap::code(self::accountKey($i, 'collection')), 'debit' => $i->amount, 'label' => "Deposit {$i->number}"],
                    ['account' => AccountMap::code(self::accountKey($i)), 'credit' => $i->amount, 'label' => "Deposit {$i->number}"],
                ],
                user: $user,
                memo: "Deposited {$i->kind} {$i->instrument_reference} for collection",
                referenceType: 'instrument',
                referenceId: $i->id,
                date: $date,
                journalCode: self::journalCode($i),
            );

            $i->update([
                'status' => PaymentInstrument::STATUS_DEPOSITED,
                'deposited_at' => $date ?? now()->toDateString(),
            ]);
            self::record($i, 'deposited', $from, PaymentInstrument::STATUS_DEPOSITED, $user, $entry);

            return $i->refresh();
        });
    }

    /** Bank acknowledged the deposit but the funds are not available yet. */
    public static function markPendingClearance(PaymentInstrument $i, User $user): PaymentInstrument
    {
        self::assertTransition($i, PaymentInstrument::STATUS_PENDING);
        $from = $i->status;
        $i->update(['status' => PaymentInstrument::STATUS_PENDING]);
        self::record($i, 'pending_clearance', $from, PaymentInstrument::STATUS_PENDING, $user);

        return $i->refresh();
    }

    /**
     * Funds credited (incoming) or debited (outgoing).
     *
     *   incoming: Dr Bank / Cr Instruments in collection
     *   outgoing: Dr Instruments payable / Cr Bank
     *
     * Bank charges, when present, are expensed to the mapped fees account.
     */
    public static function clear(
        PaymentInstrument $i,
        User $user,
        ?string $date = null,
        float $fees = 0,
    ): PaymentInstrument {
        self::assertTransition($i, PaymentInstrument::STATUS_CLEARED);
        $from = $i->status;

        return DB::transaction(function () use ($i, $user, $date, $fees, $from) {
            $amount = (float) $i->amount;
            $fees = round($fees, 3);
            $bank = self::bankCode($i);

            if ($i->isIncoming()) {
                // Money in, minus whatever the bank kept.
                $lines = [
                    ['account' => $bank, 'debit' => round($amount - $fees, 3), 'label' => "Collection {$i->number}"],
                ];
                if ($fees > 0) {
                    $lines[] = ['account' => AccountMap::code('bank_fees'), 'debit' => $fees, 'label' => "Bank fees {$i->number}"];
                }
                $lines[] = ['account' => AccountMap::code(self::accountKey($i, 'collection')), 'credit' => $amount, 'label' => "Collection {$i->number}"];
            } else {
                $lines = [
                    ['account' => AccountMap::code(self::accountKey($i)), 'debit' => $amount, 'label' => "Payment {$i->number}"],
                ];
                if ($fees > 0) {
                    $lines[] = ['account' => AccountMap::code('bank_fees'), 'debit' => $fees, 'label' => "Bank fees {$i->number}"];
                }
                $lines[] = ['account' => $bank, 'credit' => round($amount + $fees, 3), 'label' => "Payment {$i->number}"];
            }

            $entry = AccountingService::post(
                lines: $lines,
                user: $user,
                memo: "Cleared {$i->kind} {$i->instrument_reference}",
                referenceType: 'instrument',
                referenceId: $i->id,
                date: $date,
                journalCode: self::journalCode($i),
            );

            $i->update([
                'status' => PaymentInstrument::STATUS_CLEARED,
                'cleared_at' => $date ?? now()->toDateString(),
                'bank_fees' => $fees,
            ]);
            self::record($i, 'cleared', $from, PaymentInstrument::STATUS_CLEARED, $user, $entry);

            // A cleared instrument settles whatever installment it was raised for.
            InstallmentService::settleFromInstrument($i, $user);

            return $i->refresh();
        });
    }

    /**
     * Bounced / returned unpaid (chèque sans provision, effet impayé).
     *
     * Posts the exact reversal of everything recognised so far, putting the
     * debt back on the counterparty — optionally onto the doubtful-receivable
     * account — and expenses any return fee. History is never rewritten.
     */
    public static function bounce(
        PaymentInstrument $i,
        User $user,
        string $reason = '',
        float $fees = 0,
        ?string $date = null,
        bool $moveToDoubtful = false,
    ): PaymentInstrument {
        self::assertTransition($i, PaymentInstrument::STATUS_BOUNCED);
        $from = $i->status;

        return DB::transaction(function () use ($i, $user, $reason, $fees, $date, $moveToDoubtful, $from) {
            $amount = (float) $i->amount;
            $fees = round($fees, 3);

            // Whichever account currently carries the instrument gets credited back.
            $carrying = $from === PaymentInstrument::STATUS_RECEIVED
                ? self::accountKey($i)
                : self::accountKey($i, 'collection');

            $partyKey = $i->isIncoming() && $moveToDoubtful
                ? 'doubtful_receivable'
                : self::partyKey($i);

            if ($i->isIncoming()) {
                $lines = [
                    ['account' => AccountMap::code($partyKey), 'debit' => $amount, 'label' => "Unpaid {$i->number}"],
                    ['account' => AccountMap::code($carrying), 'credit' => $amount, 'label' => "Unpaid {$i->number}"],
                ];
                if ($fees > 0) {
                    // Return charges are ours until they are re-invoiced.
                    $lines[] = ['account' => AccountMap::code('bank_fees'), 'debit' => $fees, 'label' => "Return fees {$i->number}"];
                    $lines[] = ['account' => self::bankCode($i), 'credit' => $fees, 'label' => "Return fees {$i->number}"];
                }
            } else {
                // Our own instrument was refused: the supplier is owed again.
                $lines = [
                    ['account' => AccountMap::code(self::accountKey($i)), 'debit' => $amount, 'label' => "Unpaid {$i->number}"],
                    ['account' => AccountMap::code(self::partyKey($i)), 'credit' => $amount, 'label' => "Unpaid {$i->number}"],
                ];
                if ($fees > 0) {
                    $lines[] = ['account' => AccountMap::code('bank_fees'), 'debit' => $fees, 'label' => "Return fees {$i->number}"];
                    $lines[] = ['account' => self::bankCode($i), 'credit' => $fees, 'label' => "Return fees {$i->number}"];
                }
            }

            $entry = AccountingService::post(
                lines: $lines,
                user: $user,
                memo: "Bounced {$i->kind} {$i->instrument_reference}" . ($reason !== '' ? " — {$reason}" : ''),
                referenceType: 'instrument',
                referenceId: $i->id,
                date: $date,
                journalCode: self::journalCode($i),
            );

            $i->update([
                'status' => PaymentInstrument::STATUS_BOUNCED,
                'bounced_at' => $date ?? now()->toDateString(),
                'bounce_reason' => $reason,
                'bank_fees' => (float) $i->bank_fees + $fees,
            ]);
            self::record($i, 'bounced', $from, PaymentInstrument::STATUS_BOUNCED, $user, $entry, $reason);

            // Any installment this instrument was covering goes back to unpaid.
            InstallmentService::unsettleFromInstrument($i);

            return $i->refresh();
        });
    }

    /**
     * A bounced instrument finally settled by other means (cash, transfer).
     * The replacement payment carries its own posting, so this only closes
     * the instrument's own lifecycle.
     */
    public static function settle(PaymentInstrument $i, User $user, string $notes = ''): PaymentInstrument
    {
        self::assertTransition($i, PaymentInstrument::STATUS_SETTLED);
        $from = $i->status;
        $i->update(['status' => PaymentInstrument::STATUS_SETTLED]);
        self::record($i, 'settled', $from, PaymentInstrument::STATUS_SETTLED, $user, null, $notes);

        return $i->refresh();
    }

    // ---------------- outgoing ----------------

    /**
     * Cheque/traite issued to a supplier:
     *   Dr Accounts payable / Cr Cheques (or Notes) payable
     */
    public static function issue(PaymentInstrument $i, User $user): PaymentInstrument
    {
        self::assertTransition($i, PaymentInstrument::STATUS_ISSUED);
        $from = $i->status;

        return DB::transaction(function () use ($i, $user, $from) {
            $entry = AccountingService::post(
                lines: [
                    ['account' => AccountMap::code(self::partyKey($i)), 'debit' => $i->amount, 'label' => $i->counterpartyLabel()],
                    ['account' => AccountMap::code(self::accountKey($i)), 'credit' => $i->amount, 'label' => "{$i->kind} {$i->number}"],
                ],
                user: $user,
                memo: "Issued {$i->kind} {$i->instrument_reference} to {$i->counterpartyLabel()}",
                referenceType: 'instrument',
                referenceId: $i->id,
                date: $i->issue_date?->toDateString(),
                journalCode: self::journalCode($i),
            );

            $i->update(['status' => PaymentInstrument::STATUS_ISSUED]);
            self::record($i, 'issued', $from, PaymentInstrument::STATUS_ISSUED, $user, $entry);

            return $i->refresh();
        });
    }

    /** Cancel an instrument that never took economic effect. */
    public static function cancel(PaymentInstrument $i, User $user, string $reason = ''): PaymentInstrument
    {
        self::assertTransition($i, PaymentInstrument::STATUS_CANCELLED);
        $from = $i->status;

        return DB::transaction(function () use ($i, $user, $reason, $from) {
            $entry = null;

            // If it had already been recognised, reverse that recognition.
            if (in_array($from, [PaymentInstrument::STATUS_RECEIVED, PaymentInstrument::STATUS_ISSUED], true)) {
                $lines = $i->isIncoming()
                    ? [
                        ['account' => AccountMap::code(self::partyKey($i)), 'debit' => $i->amount, 'label' => "Cancelled {$i->number}"],
                        ['account' => AccountMap::code(self::accountKey($i)), 'credit' => $i->amount, 'label' => "Cancelled {$i->number}"],
                    ]
                    : [
                        ['account' => AccountMap::code(self::accountKey($i)), 'debit' => $i->amount, 'label' => "Cancelled {$i->number}"],
                        ['account' => AccountMap::code(self::partyKey($i)), 'credit' => $i->amount, 'label' => "Cancelled {$i->number}"],
                    ];

                $entry = AccountingService::post(
                    lines: $lines,
                    user: $user,
                    memo: "Cancelled {$i->kind} {$i->instrument_reference}" . ($reason !== '' ? " — {$reason}" : ''),
                    referenceType: 'instrument',
                    referenceId: $i->id,
                    journalCode: self::journalCode($i),
                );
            }

            $i->update(['status' => PaymentInstrument::STATUS_CANCELLED]);
            self::record($i, 'cancelled', $from, PaymentInstrument::STATUS_CANCELLED, $user, $entry, $reason);

            return $i->refresh();
        });
    }

    // ---------------- queries ----------------

    /** Instruments still expected to move money, oldest due first. */
    public static function outstanding(?string $kind = null, ?string $direction = null)
    {
        return PaymentInstrument::query()
            ->whereIn('status', PaymentInstrument::OPEN_STATUSES)
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->when($direction, fn ($q) => $q->where('direction', $direction))
            ->orderByRaw('due_date is null, due_date')
            ->orderBy('id');
    }

    /** Portfolio summary used by the dashboard cards. */
    public static function summary(): array
    {
        $open = PaymentInstrument::whereIn('status', PaymentInstrument::OPEN_STATUSES)->get();
        $bounced = PaymentInstrument::where('status', PaymentInstrument::STATUS_BOUNCED)->get();

        $sum = fn ($rows) => round((float) $rows->sum('amount'), 3);
        $incoming = $open->where('direction', PaymentInstrument::DIRECTION_IN);
        $outgoing = $open->where('direction', PaymentInstrument::DIRECTION_OUT);
        $overdue = $open->filter(fn (PaymentInstrument $i) => $i->isOverdue());

        return [
            'outstanding_incoming_count' => $incoming->count(),
            'outstanding_incoming_amount' => $sum($incoming),
            'outstanding_outgoing_count' => $outgoing->count(),
            'outstanding_outgoing_amount' => $sum($outgoing),
            'overdue_count' => $overdue->count(),
            'overdue_amount' => $sum($overdue),
            'bounced_count' => $bounced->count(),
            'bounced_amount' => $sum($bounced),
        ];
    }

    /** Attach a scanned copy of the instrument. */
    public static function attach(
        PaymentInstrument $i,
        string $path,
        string $filename,
        string $mime,
        int $size,
        User $user,
    ): Attachment {
        return Attachment::create([
            'owner_type' => Attachment::OWNER_INSTRUMENT,
            'owner_id' => $i->id,
            'path' => $path,
            'filename' => $filename,
            'mime' => $mime,
            'size' => $size,
            'uploaded_by' => $user->id,
            'created_at' => now(),
        ]);
    }
}
