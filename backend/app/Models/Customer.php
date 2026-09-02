<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use Auditable;

    protected $fillable = ['name', 'email', 'phone', 'address', 'tax_id', 'notes', 'is_active', 'pricelist_id'];

    protected $attributes = ['email' => '', 'phone' => '', 'address' => '', 'notes' => ''];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'tax_id' => $this->tax_id,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'pricelist_id' => $this->pricelist_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
