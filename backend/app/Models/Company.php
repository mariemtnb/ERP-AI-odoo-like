<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A legal entity. Multi-company: one row per company, optionally nested. */
class Company extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'name', 'legal_name', 'company_profile_id', 'parent_id',
        'currency', 'locale', 'timezone', 'is_default', 'is_active',
    ];

    protected $attributes = [
        'legal_name' => '', 'currency' => 'TND', 'locale' => 'fr',
        'timezone' => 'Africa/Tunis', 'is_default' => false, 'is_active' => true,
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class, 'company_profile_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function businessUnits(): HasMany
    {
        return $this->hasMany(BusinessUnit::class);
    }

    public function fiscalYears(): HasMany
    {
        return $this->hasMany(FiscalYear::class);
    }

    /** The active company. Single-company installs never think about this. */
    public static function current(): ?self
    {
        return static::where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'company_profile_id' => $this->company_profile_id,
            'parent_id' => $this->parent_id,
            'parent_name' => $this->parent?->name,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'branch_count' => $this->branches_count ?? null,
        ];
    }
}
