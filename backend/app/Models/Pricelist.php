<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named set of pricing rules. A customer may be attached to one; the
 * pricelist flagged `is_default` applies to everyone else.
 */
class Pricelist extends Model
{
    protected $fillable = ['name', 'is_active', 'is_default', 'notes'];

    protected $attributes = ['is_active' => true, 'is_default' => false, 'notes' => ''];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_default' => 'boolean'];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(PricelistRule::class);
    }

    /** The active default pricelist, or null when none is set. */
    public static function default(): ?self
    {
        return static::where('is_active', true)->where('is_default', true)->first();
    }

    public function toApi(bool $withRules = false): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'notes' => $this->notes,
            'rule_count' => $this->rules_count ?? $this->rules()->count(),
        ];
        if ($withRules) {
            $data['rules'] = $this->rules->map(fn (PricelistRule $r) => $r->toApi())->values()->all();
        }

        return $data;
    }
}
