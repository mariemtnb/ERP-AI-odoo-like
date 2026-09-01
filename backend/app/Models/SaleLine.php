<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['sale_id', 'product_id', 'quantity', 'unit_price', 'discount_pct'];

    protected $attributes = ['discount_pct' => 0];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'discount_pct' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Line total after any per-line discount. */
    public function subtotal(): float
    {
        return round($this->quantity * $this->unit_price * (1 - (float) $this->discount_pct / 100), 2);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'product' => $this->product_id,
            'product_sku' => $this->product?->sku,
            'product_name' => $this->product?->name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount_pct' => $this->discount_pct,
            'subtotal' => number_format($this->subtotal(), 2, '.', ''),
        ];
    }
}
