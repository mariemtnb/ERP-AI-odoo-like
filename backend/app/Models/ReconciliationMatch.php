<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a bank statement line to what it represents. Many-to-many by design:
 * one transfer can settle several invoices, and one invoice can arrive in
 * several transfers.
 */
class ReconciliationMatch extends Model
{
    public $timestamps = false;

    public const TYPE_PAYMENT = 'payment';
    public const TYPE_INSTRUMENT = 'instrument';
    public const TYPE_INSTALLMENT = 'installment';
    public const TYPE_SALE = 'sale';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPES = [
        self::TYPE_PAYMENT, self::TYPE_INSTRUMENT, self::TYPE_INSTALLMENT,
        self::TYPE_SALE, self::TYPE_PURCHASE, self::TYPE_ADJUSTMENT,
    ];

    protected $fillable = [
        'bank_transaction_id', 'matchable_type', 'matchable_id', 'amount',
        'journal_entry_id', 'note', 'created_by', 'created_at',
    ];

    protected $attributes = ['note' => ''];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Human label for the matched object, resolved lazily per row. */
    public function matchedLabel(): string
    {
        return match ($this->matchable_type) {
            self::TYPE_PAYMENT => Payment::find($this->matchable_id)?->number ?? 'payment',
            self::TYPE_INSTRUMENT => PaymentInstrument::find($this->matchable_id)?->number ?? 'instrument',
            self::TYPE_INSTALLMENT => (function () {
                $i = Installment::with('plan')->find($this->matchable_id);

                return $i ? "{$i->plan?->number} #{$i->sequence}" : 'installment';
            })(),
            self::TYPE_SALE => Sale::find($this->matchable_id)?->number ?? 'sale',
            self::TYPE_PURCHASE => PurchaseOrder::find($this->matchable_id)?->number ?? 'purchase',
            default => 'adjustment',
        };
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'bank_transaction_id' => $this->bank_transaction_id,
            'matchable_type' => $this->matchable_type,
            'matchable_id' => $this->matchable_id,
            'matched_label' => $this->matchedLabel(),
            'amount' => (string) $this->amount,
            'journal_entry_id' => $this->journal_entry_id,
            'journal_entry_number' => $this->journalEntry?->number,
            'note' => $this->note,
            'created_by_email' => $this->creator?->email,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
