<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use Auditable;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELLED = 'cancelled';

    public const INTERVALS = ['monthly', 'quarterly', 'yearly'];

    protected $fillable = [
        'number', 'customer_id', 'description', 'amount', 'interval',
        'status', 'start_date', 'next_invoice_date', 'created_by',
    ];

    protected $attributes = ['status' => self::STATUS_ACTIVE];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'start_date' => 'date:Y-m-d',
            'next_invoice_date' => 'date:Y-m-d',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    /** Advance a date by one billing interval. */
    public function advance(Carbon $from): Carbon
    {
        return match ($this->interval) {
            'monthly' => $from->copy()->addMonthNoOverflow(),
            'quarterly' => $from->copy()->addMonthsNoOverflow(3),
            'yearly' => $from->copy()->addYearNoOverflow(),
            default => $from->copy()->addMonthNoOverflow(),
        };
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'description' => $this->description,
            'amount' => $this->amount,
            'interval' => $this->interval,
            'status' => $this->status,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'next_invoice_date' => $this->next_invoice_date?->format('Y-m-d'),
            'invoices_count' => $this->invoices()->count(),
            'billed_total' => number_format((float) $this->invoices()->sum('amount'), 2, '.', ''),
        ];
    }
}
