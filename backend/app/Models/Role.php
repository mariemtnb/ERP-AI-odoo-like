<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A role, optionally inheriting from a parent.
 *
 * The three built-ins (employee → manager → admin) mirror the `users.role`
 * column exactly, so the existing `role:` middleware keeps working while
 * custom roles can be layered on top.
 */
class Role extends Model
{
    public const ADMIN = 'admin';
    public const MANAGER = 'manager';
    public const EMPLOYEE = 'employee';
    public const SYSTEM_ROLES = [self::EMPLOYEE, self::MANAGER, self::ADMIN];

    protected $fillable = ['key', 'name', 'description', 'parent_id', 'is_system', 'level'];

    protected $attributes = ['description' => '', 'is_system' => false, 'level' => 0];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withPivot('allow')
            ->withTimestamps();
    }

    public function permissionGroups(): BelongsToMany
    {
        return $this->belongsToMany(PermissionGroup::class, 'role_permissions')
            ->withPivot('allow')
            ->withTimestamps();
    }

    /** This role and every ancestor, nearest first. */
    public function lineage(): array
    {
        $chain = [$this];
        $role = $this;
        $guard = 0;
        // Defensive: a mis-edited parent_id could otherwise loop forever.
        while ($role->parent_id && $guard++ < 20) {
            $role = $role->parent;
            if (! $role) {
                break;
            }
            $chain[] = $role;
        }

        return $chain;
    }

    public function toApi(bool $withPermissions = false): array
    {
        $data = [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'parent_key' => $this->parent?->key,
            'is_system' => $this->is_system,
            'level' => $this->level,
            'user_count' => $this->users_count ?? null,
        ];

        if ($withPermissions) {
            $data['permissions'] = $this->permissions
                ->map(fn (Permission $p) => $p->key)->values()->all();
        }

        return $data;
    }
}
