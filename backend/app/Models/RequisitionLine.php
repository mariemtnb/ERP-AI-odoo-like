<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionLine extends Model
{
    protected $fillable = ['requisition_id', 'product_id', 'quantity', 'estimated_price', 'notes'];

    protected $attributes = ['estimated_price' => 0, 'notes' => ''];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'estimated_price' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'quantity' => (string) $this->quantity,
            'estimated_price' => (string) $this->estimated_price,
            'notes' => $this->notes,
        ];
    }
}
