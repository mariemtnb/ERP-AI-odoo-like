<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An axis a product can vary on — e.g. Size, Colour. */
class ProductAttribute extends Model
{
    protected $fillable = ['name'];

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class, 'attribute_id');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'values' => $this->relationLoaded('values')
                ? $this->values->map(fn ($v) => ['id' => $v->id, 'value' => $v->value])->values()->all()
                : [],
        ];
    }
}
