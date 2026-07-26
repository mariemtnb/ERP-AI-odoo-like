<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cost or profit centre, used to tag entries for analytical reporting. */
class BusinessUnit extends Model
{
    use Auditable;

    public const KINDS = ['cost_centre', 'profit_centre', 'division'];

    protected $fillable = [
        'company_id', 'code', 'name', 'kind', 'manager_id', 'is_active',
    ];

    protected $attributes = ['kind' => 'cost_centre', 'is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'code' => $this->code,
            'name' => $this->name,
            'kind' => $this->kind,
            'manager_id' => $this->manager_id,
            'manager_email' => $this->manager?->email,
            'is_active' => $this->is_active,
        ];
    }
}
