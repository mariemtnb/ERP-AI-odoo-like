<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLot extends Model
{
    use Auditable;

    protected $fillable = [
        'product_id', 'warehouse_id', 'lot_number', 'expiry_date',
        'quantity', 'created_by', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date:Y-m-d',
            'quantity' => 'decimal:3',
            'received_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast() && ! $this->expiry_date->isToday();
    }

    public function daysToExpiry(): ?int
    {
        if ($this->expiry_date === null) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($this->expiry_date, false);
    }

    public function status(): string
    {
        if ($this->expiry_date === null) {
            return 'ok';
        }
        $days = $this->daysToExpiry();
        if ($days < 0) {
            return 'expired';
        }
        if ($days <= 7) {
            return 'expiring';
        }

        return 'ok';
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'product' => $this->product_id,
            'product_name' => $this->product?->name,
            'sku' => $this->product?->sku,
            'warehouse' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse?->name,
            'lot_number' => $this->lot_number,
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'days_to_expiry' => $this->daysToExpiry(),
            'quantity' => $this->quantity,
            'status' => $this->status(),
            'received_at' => $this->received_at?->toISOString(),
        ];
    }
}
