<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    use Auditable;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'number', 'customer_id', 'status', 'sale_date', 'total_amount', 'created_by',
    ];

    protected $attributes = ['status' => self::STATUS_DRAFT, 'total_amount' => 0];

    protected function casts(): array
    {
        return ['sale_date' => 'date:Y-m-d', 'total_amount' => 'decimal:2'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recomputeTotal(): void
    {
        $this->total_amount = $this->lines->sum(fn ($l) => $l->quantity * $l->unit_price);
        $this->save();
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'status' => $this->status,
            'sale_date' => $this->sale_date?->format('Y-m-d'),
            'total_amount' => $this->total_amount,
            'created_by_email' => $this->creator?->email,
            'lines' => $this->lines->map(fn ($l) => $l->toApi())->values()->all(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
