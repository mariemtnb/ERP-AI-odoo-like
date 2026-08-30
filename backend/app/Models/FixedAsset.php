<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAsset extends Model
{
    use Auditable;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISPOSED = 'disposed';

    protected $fillable = [
        'name', 'category', 'acquisition_date', 'acquisition_cost', 'salvage_value',
        'useful_life_months', 'method', 'accumulated_depreciation', 'status',
        'disposed_date', 'created_by',
    ];

    protected $attributes = [
        'category' => '', 'salvage_value' => 0, 'method' => 'straight_line',
        'accumulated_depreciation' => 0, 'status' => self::STATUS_ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date:Y-m-d',
            'disposed_date' => 'date:Y-m-d',
            'acquisition_cost' => 'decimal:2',
            'salvage_value' => 'decimal:2',
            'accumulated_depreciation' => 'decimal:2',
            'useful_life_months' => 'integer',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(DepreciationEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function depreciableBase(): float
    {
        return round((float) $this->acquisition_cost - (float) $this->salvage_value, 2);
    }

    public function bookValue(): float
    {
        return round((float) $this->acquisition_cost - (float) $this->accumulated_depreciation, 2);
    }

    /** Fully depreciated when accumulated has reached the depreciable base. */
    public function isFullyDepreciated(): bool
    {
        return (float) $this->accumulated_depreciation + 0.001 >= $this->depreciableBase();
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'acquisition_date' => $this->acquisition_date?->format('Y-m-d'),
            'acquisition_cost' => $this->acquisition_cost,
            'salvage_value' => $this->salvage_value,
            'useful_life_months' => $this->useful_life_months,
            'method' => $this->method,
            'accumulated_depreciation' => $this->accumulated_depreciation,
            'book_value' => number_format($this->bookValue(), 2, '.', ''),
            'status' => $this->status,
            'disposed_date' => $this->disposed_date?->format('Y-m-d'),
            'fully_depreciated' => $this->isFullyDepreciated(),
        ];
    }
}
