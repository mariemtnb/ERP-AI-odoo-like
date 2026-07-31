<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One scheduled payment of an installment plan (une échéance). */
class Installment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PARTIAL = 'partially_paid';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUSES = [
        self::STATUS_PENDING, self::STATUS_PARTIAL, self::STATUS_PAID,
        self::STATUS_OVERDUE, self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'plan_id', 'sequence', 'due_date', 'amount', 'paid_amount', 'status',
        'payment_method', 'paid_at', 'is_down_payment', 'notes',
    ];

    protected $attributes = [
        'paid_amount' => 0, 'status' => self::STATUS_PENDING,
        'payment_method' => '', 'is_down_payment' => false, 'notes' => '',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'paid_at' => 'date:Y-m-d',
            'is_down_payment' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function remainingAmount(): float
    {
        return round((float) $this->amount - (float) $this->paid_amount, 3);
    }

    /**
     * Late = past due (plus the configured grace period) and not settled.
     * The grace period is a company setting, never a hardcoded assumption.
     */
    public function isOverdue(): bool
    {
        if (in_array($this->status, [self::STATUS_PAID, self::STATUS_CANCELLED], true)) {
            return false;
        }
        $grace = (int) CompanyProfile::current()->late_payment_grace_days;

        return $this->due_date !== null
            && $this->due_date->copy()->addDays($grace)->isPast();
    }

    public function daysLate(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) $this->due_date->diffInDays(now(), absolute: true);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'sequence' => $this->sequence,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'amount' => (string) $this->amount,
            'paid_amount' => (string) $this->paid_amount,
            'remaining_amount' => number_format($this->remainingAmount(), 3, '.', ''),
            'status' => $this->status,
            'is_overdue' => $this->isOverdue(),
            'days_late' => $this->daysLate(),
            'payment_method' => $this->payment_method,
            'paid_at' => $this->paid_at?->format('Y-m-d'),
            'is_down_payment' => $this->is_down_payment,
            'notes' => $this->notes,
        ];
    }
}
