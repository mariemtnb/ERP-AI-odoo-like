<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    use Auditable;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'number', 'sale_id', 'customer_id', 'carrier', 'tracking_number',
        'address', 'status', 'shipped_at', 'delivered_at', 'created_by',
    ];

    protected $attributes = ['status' => self::STATUS_PENDING, 'address' => ''];

    protected function casts(): array
    {
        return ['shipped_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'sale' => $this->sale_id,
            'sale_number' => $this->sale?->number,
            'customer' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'carrier' => $this->carrier,
            'tracking_number' => $this->tracking_number,
            'address' => $this->address,
            'status' => $this->status,
            'shipped_at' => $this->shipped_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
        ];
    }
}
