<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A physical site of a company, usually mapped onto a warehouse. */
class Branch extends Model
{
    use Auditable;

    protected $fillable = [
        'company_id', 'code', 'name', 'address', 'city', 'phone',
        'warehouse_id', 'is_default', 'is_active',
    ];

    protected $attributes = [
        'address' => '', 'city' => '', 'phone' => '',
        'is_default' => false, 'is_active' => true,
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'company_name' => $this->company?->name,
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse?->name,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
        ];
    }
}
