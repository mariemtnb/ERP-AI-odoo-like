<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandedCostAllocation extends Model
{
    protected $fillable = ['landed_cost_id', 'product_id', 'basis', 'amount'];

    protected function casts(): array
    {
        return ['basis' => 'decimal:3', 'amount' => 'decimal:3'];
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
            'basis' => (string) $this->basis,
            'amount' => (string) $this->amount,
        ];
    }
}
