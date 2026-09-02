<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A reminder that was actually sent — one row per sale-and-level. */
class DunningLog extends Model
{
    protected $fillable = [
        'sale_id', 'level', 'days_overdue', 'outstanding', 'emailed_to', 'emailed', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['emailed' => 'boolean', 'outstanding' => 'decimal:3', 'sent_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'level' => $this->level,
            'days_overdue' => $this->days_overdue,
            'outstanding' => (string) $this->outstanding,
            'emailed' => $this->emailed,
            'emailed_to' => $this->emailed_to,
            'sent_at' => $this->sent_at?->toISOString(),
        ];
    }
}
