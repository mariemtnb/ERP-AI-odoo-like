<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A settlement fact: money actually moved. Cash receipts, transfers, cheque
 * clearings, installment payments and advances are all rows here, each
 * carrying the journal entry it produced.
 */
class Payment extends Model
{
    use Auditable;

    public const DIRECTION_IN = 'inbound';
    public const DIRECTION_OUT = 'outbound';
    public const DIRECTIONS = [self::DIRECTION_IN, self::DIRECTION_OUT];

    public const METHOD_CASH = 'cash';
    public const METHOD_TRANSFER = 'bank_transfer';
    public const METHOD_CHEQUE = 'cheque';
    public const METHOD_TRAITE = 'traite';
    public const METHOD_CARD = 'card';
    public const METHOD_DEPOSIT = 'bank_deposit';
    public const METHOD_WITHDRAWAL = 'bank_withdrawal';

    public const METHODS = [
        self::METHOD_CASH, self::METHOD_TRANSFER, self::METHOD_CHEQUE,
        self::METHOD_TRAITE, self::METHOD_CARD, self::METHOD_DEPOSIT,
        self::METHOD_WITHDRAWAL,
    ];

    protected $fillable = [
        'number', 'direction', 'method', 'amount', 'payment_date',
        'customer_id', 'supplier_id', 'bank_account_id', 'instrument_id',
        'installment_id', 'reference_type', 'reference_id', 'is_advance',
        'journal_entry_id', 'reference', 'notes', 'created_by',
    ];

    protected $attributes = [
        'reference_type' => '', 'is_advance' => false, 'reference' => '', 'notes' => '',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date:Y-m-d',
            'is_advance' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(PaymentInstrument::class, 'instrument_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'direction' => $this->direction,
            'method' => $this->method,
            'amount' => (string) $this->amount,
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier?->name,
            'bank_account_id' => $this->bank_account_id,
            'bank_account_label' => $this->bankAccount?->label,
            'instrument_id' => $this->instrument_id,
            'instrument_number' => $this->instrument?->number,
            'installment_id' => $this->installment_id,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'is_advance' => $this->is_advance,
            'journal_entry_id' => $this->journal_entry_id,
            'journal_entry_number' => $this->journalEntry?->number,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
