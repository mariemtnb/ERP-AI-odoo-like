<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Module switch. Turning a module off hides its navigation and refuses its
 * endpoints, without a deployment or a code change.
 */
class FeatureFlag extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'enabled', 'is_locked', 'company_id',
    ];

    protected $attributes = ['description' => '', 'enabled' => true, 'is_locked' => false];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'is_locked' => 'boolean'];
    }

    /** Per-request memo — this is read on nearly every request. */
    private static ?array $cache = null;

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** @return array<string,bool> */
    public static function all(): array
    {
        if (self::$cache === null) {
            try {
                self::$cache = static::query()->pluck('enabled', 'key')
                    ->map(fn ($v) => (bool) $v)->all();
            } catch (\Throwable) {
                // Before the table exists (early migrations), assume enabled.
                return [];
            }
        }

        return self::$cache;
    }

    /**
     * Unknown flags default to ENABLED. A missing row must never silently
     * disable a working module — the failure mode has to be visible, not a
     * feature quietly vanishing.
     */
    public static function enabled(string $key): bool
    {
        return self::all()[$key] ?? true;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'enabled' => $this->enabled,
            'is_locked' => $this->is_locked,
            'company_id' => $this->company_id,
        ];
    }
}
