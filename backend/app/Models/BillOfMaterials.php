<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillOfMaterials extends Model
{
    use Auditable;

    protected $table = 'bills_of_materials';

    protected $fillable = ['product_id', 'output_quantity', 'is_active', 'created_by'];

    protected $attributes = ['output_quantity' => 1, 'is_active' => true];

    protected function casts(): array
    {
        return ['output_quantity' => 'decimal:3', 'is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(BomComponent::class, 'bom_id');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'product' => $this->product_id,
            'product_name' => $this->product?->name,
            'sku' => $this->product?->sku,
            'output_quantity' => $this->output_quantity,
            'is_active' => $this->is_active,
            'components' => $this->components->map(fn ($c) => $c->toApi())->values()->all(),
        ];
    }
}
