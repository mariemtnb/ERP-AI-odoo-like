<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A unit of measure. Within a category, `factor` is its size in the reference unit. */
class UnitOfMeasure extends Model
{
    protected $table = 'units_of_measure';

    public const CATEGORIES = ['unit', 'weight', 'volume', 'length'];

    protected $fillable = ['code', 'name', 'category', 'factor', 'is_reference', 'is_active'];

    protected $attributes = ['factor' => 1, 'is_reference' => false, 'is_active' => true];

    protected function casts(): array
    {
        return ['factor' => 'decimal:8', 'is_reference' => 'boolean', 'is_active' => 'boolean'];
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category,
            'factor' => (string) $this->factor,
            'is_reference' => $this->is_reference,
            'is_active' => $this->is_active,
        ];
    }
}
