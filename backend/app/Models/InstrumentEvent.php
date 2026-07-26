<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only lifecycle history of a cheque / effet, with the journal entry
 * each step produced — this is what the AI agent reads to explain "why was
 * this posted?".
 */
class InstrumentEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'instrument_id', 'event', 'from_status', 'to_status', 'amount',
        'journal_entry_id', 'notes', 'created_by', 'created_at',
    ];

    protected $attributes = ['from_status' => '', 'amount' => 0, 'notes' => ''];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(PaymentInstrument::class, 'instrument_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'amount' => (string) $this->amount,
            'journal_entry_id' => $this->journal_entry_id,
            'journal_entry_number' => $this->journalEntry?->number,
            'notes' => $this->notes,
            'created_by_email' => $this->creator?->email,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
