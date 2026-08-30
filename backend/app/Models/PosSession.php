<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSession extends Model
{
    use Auditable;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id', 'status', 'opening_float', 'expected_cash',
        'closing_counted', 'opened_at', 'closed_at',
    ];

    protected $attributes = ['status' => self::STATUS_OPEN, 'opening_float' => 0];

    protected function casts(): array
    {
        return [
            'opening_float' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'closing_counted' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PosOrder::class, 'session_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /** Variance between counted cash and what the till should hold. */
    public function variance(): ?float
    {
        if ($this->closing_counted === null || $this->expected_cash === null) {
            return null;
        }

        return round((float) $this->closing_counted - (float) $this->expected_cash, 2);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'cashier' => $this->user_id,
            'cashier_email' => $this->cashier?->email,
            'opening_float' => $this->opening_float,
            'expected_cash' => $this->expected_cash,
            'closing_counted' => $this->closing_counted,
            'variance' => $this->variance(),
            'orders_count' => $this->orders()->count(),
            'opened_at' => $this->opened_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
        ];
    }
}
