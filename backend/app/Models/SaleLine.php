<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['sale_id', 'product_id', 'quantity', 'unit_price'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
            'subtotal' => number_format($this->quantity * $this->unit_price, 2, '.', ''),
        ];
    }
}
