<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One debit-or-credit leg of a journal entry. */
class JournalEntryLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['journal_entry_id', 'account_id', 'label', 'debit', 'credit'];

    protected $attributes = ['label' => '', 'debit' => 0, 'credit' => 0];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'account_code' => $this->account?->code,
            'account_name' => $this->account?->name,
            'label' => $this->label,
            'debit' => $this->debit,
            'credit' => $this->credit,
        ];
    }
}
