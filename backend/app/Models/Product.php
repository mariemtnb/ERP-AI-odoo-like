<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use Auditable;

    protected $fillable = [
        'sku', 'name', 'category_id', 'description', 'cost_price',
        'sale_price', 'unit', 'min_stock_level', 'is_active',
    ];

    protected $attributes = [
        'description' => '',
        'unit' => 'unit',
        'quantity_in_stock' => 0,
        'min_stock_level' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'quantity_in_stock' => 'decimal:3',
            'min_stock_level' => 'decimal:3',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLowStock(): bool
    {
        return (float) $this->quantity_in_stock <= (float) $this->min_stock_level;
    }

    /** Matches the DRF ProductSerializer payload exactly. */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category_id,
            'category_name' => $this->category?->name,
            'description' => $this->description,
            'cost_price' => $this->cost_price,
            'sale_price' => $this->sale_price,
            'unit' => $this->unit,
            'quantity_in_stock' => $this->quantity_in_stock,
            'min_stock_level' => $this->min_stock_level,
            'is_low_stock' => $this->isLowStock(),
            'is_active' => $this->is_active,
        ];
    }
}
