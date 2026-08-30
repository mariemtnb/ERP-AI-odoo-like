<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReorderRule extends Model
{
    use Auditable;

    protected $fillable = [
        'product_id', 'supplier_id', 'min_qty', 'reorder_qty', 'is_active', 'created_by',
    ];

    protected $attributes = ['is_active' => true, 'min_qty' => 0, 'reorder_qty' => 0];

    protected function casts(): array
    {
        return [
            'min_qty' => 'decimal:3',
            'reorder_qty' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'product' => $this->product_id,
            'product_name' => $this->product?->name,
            'sku' => $this->product?->sku,
            'current_stock' => $this->product?->quantity_in_stock,
            'supplier' => $this->supplier_id,
            'supplier_name' => $this->supplier?->name,
            'min_qty' => $this->min_qty,
            'reorder_qty' => $this->reorder_qty,
            'is_active' => $this->is_active,
        ];
    }
}
