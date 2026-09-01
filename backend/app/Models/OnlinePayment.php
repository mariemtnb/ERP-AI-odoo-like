<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlinePayment extends Model
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'sale_id', 'token', 'amount', 'provider', 'status', 'gateway_ref', 'journal_entry_id', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    public function toApi(): array
    {
        return [
            'token' => $this->token,
            'amount' => (string) $this->amount,
            'provider' => $this->provider,
            'status' => $this->status,
            'sale_number' => $this->sale?->number,
            'paid_at' => $this->paid_at?->toISOString(),
        ];
    }
}
