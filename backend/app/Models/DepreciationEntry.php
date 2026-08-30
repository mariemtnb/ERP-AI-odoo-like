<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepreciationEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'fixed_asset_id', 'period', 'amount', 'book_value_after', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'book_value_after' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'period' => $this->period?->format('Y-m-d'),
            'amount' => $this->amount,
            'book_value_after' => $this->book_value_after,
        ];
    }
}
