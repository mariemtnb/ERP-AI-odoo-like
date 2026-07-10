<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only ledger row: created, never updated or deleted. */
class StockMovement extends Model
{
    public const TYPE_IN = 'in';
    public const TYPE_OUT = 'out';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public $timestamps = false;

    protected $fillable = [
        'product_id', 'movement_type', 'quantity', 'reason',
        'reference_type', 'reference_id', 'created_by', 'warehouse_id',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'created_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'product' => $this->product_id,
            'product_sku' => $this->product?->sku,
            'product_name' => $this->product?->name,
            'movement_type' => $this->movement_type,
            'quantity' => $this->quantity,
            'reason' => $this->reason,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'created_by_email' => $this->creator?->email,
            'warehouse' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse?->name,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
