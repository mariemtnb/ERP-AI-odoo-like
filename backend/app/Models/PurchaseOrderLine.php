<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['purchase_order_id', 'product_id', 'quantity', 'received_qty', 'unit_price', 'tax_rate'];

    protected $attributes = ['received_qty' => 0, 'tax_rate' => 0];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'received_qty' => 'decimal:3', 'unit_price' => 'decimal:2', 'tax_rate' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function subtotal(): float
    {
        return round($this->quantity * $this->unit_price, 2);
    }

    /** Quantity still to arrive. */
    public function remaining(): float
    {
        return max(0.0, round((float) $this->quantity - (float) $this->received_qty, 3));
    }

    /** VAT within this line, at its own rate (prices are inclusive). */
    public function vatAmount(): float
    {
        $rate = (float) $this->tax_rate;

        return $rate > 0 ? round($this->subtotal() * $rate / (100 + $rate), 2) : 0.0;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'product' => $this->product_id,
            'product_sku' => $this->product?->sku,
            'product_name' => $this->product?->name,
            'quantity' => $this->quantity,
            'received_qty' => $this->received_qty,
            'remaining' => number_format($this->remaining(), 3, '.', ''),
            'unit_price' => $this->unit_price,
            'tax_rate' => $this->tax_rate,
            'subtotal' => number_format($this->subtotal(), 2, '.', ''),
            'vat' => number_format($this->vatAmount(), 2, '.', ''),
        ];
    }
}
