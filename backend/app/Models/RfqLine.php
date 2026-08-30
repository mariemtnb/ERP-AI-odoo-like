<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['rfq_id', 'product_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
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
            'product_name' => $this->product?->name,
            'sku' => $this->product?->sku,
            'quantity' => $this->quantity,
        ];
    }
}
