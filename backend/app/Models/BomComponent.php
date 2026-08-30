<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomComponent extends Model
{
    public $timestamps = false;

    protected $fillable = ['bom_id', 'component_product_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    public function toApi(): array
    {
        return [
            'component' => $this->component_product_id,
            'component_name' => $this->component?->name,
            'sku' => $this->component?->sku,
            'quantity' => $this->quantity,
            'in_stock' => $this->component?->quantity_in_stock,
        ];
    }
}
