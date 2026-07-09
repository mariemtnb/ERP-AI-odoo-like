<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'description'];

    protected $attributes = ['description' => ''];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'product_count' => (int) ($this->products_count ?? $this->products()->count()),
        ];
    }
}
