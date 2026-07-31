<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A named bundle of permissions, so sets can be granted in one gesture. */
class PermissionGroup extends Model
{
    protected $fillable = ['key', 'name', 'description'];

    protected $attributes = ['description' => ''];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_group_items');
    }

    public function toApi(bool $withPermissions = true): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'permissions' => $withPermissions
                ? $this->permissions->map(fn (Permission $p) => $p->key)->values()->all()
                : [],
        ];
    }
}
