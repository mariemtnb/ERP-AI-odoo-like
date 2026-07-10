<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = ['name', 'address', 'is_default', 'is_active'];

    protected $attributes = ['address' => '', 'is_default' => false, 'is_active' => true];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public static function defaultWarehouse(): self
    {
        return self::where('is_default', true)->firstOrFail();
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
        ];
    }
}
