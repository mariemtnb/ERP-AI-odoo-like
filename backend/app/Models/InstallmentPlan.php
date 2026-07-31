<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Installment plan ("khlas bel taqsit") attached to a sale or purchase. */
class InstallmentPlan extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DEFAULTED = 'defaulted';
    public const STATUSES = [
        self::STATUS_ACTIVE, self::STATUS_COMPLETED,
        self::STATUS_CANCELLED, self::STATUS_DEFAULTED,
    ];

    public const FREQUENCIES = ['weekly', 'biweekly', 'monthly', 'quarterly', 'custom'];

    protected $fillable = [
        'number', 'reference_type', 'reference_id', 'customer_id', 'supplier_id',
        'total_amount', 'down_payment', 'installment_count', 'frequency',
        'start_date', 'paid_amount', 'status', 'notes', 'created_by',
    ];

    protected $attributes = [
        'down_payment' => 0, 'paid_amount' => 0, 'frequency' => 'monthly',
        'status' => self::STATUS_ACTIVE, 'notes' => '',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date:Y-m-d'];
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class, 'plan_id')->orderBy('sequence');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function remainingAmount(): float
    {
        return round((float) $this->total_amount - (float) $this->paid_amount, 3);
    }

    /** Sum of installments past due and not fully paid. */
    public function overdueAmount(): float
    {
        return round($this->installments
            ->filter(fn (Installment $i) => $i->isOverdue())
            ->sum(fn (Installment $i) => $i->remainingAmount()), 3);
    }

    public function nextDue(): ?Installment
    {
        return $this->installments
            ->first(fn (Installment $i) => $i->status !== Installment::STATUS_PAID
                && $i->status !== Installment::STATUS_CANCELLED);
    }

    public function toApi(bool $withSchedule = false): array
    {
        $data = [
            'id' => $this->id,
            'number' => $this->number,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier?->name,
            'total_amount' => (string) $this->total_amount,
            'down_payment' => (string) $this->down_payment,
            'paid_amount' => (string) $this->paid_amount,
            'remaining_amount' => number_format($this->remainingAmount(), 3, '.', ''),
            'installment_count' => $this->installment_count,
            'frequency' => $this->frequency,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
        ];

        if ($withSchedule || $this->relationLoaded('installments')) {
            $data['overdue_amount'] = number_format($this->overdueAmount(), 3, '.', '');
            $data['next_due_date'] = $this->nextDue()?->due_date?->format('Y-m-d');
            $data['installments'] = $this->installments
                ->map(fn (Installment $i) => $i->toApi())->values()->all();
        }

        return $data;
    }
}
