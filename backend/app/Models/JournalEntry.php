<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Double-entry journal entry header. Always balanced (see AccountingService). */
class JournalEntry extends Model
{
    protected $fillable = [
        'number', 'entry_date', 'memo', 'reference_type', 'reference_id', 'created_by',
    ];

    protected $attributes = ['memo' => '', 'reference_type' => 'manual'];

    protected function casts(): array
    {
        return ['entry_date' => 'date:Y-m-d'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function totalDebit(): float
    {
        return (float) $this->lines->sum('debit');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'entry_date' => $this->entry_date?->format('Y-m-d'),
            'memo' => $this->memo,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'created_by_email' => $this->creator?->email,
            'total' => number_format($this->totalDebit(), 2, '.', ''),
            'lines' => $this->lines->map(fn (JournalEntryLine $l) => $l->toApi())->values()->all(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
