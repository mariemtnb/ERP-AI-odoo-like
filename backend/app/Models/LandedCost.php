<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Freight/duty/insurance capitalised onto a received purchase order. */
class LandedCost extends Model
{
    public const BY_VALUE = 'value';
    public const BY_QUANTITY = 'quantity';
    public const ALLOCATIONS = [self::BY_VALUE, self::BY_QUANTITY];

    protected $fillable = [
        'purchase_order_id', 'description', 'amount', 'allocation', 'journal_entry_id', 'created_by',
    ];

    protected $attributes = ['allocation' => self::BY_VALUE];

    protected function casts(): array
    {
        return ['amount' => 'decimal:3'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LandedCostAllocation::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'description' => $this->description,
            'amount' => (string) $this->amount,
            'allocation' => $this->allocation,
            'journal_entry_id' => $this->journal_entry_id,
            'allocations' => $this->relationLoaded('allocations')
                ? $this->allocations->map(fn ($a) => $a->toApi())->values()->all()
                : [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
