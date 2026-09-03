<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One allowed value on an attribute — e.g. Small, Red. */
class ProductAttributeValue extends Model
{
    protected $fillable = ['attribute_id', 'value'];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'attribute_id');
    }

    /** "Size: Small" — used to name and describe a variant. */
    public function label(): string
    {
        return trim(($this->attribute?->name ? $this->attribute->name.': ' : '').$this->value);
    }
}
