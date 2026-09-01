<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One rule inside a pricelist.
 *
 *  - target: a product, a category, or (both null) every product
 *  - min_qty: the rule applies from this quantity up
 *  - mode 'fixed':    `value` is the unit price
 *  - mode 'discount': `value` is a percentage off the product's base price
 */
class PricelistRule extends Model
{
    public const FIXED = 'fixed';
    public const DISCOUNT = 'discount';

    protected $fillable = ['pricelist_id', 'product_id', 'category_id', 'min_qty', 'mode', 'value'];

    protected $attributes = ['min_qty' => 0, 'mode' => self::FIXED, 'value' => 0];

    protected function casts(): array
    {
        return ['min_qty' => 'decimal:3', 'value' => 'decimal:3'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** How specific this rule is: product beats category beats blanket. */
    public function specificity(): int
    {
        return $this->product_id ? 2 : ($this->category_id ? 1 : 0);
    }

    /** Resolve this rule to a unit price given the product's base price. */
    public function priceFor(float $basePrice): float
    {
        return $this->mode === self::DISCOUNT
            ? round($basePrice * (1 - (float) $this->value / 100), 2)
            : round((float) $this->value, 2);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'pricelist_id' => $this->pricelist_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'category_id' => $this->category_id,
            'category_name' => $this->category?->name,
            'min_qty' => (string) $this->min_qty,
            'mode' => $this->mode,
            'value' => (string) $this->value,
        ];
    }
}
